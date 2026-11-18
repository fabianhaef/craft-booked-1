<?php

namespace anvildev\booked\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * @method \anvildev\booked\elements\Employee[]|array all($db = null)
 * @method \anvildev\booked\elements\Employee|array|null one($db = null)
 * @method \anvildev\booked\elements\Employee|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class EmployeeQuery extends ElementQuery
{
    // Craft 6-only. Replaces the Craft 5 joinElementTable('booked_employees')
    // call in the old beforePrepare() override below: declaring $table joins
    // the table to `elements` automatically and auto-discovers all its
    // columns via schema introspection, so no manual addSelect() is needed
    // either. See elementQueryBeforeQuery() for the rest of the Craft 6 port.
    protected string $table = 'booked_employees';

    public ?int $userId = null;
    public ?int $locationId = null;
    public ?int $serviceId = null;
    public ?bool $enabled = null;

    public function userId(?int $value): static
    {
        $this->userId = $value;
        return $this;
    }

    public function locationId(?int $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    public function serviceId(?int $value): static
    {
        $this->serviceId = $value;
        return $this;
    }

    public function enabled(?bool $value = true): static
    {
        $this->enabled = $value;
        return $this;
    }

    // Craft 6-only. Replaces the Craft 5 beforePrepare() override below -
    // that hook is no longer called as of 6.0.0 (see MIGRATION_PLAN.md
    // finding #10). Conditions move from the old $subQuery onto $query
    // directly, which no longer exists as a separate concept; and they're
    // applied with Laravel's own where()/whereRaw() rather than
    // Db::parseParam()'s Yii condition arrays through andWhere(), because
    // the adapter's Yii-condition-to-Laravel bridge has multiple confirmed
    // bugs that corrupt the resulting SQL (see findings #10 and #12) -
    // calling Laravel's real query builder methods directly bypasses that
    // bridge entirely instead of routing through it.
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'booked_employees.userId', 'booked_employees.locationId', 'booked_employees.email',
            'booked_employees.workingHours', 'booked_employees.serviceIds',
        ]);

        foreach (['userId', 'locationId'] as $param) {
            if ($this->$param !== null) {
                $this->query->where("booked_employees.$param", $this->$param);
            }
        }

        if ($this->enabled !== null) {
            $this->query->where('elements.enabled', (bool)$this->enabled);
        }

        if ($this->serviceId !== null) {
            $id = (int)$this->serviceId;

            if (Craft::$app->getDb()->getIsPgsql()) {
                $this->query->whereRaw(
                    '"booked_employees"."serviceIds"::jsonb @> ?::jsonb',
                    [json_encode([$id])],
                );
            } else {
                $this->query->whereRaw(
                    'JSON_CONTAINS(`booked_employees`.`serviceIds`, ?)',
                    [json_encode($id)],
                );
            }
        }
    }
}
