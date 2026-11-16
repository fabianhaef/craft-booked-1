<?php

namespace anvildev\booked\contracts;

/**
 * Query contract for reservations in both Element and ActiveRecord modes.
 */
interface ReservationQueryInterface
{
    /** @param int|int[]|null $value */
    public function id($value): static;

    /** @param mixed $value Site ID, '*' for all sites, or null */
    public function siteId($value): static;

    public function userName(?string $value): static;
    public function userEmail(?string $value): static;
    public function userId(?int $value): static;

    /** @param string|array|null $value Single date, range array, or comparison */
    public function bookingDate(array|string|null $value): static;

    public function startTime(?string $value): static;
    public function endTime(?string $value): static;

    /** @param int|int[]|null $value */
    public function employeeId(array|int|null $value): static;

    /** @param int|int[]|null $value */
    public function locationId(array|int|null $value): static;

    /** @param int|int[]|null $value */
    public function serviceId(array|int|null $value): static;

    /** @param int|int[]|null $value */
    public function eventDateId(array|int|null $value): static;

    /** @param string|string[]|null $value */
    public function status(array|string|null $value): static;

    /** @param string|string[]|null $value */
    public function reservationStatus(array|string|null $value): static;

    public function confirmationToken(?string $value): static;
    public function forCurrentUser(): static;
    public function withEmployee(): static;
    public function withService(): static;
    public function withLocation(): static;
    public function withRelations(): static;

    // Query-execution signatures, kept compatible with Craft 6 core:
    // `ReservationQuery` inherits these from `CraftCms\Cms\Element\Queries\ElementQuery`
    // / Laravel's query Builder contract, so the parameter types must match the core
    // implementations (`?int` for limit/offset), and no return types are declared here
    // because the core methods return `self`, which cannot satisfy a `static` interface
    // return type. `sum()`, `each()`, and `andWhere()` are intentionally omitted: they
    // aren't part of Craft 6's core query chain (andWhere exists only as an adapter
    // mixin, which is invisible to compile-time interface checks), so declaring them
    // here would make `ReservationQuery` fatally abstract. `ReservationModelQuery`
    // still provides all three as concrete methods.
    public function orderBy($columns);
    public function limit(?int $limit);
    public function offset(?int $offset);
    public function one($db = null);
    public function all($db = null);
    public function count($columns = '*'): int;
    public function exists(): bool;

    /** @return int[] */
    public function ids(): array;

    public function where($condition, $params = []);
}
