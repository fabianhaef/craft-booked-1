<?php

namespace anvildev\booked\elements\db;

use craft\elements\db\ElementQuery;

/**
 * @method \anvildev\booked\elements\Service[]|array all($db = null)
 * @method \anvildev\booked\elements\Service|array|null one($db = null)
 * @method \anvildev\booked\elements\Service|array|null nth(int $n, ?\yii\db\Connection $db = null)
 */
class ServiceQuery extends ElementQuery
{
    // Craft 6-only. See EmployeeQuery for the full explanation of this
    // property and the elementQueryBeforeQuery() override below -
    // replaces the Craft 5 beforePrepare()/joinElementTable() pattern,
    // confirmed dead as of Craft 6.0.0 (MIGRATION_PLAN.md finding #10).
    protected string $table = 'booked_services';

    public ?int $duration = null;
    public ?float $price = null;
    public ?string $durationType = null;
    public ?int $minDays = null;
    public ?int $maxDays = null;
    public ?bool $enabled = null;
    public bool $withTrashed = false;
    public array|int|null $locationId = null;

    public function duration(?int $value): static
    {
        $this->duration = $value;
        return $this;
    }

    public function price(?float $value): static
    {
        $this->price = $value;
        return $this;
    }

    public function durationType(?string $value): static
    {
        $this->durationType = $value;
        return $this;
    }

    public function enabled(?bool $value = true): static
    {
        $this->enabled = $value;
        return $this;
    }

    public function withTrashed(bool $value = true): static
    {
        $this->withTrashed = $value;
        return $this;
    }

    public function locationId(array|int|null $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    // Craft 6-only. See EmployeeQuery::elementQueryBeforeQuery() for why
    // this uses $this->query (not the now-nonexistent $subQuery) and
    // native Laravel calls (not Db::parseParam() through andWhere()). The
    // old ['is not', 'booked_services.id', null] check is dropped: under
    // Craft 6, booked_services is the query's own base FROM table (not
    // left-joined to it), so every row the query can even produce already
    // has a non-null booked_services.id by construction - the join itself
    // now enforces what that condition used to check for.
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'booked_services.propagationMethod', 'booked_services.description', 'booked_services.duration',
            'booked_services.bufferBefore', 'booked_services.bufferAfter', 'booked_services.price',
            'booked_services.allowCancellation', 'booked_services.cancellationPolicyHours',
            'booked_services.allowRefund', 'booked_services.refundTiers', 'booked_services.virtualMeetingProvider',
            'booked_services.minTimeBeforeBooking', 'booked_services.durationType', 'booked_services.pricingMode',
            'booked_services.minDays', 'booked_services.maxDays', 'booked_services.timeSlotLength',
            'booked_services.availabilitySchedule', 'booked_services.customerLimitEnabled',
            'booked_services.customerLimitCount', 'booked_services.customerLimitPeriod',
            'booked_services.customerLimitPeriodType', 'booked_services.enableWaitlist',
            'booked_services.taxCategoryId', 'booked_services.deletedAt', 'booked_services.reservationFieldLayoutId',
        ]);

        foreach (['duration', 'price', 'durationType', 'minDays', 'maxDays'] as $param) {
            if ($this->$param !== null) {
                $this->query->where("booked_services.$param", $this->$param);
            }
        }

        if ($this->enabled !== null) {
            $this->query->where('elements.enabled', (bool)$this->enabled);
        }

        if (!$this->withTrashed) {
            $this->query->whereNull('booked_services.deletedAt');
        }

        if ($this->locationId !== null) {
            $values = (array)$this->locationId;
            $this->query->whereIn(
                'elements.id',
                fn($query) => $query->select('serviceId')->from('booked_service_locations')->whereIn('locationId', $values),
            );
        }
    }
}
