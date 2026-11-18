<?php

namespace anvildev\booked\validators;

use Closure;
use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * Craft 6-only. Wraps a closure-style validation callback
 * ($attribute, $value, $fail) as an implicit Laravel validation rule, so it
 * still runs when the attribute's value is empty.
 *
 * A bare Closure passed as a Laravel rule is wrapped by
 * Illuminate\Validation\ClosureValidationRule, which implements only the
 * plain Rule contract. Laravel's own Validator::isValidatable() skips any
 * non-implicit rule outright when the value is empty, before the closure
 * ever runs - fatal for a bridged Yii 'required' rule, whose entire job is
 * to validate emptiness. Verified empirically against a real BlackoutDate
 * element, 2026-11-18 (see MIGRATION_PLAN.md finding #9). Implementing
 * ImplicitRule (the legacy Rule contract Laravel still checks for
 * implicit-ness) keeps Laravel from skipping it.
 *
 * @author Anvildev
 * @since 2.0.0
 */
class LegacyYiiRule implements ImplicitRule
{
    private bool $failed = false;

    /** @var array<int, string> */
    private array $messages = [];

    /**
     * @param Closure(string, mixed, Closure): void $callback
     */
    public function __construct(private readonly Closure $callback)
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     */
    public function passes($attribute, $value): bool
    {
        $this->failed = false;
        $this->messages = [];

        ($this->callback)($attribute, $value, function(string $message): void {
            $this->failed = true;
            $this->messages[] = $message;
        });

        return !$this->failed;
    }

    /** @return array<int, string> */
    public function message(): array
    {
        return $this->messages;
    }
}
