<?php

namespace anvildev\booked\elements\db;

use anvildev\booked\Booked;
use anvildev\booked\contracts\ReservationQueryInterface;
use anvildev\booked\validators\LegacyQueryParam;
use Craft;
use craft\elements\db\ElementQuery;
use craft\web\Application as WebApplication;

/**
 * @method \anvildev\booked\elements\Reservation[]|array all($db = null)
 * @method \anvildev\booked\elements\Reservation|array|null one($db = null)
 * @method \anvildev\booked\elements\Reservation|array|null nth(int $n, ?Connection $db = null)
 */
class ReservationQuery extends ElementQuery implements ReservationQueryInterface
{
    // Craft 6-only. See EmployeeQuery for the full explanation of this
    // property - replaces the Craft 5 joinElementTable('booked_reservations')
    // call in the old beforePrepare() override below, confirmed dead as of
    // Craft 6.0.0 (MIGRATION_PLAN.md finding #10).
    protected string $table = 'booked_reservations';

    public function init(): void
    {
        parent::init();
        // Return all reservation statuses (confirmed, pending, cancelled) by default,
        // rather than relying on Craft's default 'enabled' element status.
        $this->status = null;
    }

    public ?string $userName = null;
    public ?string $userEmail = null;
    public ?string $userPhone = null;
    public ?int $userId = null;
    public array|string|null $bookingDate = null;
    public array|string|null $endDate = null;
    public ?string $startTime = null;
    public ?string $endTime = null;
    public array|string|null $reservationStatus = null;
    public array|int|null $employeeId = null;
    public array|int|null $locationId = null;
    public array|int|null $serviceId = null;
    public array|int|null $eventDateId = null;
    public ?string $confirmationToken = null;

    private bool $_forCurrentUser = false;

    public function employeeId(array|int|null $value): static
    {
        $this->employeeId = $value;
        return $this;
    }

    public function locationId(array|int|null $value): static
    {
        $this->locationId = $value;
        return $this;
    }

    public function serviceId(array|int|null $value): static
    {
        $this->serviceId = $value;
        return $this;
    }

    public function eventDateId(array|int|null $value): static
    {
        $this->eventDateId = $value;
        return $this;
    }

    public function userName(?string $value): static
    {
        $this->userName = $value;
        return $this;
    }

    public function userEmail(?string $value): static
    {
        $this->userEmail = $value;
        return $this;
    }

    public function userPhone(?string $value): static
    {
        $this->userPhone = $value;
        return $this;
    }

    public function userId(?int $value): static
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Filter by the currently logged-in user (by userId or email fallback).
     * If no user is logged in, returns no results.
     */
    public function forCurrentUser(): static
    {
        $this->_forCurrentUser = true;
        return $this;
    }

    public function bookingDate(array|string|null $value): static
    {
        $this->bookingDate = $value;
        return $this;
    }

    public function endDate(array|string|null $value): static
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

    public function reservationStatus(array|string|null $value): static
    {
        $this->reservationStatus = $value;
        return $this;
    }

    public function confirmationToken(?string $value): static
    {
        $this->confirmationToken = $value;
        return $this;
    }

    public function withEmployee(): static
    {
        $this->with(['employee']);
        return $this;
    }

    public function withService(): static
    {
        $this->with(['service']);
        return $this;
    }

    public function withLocation(): static
    {
        $this->with(['location']);
        return $this;
    }

    public function withRelations(): static
    {
        $this->with(['employee', 'service', 'location']);
        return $this;
    }

    // Craft 6-only signature: statusCondition() returns a raw condition array
    // on Craft 5, but a Closure(Builder): Builder on Craft 6 — no single
    // implementation satisfies both override contracts, so this branch
    // targets 6.x exclusively (see MIGRATION_PLAN.md).
    protected function statusCondition(string $status): \Closure
    {
        return match ($status) {
            'confirmed', 'pending', 'cancelled', 'no_show' => fn(\Illuminate\Database\Query\Builder $q) => $q->where('booked_reservations.status', $status),
            default => parent::statusCondition($status),
        };
    }

    // Craft 6-only. See EmployeeQuery::elementQueryBeforeQuery() for why
    // this uses $this->query (not the now-nonexistent $subQuery) and native
    // Laravel calls (not Db::parseParam() through andWhere()) throughout.
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        // Prevent ID collisions with other element types when AR mode IDs overlap
        $this->query->where('elements.type', $this->elementType);

