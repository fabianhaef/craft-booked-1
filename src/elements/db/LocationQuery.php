<?php

namespace anvildev\booked\elements\db;

use craft\elements\db\ElementQuery;

/**
 * @method \anvildev\booked\elements\Location[]|array all($db = null)
 * @method \anvildev\booked\elements\Location|array|null one($db = null)
 * @method \anvildev\booked\elements\Location|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class LocationQuery extends ElementQuery
{
    // Craft 6-only. See EmployeeQuery for the full explanation of this
    // property and the elementQueryBeforeQuery() override below -
    // replaces the Craft 5 beforePrepare()/joinElementTable() pattern,
    // confirmed dead as of Craft 6.0.0 (MIGRATION_PLAN.md finding #10).
    protected string $table = 'booked_locations';

    public ?string $timezone = null;
    public ?bool $enabled = null;

    public function timezone(?string $value): static
    {
        $this->timezone = $value;
        return $this;
    }

    public function enabled(?bool $value = true): static
    {
        $this->enabled = $value;
        return $this;
    }

    // Craft 6-only. See EmployeeQuery::elementQueryBeforeQuery() for why
    // this uses $this->query (not the now-nonexistent $subQuery) and
    // Laravel's own where() (not Db::parseParam() through andWhere()).
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'booked_locations.timezone', 'booked_locations.addressLine1', 'booked_locations.addressLine2',
            'booked_locations.locality', 'booked_locations.administrativeArea',
            'booked_locations.postalCode', 'booked_locations.countryCode',
        ]);

        if ($this->enabled !== null) {
            $this->query->where('elements.enabled', (bool)$this->enabled);
        }

        if ($this->timezone !== null) {
            $this->query->where('booked_locations.timezone', $this->timezone);
        }
    }
}
