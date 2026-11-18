<?php

namespace anvildev\booked\elements\db;

use craft\elements\db\ElementQuery;

/**
 * @method \anvildev\booked\elements\Schedule[]|array all($db = null)
 * @method \anvildev\booked\elements\Schedule|array|null one($db = null)
 * @method \anvildev\booked\elements\Schedule|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class ScheduleQuery extends ElementQuery
{
    // Craft 6-only. See EmployeeQuery for the full explanation of this
    // property and the elementQueryBeforeQuery() override below -
    // replaces the Craft 5 beforePrepare()/joinElementTable() pattern,
    // confirmed dead as of Craft 6.0.0 (MIGRATION_PLAN.md finding #10).
    protected string $table = 'booked_schedules';

    public ?int $employeeId = null;
    /** @var string|null Date in Y-m-d format */
    public ?string $activeOn = null;
    public ?bool $enabled = null;

    public function employeeId(?int $value): static
    {
        $this->employeeId = $value;
        return $this;
    }

    public function activeOn(string|\DateTime|null $value): static
    {
        $this->activeOn = $value instanceof \DateTime ? $value->format('Y-m-d') : $value;
        return $this;
    }

    public function enabled(?bool $value = true): static
    {
        $this->enabled = $value;
        return $this;
    }

    // Craft 6-only. See EmployeeQuery::elementQueryBeforeQuery() for why
    // this uses $this->query (not the now-nonexistent $subQuery) and
    // native Laravel calls (not Db::parseParam() through andWhere()). The
    // old two-query innerJoin (once on $subQuery for filtering, once on
    // $query for the real select) collapses into a single join on $query.
    // The CASE-based order replaces Craft's own default order outright
    // (matching the old code's effect, since it also overwrote whatever
    // default order Craft 5 had already applied by this point) - $query
    // ->reorder() clears what parent::elementQueryBeforeQuery() just
    // applied above, since Craft 6 only skips applying its own default
    // order when $query already has one *before* that call runs.
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'booked_schedules.workingHours', 'booked_schedules.startDate', 'booked_schedules.endDate',
        ]);

        if ($this->enabled !== null) {
            $this->query->where('elements.enabled', (bool)$this->enabled);
        }

        if ($this->employeeId !== null) {
            $employeeId = $this->employeeId;
            $this->query->join(
                'booked_employee_schedule_assignments as assignments',
                fn($join) => $join->on('assignments.scheduleId', '=', 'booked_schedules.id')
                    ->where('assignments.employeeId', $employeeId),
            );
            $this->query->addSelect(['assignments.sortOrder']);

            // Order by date specificity: both dates > one date > neither
            $this->query->reorder();
            $this->query->orderByRaw('CASE
                WHEN booked_schedules.startDate IS NOT NULL AND booked_schedules.endDate IS NOT NULL THEN 1
                WHEN booked_schedules.startDate IS NOT NULL OR booked_schedules.endDate IS NOT NULL THEN 2
                ELSE 3
            END');
            $this->query->orderBy('assignments.sortOrder');
        }

        if ($this->activeOn !== null) {
            $activeOn = $this->activeOn;
            $this->query->where(
                fn($q) => $q->whereNull('booked_schedules.startDate')->orWhere('booked_schedules.startDate', '<=', $activeOn),
            );
            $this->query->where(
                fn($q) => $q->whereNull('booked_schedules.endDate')->orWhere('booked_schedules.endDate', '>=', $activeOn),
            );
        }
    }
}
