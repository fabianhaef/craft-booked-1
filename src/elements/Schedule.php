<?php

namespace anvildev\booked\elements;

use anvildev\booked\Booked;
use anvildev\booked\elements\db\ScheduleQuery;
use anvildev\booked\elements\traits\HasWeeklySchedule;
use anvildev\booked\records\ScheduleRecord;
use anvildev\booked\traits\HasElementPermissions;
use anvildev\booked\traits\HasEnabledStatus;
use anvildev\booked\validators\LegacyElementRules;
use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\SetStatus;
use craft\elements\User;
use craft\helpers\Html;
use CraftCms\RulesetValidation\Attributes\Ruleset;

#[Ruleset(LegacyElementRules::class)]
class Schedule extends Element
{
    use HasEnabledStatus;
    use HasElementPermissions;
    use HasWeeklySchedule;

    // Craft 6-only: a property hook replaces the Craft 5 init()-based
    // normalization below. init() is never called at all under Craft 6
    // (Component::__construct() just assigns config values; it never calls
    // a subclass's init(), and calling it manually confirms why - this
    // class's own override opens with parent::init(), which hits the same
    // "no real method, only a mixin, no mixin for this one either"
    // BadMethodCallException as beforePrepare()/defineRules() before it.
    // The set hook runs on every assignment - including Craft 6 hydrating
    // this property from a database row, which is exactly where the bug
    // showed up: workingHours stayed a raw JSON string, so
    // getScheduleForDay() found nothing and the public booking wizard
    // showed zero availability on every date (MIGRATION_PLAN.md finding #15).
    public array $workingHours = [] {
        set(array | string | null $value) {
            $this->workingHours = $this->normalizeWorkingHours($value);
        }
    }
    public ?string $startDate = null;
    public ?string $endDate = null;
    /** Sort order from assignment (set during query, not persisted on element) */
    public ?int $sortOrder = null;

