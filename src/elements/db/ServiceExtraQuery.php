<?php

namespace anvildev\booked\elements\db;

use craft\elements\db\ElementQuery;

class ServiceExtraQuery extends ElementQuery
{
    // Craft 6-only. See EmployeeQuery for the full explanation of this
    // property and the elementQueryBeforeQuery() override below -
    // replaces the Craft 5 beforePrepare()/joinElementTable() pattern,
    // confirmed dead as of Craft 6.0.0 (MIGRATION_PLAN.md finding #10).
    protected string $table = 'booked_service_extras';

    public ?float $price = null;
    public ?int $duration = null;
    public ?int $maxQuantity = null;
    public ?bool $isRequired = null;

    public function price(?float $value): static
    {
        $this->price = $value;
        return $this;
    }

    public function duration(?int $value): static
    {
        $this->duration = $value;
        return $this;
    }

    public function maxQuantity(?int $value): static
    {
        $this->maxQuantity = $value;
        return $this;
    }

    public function isRequired(?bool $value): static
    {
        $this->isRequired = $value;
        return $this;
    }

    // Craft 6-only. See EmployeeQuery::elementQueryBeforeQuery() for why
    // this uses $this->query (not the now-nonexistent $subQuery) and
    // Laravel's own where() (not Db::parseParam() through andWhere()).
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'booked_service_extras.propagationMethod', 'booked_service_extras.price',
            'booked_service_extras.duration', 'booked_service_extras.maxQuantity',
            'booked_service_extras.isRequired', 'booked_service_extras.description',
        ]);

        foreach (['price', 'duration', 'maxQuantity'] as $param) {
            if ($this->$param !== null) {
                $this->query->where("booked_service_extras.$param", $this->$param);
            }
        }

        if ($this->isRequired !== null) {
            $this->query->where('booked_service_extras.isRequired', (bool)$this->isRequired);
        }
    }
}