        $this->query->addSelect([
            'booked_reservations.userName', 'booked_reservations.userEmail', 'booked_reservations.userPhone',
            'booked_reservations.userId', 'booked_reservations.userTimezone', 'booked_reservations.bookingDate',
            'booked_reservations.endDate', 'booked_reservations.startTime', 'booked_reservations.endTime',
            'booked_reservations.status', 'booked_reservations.notes', 'booked_reservations.sessionNotes',
            'booked_reservations.notificationSent', 'booked_reservations.confirmationToken',
            'booked_reservations.employeeId', 'booked_reservations.locationId', 'booked_reservations.serviceId',
            'booked_reservations.quantity', 'booked_reservations.virtualMeetingUrl',
            'booked_reservations.virtualMeetingProvider', 'booked_reservations.virtualMeetingId',
            'booked_reservations.googleEventId', 'booked_reservations.outlookEventId',
            'booked_reservations.emailReminder24hSent', 'booked_reservations.emailReminder1hSent',
            'booked_reservations.smsReminder24hSent', 'booked_reservations.eventDateId',
            'booked_reservations.smsConfirmationSent', 'booked_reservations.smsCancellationSent',
            'booked_reservations.smsDeliveryStatus', 'booked_reservations.smsConfirmationSentAt',
            'booked_reservations.siteId as bookingSiteId',
        ]);

        // Simple param filters (property name => column name)
        foreach ([
            'userName' => 'userName', 'userEmail' => 'userEmail', 'userPhone' => 'userPhone',
            'userId' => 'userId', 'endDate' => 'endDate', 'startTime' => 'startTime', 'endTime' => 'endTime',
            'reservationStatus' => 'status',
            'employeeId' => 'employeeId', 'locationId' => 'locationId', 'serviceId' => 'serviceId',
            'eventDateId' => 'eventDateId',
        ] as $prop => $col) {
            LegacyQueryParam::apply($this->query, "booked_reservations.$col", $this->$prop);
        }

        LegacyQueryParam::apply($this->query, 'booked_reservations.confirmationToken', $this->confirmationToken);

        // Current user: match by userId OR email (legacy bookings)
        if ($this->_forCurrentUser) {
            $currentUser = Craft::$app->getUser()->getIdentity();

            if ($currentUser) {
                $this->query->where(
                    fn($q) => $q->where('booked_reservations.userId', $currentUser->id)
                        ->orWhere('booked_reservations.userEmail', $currentUser->email),
                );
            } else {
                $this->query->whereRaw('1 = 0');
            }
        }

        // Booking date supports operator pairs, 'and' compound conditions, and simple values
        if ($this->bookingDate) {
            $this->_applyBookingDateFilter();
        }

        $this->_applyStaffScope();
    }

    /**
     * Limits a view-only staff member to the employees they manage, and only
     * while Craft is building an element index.
     *
     * The bookings index query is created inside Craft's own controller, so a
     * filter applied by our controllers never runs for it. Seat counts,
     * availability and the public booking form also use this query. Scoping
     * those would hide other employees' bookings and let a staff member book
     * a seat that is already taken, so the limit stays on element-index requests.
     *
     * PermissionService::scopeReservationQuery() already implements the staff
     * narrowing for callers like CustomerService::bookingsFor() — reused here
     * rather than re-deriving the same employeeId condition a second way. But
     * unlike those callers, nothing upstream of this one already required
     * booked-viewBookings: Craft's own element-indexes controller only checks
     * for CP access, not any permission specific to this plugin, so every
     * logged-in CP user reaches this query. getStaffEmployeeIds() returns
     * null ("full access") for exactly that user too — it was written
     * assuming a caller had already gated on booked-viewBookings — so this
     * method has to check that itself before delegating, or any CP user with
     * no Booked permission at all would see every reservation.
     */
    private function _applyStaffScope(): void
    {
        // Console and queue runs have no user session to scope against.
        // Checked against the application type, not a request-reported flag:
        // the latter falls back to a PHP_SAPI comparison when unset, which
        // misclassifies a web app booted from a CLI context.
        if (!Craft::$app instanceof WebApplication) {
            return;
        }

        $segments = Craft::$app->getRequest()->getActionSegments();
        if (($segments[0] ?? null) !== 'element-indexes') {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();
        if ($user === null || !$user->can('booked-viewBookings')) {
            $this->query->whereRaw('0 = 1');
            return;
        }

        Booked::getInstance()?->getPermission()->scopeReservationQuery($this);
    }

    private function _applyBookingDateFilter(): void
    {
        $bd = $this->bookingDate;

        if (!is_array($bd)) {
            LegacyQueryParam::apply($this->query, 'booked_reservations.bookingDate', $bd);
            return;
        }

        // Operator format: ['>', '2024-01-01']
        if (count($bd) === 2 && is_string($bd[0]) && in_array($bd[0], ['>', '<', '>=', '<=', '!=', '<>'], true)) {
            $this->query->where('booked_reservations.bookingDate', $bd[0], $bd[1]);
            return;
        }

        // Compound: ['and', '>= 2026-02-01', '<= 2026-02-28']
        if (($bd[0] ?? null) === 'and') {
            foreach (array_slice($bd, 1) as $condition) {
                LegacyQueryParam::apply($this->query, 'booked_reservations.bookingDate', $condition);
            }
            return;
        }

        LegacyQueryParam::apply($this->query, 'booked_reservations.bookingDate', $bd);
    }
}
