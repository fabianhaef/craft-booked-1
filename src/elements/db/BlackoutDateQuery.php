<?php

namespace anvildev\booked\elements\db;

use craft\elements\db\ElementQuery;

/**
 * @method \anvildev\booked\elements\BlackoutDate[]|array all($db = null)
 * @method \anvildev\booked\elements\BlackoutDate|array|null one($db = null)
 * @method \anvildev\booked\elements\BlackoutDate|array|null nth(int $n, ?Connection $db = null)
 */
class BlackoutDateQuery extends ElementQuery
{
    // Craft 6-only. See EmployeeQuery for the full explanation of this
    // property and the elementQueryBeforeQuery() override below -
    // replaces the Craft 5 beforePrepare()/joinElementTable() pattern,
    // confirmed dead as of Craft 6.0.0 (MIGRATION_PLAN.md finding #10).
    protected string $table = 'booked_blackout_dates';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?bool $isActive = null;
    public array|int|null $locationId = null;
    public array|int|null $employeeId = null;

    public function startDate(?string $value): static
    {
        $this->startDate = $value;
        return $this;
    }

    public function endDate(?string $value): static
    {
        $this->endDate = $value;
        return $this;
    }

    public function isActive(?bool $value): static
    {
        $this->isActive = $value;
        return $this;
    }

    public function locationId(array|int|null $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    public function employeeId(array|int|null $value): static
    {
        $this->employeeId = $value;
        return $this;
    }

    // Craft 6-only. See EmployeeQuery::elementQueryBeforeQuery() for why
    // this uses $this->query (not the now-nonexistent $subQuery) and
    // Laravel's own where()/whereIn() (not Db::parseParam() through
    // andWhere()). The junction-table subqueries are rebuilt as native
    // Laravel subquery closures for the same reason: a Yii \craft\db\Query
    // passed as the 'in' operand would have to be translated by the same
    // buggy adapter bridge that corrupted SQL twice already (findings #10
    // and #12).
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'booked_blackout_dates.startDate', 'booked_blackout_dates.endDate', 'booked_blackout_dates.isActive',
        ]);

        foreach (['startDate', 'endDate'] as $param) {
            if ($this->$param !== null) {
                $this->query->where("booked_blackout_dates.$param", $this->$param);
            }
        }

        if ($this->isActive !== null) {
            $this->query->where('booked_blackout_dates.isActive', (bool)$this->isActive);
        }

        // Filter by location/employee via junction tables
        foreach ([
            'locationId' => ['booked_blackout_dates_locations', 'locationId'],
            'employeeId' => ['booked_blackout_dates_employees', 'employeeId'],
        ] as $prop => [$junctionTable, $fk]) {
            if ($this->$prop !== null) {
                $values = (array)$this->$prop;
                $this->query->whereIn(
                    'elements.id',
                    fn($query) => $query->select('blackoutDateId')->from($junctionTable)->whereIn($fk, $values),
                );
            }
        }
    }

    // Craft 6-only signature: statusCondition() returns a raw condition array
    // on Craft 5, but a Closure(Builder): Builder on Craft 6 — no single
    // implementation satisfies both override contracts, so this branch
    // targets 6.x exclusively (see MIGRATION_PLAN.md).
    protected function statusCondition(string $status): \Closure
    {
        return match ($status) {
            'active' => fn(\Illuminate\Database\Query\Builder $q) => $q->whereBool('booked_blackout_dates.isActive', true),
            'inactive' => fn(\Illuminate\Database\Query\Builder $q) => $q->whereBool('booked_blackout_dates.isActive', false),
            default => parent::statusCondition($status),
        };
    }
}