    public static function displayName(): string
    {
        return Craft::t('booked', 'element.schedule');
    }
    public static function pluralDisplayName(): string
    {
        return Craft::t('booked', 'element.schedules');
    }
    public static function refHandle(): ?string
    {
        return 'schedule';
    }
    public static function gqlTypeNameByContext(mixed $context): string
    {
        return 'BookedSchedule';
    }
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext(null);
    }
    public static function hasTitles(): bool
    {
        return true;
    }
    /** @return ScheduleQuery */
    public static function find(): ScheduleQuery
    {
        return new ScheduleQuery(static::class);
    }

    protected static function defineActions(?string $source = null): array
    {
        return [SetStatus::class, Delete::class];
    }

    protected static function defineSources(string $context): array
    {
        return [['key' => '*', 'label' => Craft::t('booked', 'element.allSchedules'), 'defaultSort' => ['title', 'asc']]];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('app', 'Title')],
            'startDate' => ['label' => Craft::t('booked', 'labels.startDate')],
            'endDate' => ['label' => Craft::t('booked', 'labels.endDate')],
            'workingDays' => ['label' => Craft::t('booked', 'schedule.workingDays')],
            'assignedEmployees' => ['label' => Craft::t('booked', 'schedule.assignedEmployees')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['title', 'workingDays', 'startDate', 'endDate'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('app', 'Title'),
                'orderBy' => 'elements_sites.title',
                'attribute' => 'title',
            ],
            [
                'label' => Craft::t('booked', 'labels.startDate'),
                'orderBy' => 'booked_schedules.startDate',
                'attribute' => 'startDate',
            ],
            [
                'label' => Craft::t('booked', 'labels.endDate'),
                'orderBy' => 'booked_schedules.endDate',
                'attribute' => 'endDate',
            ],
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
            ],
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['startDate', 'endDate'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true],
            [['workingHours'], 'safe'],
        ];
    }

    protected function attributeHtml(string $attribute): string
    {
        $dash = Html::tag('span', '–', ['class' => 'light']);
        return match ($attribute) {
            'startDate', 'endDate' => ($v = $this->$attribute) ? Html::encode(Craft::$app->formatter->asDate($v, 'short')) : $dash,
            'workingDays' => ($days = $this->getScheduledDays())
                ? Html::encode(implode(', ', array_map(fn($d) => [
                    1 => Craft::t('booked', 'labels.mon'), 2 => Craft::t('booked', 'labels.tue'),
                    3 => Craft::t('booked', 'labels.wed'), 4 => Craft::t('booked', 'labels.thu'),
                    5 => Craft::t('booked', 'labels.fri'), 6 => Craft::t('booked', 'labels.sat'),
                    7 => Craft::t('booked', 'labels.sun'),
                ][$d] ?? '', $days)))
                : $dash,
            'assignedEmployees' => ($emps = $this->getAssignedEmployees())
                ? implode(', ', array_map(fn($e) => Html::encode($e->title), $emps))
                : $dash,
            default => parent::attributeHtml($attribute),
        };
    }

    protected function cpEditUrl(): ?string
    {
        return 'booked/schedules/' . $this->id;
    }
    public function canDuplicate(User $user): bool
    {
        return true;
    }

    protected function permissionKey(): string
    {
        return 'booked-manageEmployees';
    }

    public function afterSave(bool $isNew): void
    {
        // Availability memoizes the resolved schedules for the request. Editing
        // one here — its hours, its capacity, its date range — invalidates them,
        // and the caches cannot see an element save on their own.
        Booked::getInstance()?->getScheduleAssignment()->clearServiceScheduleCache();

        $record = (!$isNew ? ScheduleRecord::findOne($this->id) : null) ?? new ScheduleRecord();
        if (!$record->id) {
            $record->id = (int)$this->id;
        }

        $record->startDate = $this->startDate;
        $record->endDate = $this->endDate;
        $record->workingHours = $this->normalizeWorkingHours($this->workingHours);
        $record->save(false);

        parent::afterSave($isNew);
    }

    public function afterDelete(): void
    {
        Booked::getInstance()?->getScheduleAssignment()->clearServiceScheduleCache();

        ScheduleRecord::findOne($this->id)?->delete();
        \anvildev\booked\records\EmployeeScheduleAssignmentRecord::deleteAll(['scheduleId' => $this->id]);
        parent::afterDelete();
    }

    public function getCapacityForDay(int $dayOfWeek): ?int
    {
        $hours = $this->workingHours[$dayOfWeek] ?? null;
        if (!$hours || empty($hours['enabled'])) {
            return null;
        }
        $cap = $hours['capacity'] ?? null;
        return $cap !== null && $cap !== '' ? (int)$cap : null;
    }

    public function getScheduleData(): ?array
    {
        return is_array($this->workingHours) ? $this->workingHours : null;
    }

    public function isActiveOn(string|\DateTime $date): bool
    {
        if (!$this->enabled) {
            return false;
        }
        $dateStr = is_string($date) ? (new \DateTime($date))->format('Y-m-d') : $date->format('Y-m-d');
        return (!$this->startDate || $dateStr >= $this->startDate)
            && (!$this->endDate || $dateStr <= $this->endDate);
    }

    /** @return Employee[] */
    public function getAssignedEmployees(): array
    {
        if (!$this->id) {
            return [];
        }
        $records = \anvildev\booked\records\EmployeeScheduleAssignmentRecord::find()->where(['scheduleId' => $this->id])->all();
        if (empty($records)) {
            return [];
        }
        return Employee::find()->siteId('*')->id(array_map(fn($r) => $r->employeeId, $records))->status(null)->all();
    }

    // Craft 6-only: getErrors()/hasErrors()/addError() are no longer real
    // declared methods on the base Element/Component — the adapter only
    // backfills them as dynamic runtime macros (__call()-based). The legacy
    // defineRules() validation bridge
    // (anvildev\booked\validators\LegacyElementRules) checks method_exists()
    // before forwarding a validator's errors, which is blind to
    // __call()-based macros — so without a real override here, every
    // defineRules() validation error on this element is silently swallowed.
    // Mirrors Reservation's own override, added for an unrelated reason
    // (ReservationInterface's `implements` check) in an earlier fix.
    // Not dual-compatible: errors() doesn't exist on Craft 5.
    public function getErrors(?string $attribute = null): array
    {
        return $attribute === null ? $this->errors()->getMessages() : $this->errors()->get($attribute);
    }

    public function hasErrors(?string $attribute = null): bool
    {
        return $attribute === null ? $this->errors()->isNotEmpty() : $this->errors()->has($attribute);
    }

    public function addError(string $attribute, string $error = ''): void
    {
        $this->errors()->add($attribute, $error);
    }
}
