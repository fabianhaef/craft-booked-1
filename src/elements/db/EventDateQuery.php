<?php

namespace anvildev\booked\elements\db;

use anvildev\booked\validators\LegacyQueryParam;
use craft\elements\db\ElementQuery;

/**
 * @method \anvildev\booked\elements\EventDate[]|array all($db = null)
 * @method \anvildev\booked\elements\EventDate|array|null one($db = null)
 * @method \anvildev\booked\elements\EventDate|array|null nth(int $n, ?Connection $db = null)
 */
class EventDateQuery extends ElementQuery
{
    // Craft 6-only. See EmployeeQuery for the full explanation of this
    // property and the elementQueryBeforeQuery() override below -
    // replaces the Craft 5 beforePrepare()/joinElementTable() pattern,
    // confirmed dead as of Craft 6.0.0 (MIGRATION_PLAN.md finding #10).
    protected string $table = 'booked_event_dates';

    public ?int $locationId = null;
    public ?string $eventDate = null;
    public ?string $endDate = null;
    public ?string $startTime = null;
    public ?string $endTime = null;
    public ?bool $enabled = null;
    public bool $withTrashed = false;

    public function locationId(?int $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    public function eventDate(?string $value): static
    {
        $this->eventDate = $value;
        return $this;
    }

    public function endDate(?string $value): static
    {
        $this->endDate = $value;
        return $this;
    }

    public function startTime(?string $value): static
    {
        $this->startTime = $value;
        return $this;
    }

    public function endTime(?string $value): static
    {
        $this->endTime = $value;
        return $this;
    }

    public function enabled(?bool $value): static
    {
        $this->enabled = $value;
        return $this;
    }

    public function withTrashed(bool $value = true): static
    {
        $this->withTrashed = $value;
        return $this;
    }

    // Craft 6-only. See EmployeeQuery::elementQueryBeforeQuery() for why
    // this uses $this->query (not the now-nonexistent $subQuery) and native
    // Laravel calls (not Db::parseParam() through andWhere()). eventDate/
    // endDate/startTime/endTime go through LegacyQueryParam specifically
    // because EventDateDateConditionRule (Craft's own date-range condition
    // builder UI) feeds this an operator-prefixed string like
    // '>= 2026-01-01', not just a plain value.
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'booked_event_dates.propagationMethod', 'booked_event_dates.locationId',
            'booked_event_dates.eventDate', 'booked_event_dates.endDate',
            'booked_event_dates.startTime', 'booked_event_dates.endTime',
            'booked_event_dates.description', 'booked_event_dates.capacity',
            'booked_event_dates.allowCancellation', 'booked_event_dates.cancellationPolicyHours',
            'booked_event_dates.allowRefund', 'booked_event_dates.refundTiers', 'booked_event_dates.price',
            'booked_event_dates.enabled', 'booked_event_dates.deletedAt', 'booked_event_dates.enableWaitlist',
        ]);

        if ($this->locationId !== null) {
            $this->query->where('booked_event_dates.locationId', $this->locationId);
        }

        foreach (['eventDate', 'endDate', 'startTime', 'endTime'] as $param) {
            LegacyQueryParam::apply($this->query, "booked_event_dates.$param", $this->$param);
        }

        if ($this->enabled !== null) {
            $this->query->where('booked_event_dates.enabled', (bool)$this->enabled);
        }

        if (!$this->withTrashed) {
            $this->query->whereNull('booked_event_dates.deletedAt');
        }
    }
}
