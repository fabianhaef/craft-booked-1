<?php

namespace anvildev\booked\validators;

use Closure;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Yii2Adapter\Validation\LegacyYiiRules;
use ReflectionClass;

/**
 * Craft 6-only. Bridges an element's legacy defineRules() into the native
 * Ruleset validation system.
 *
 * Mirrors CraftCms\Yii2Adapter\Validation\LegacyElementRules exactly, except
 * for one call: that class calls LegacyYiiRules::mergeWildcardRules() without
 * copyErrors: true, so the closure it builds always clears validateAttribute()'s
 * errors and skips forwarding them to Laravel's validator, regardless of
 * whether the target has a usable getErrors() - verified empirically against
 * a real Employee element, 2026-11-18 (see MIGRATION_PLAN.md finding #9).
 * Every Booked element needs this version instead.
 *
 * copyErrors: true also leaves the raw Yii error sitting directly on the
 * target (never cleared) in addition to the copy Laravel's validator
 * forwards, so every message would otherwise appear twice in the merged
 * error bag. rules() wraps the closure to clear that direct copy right
 * after it's been forwarded, once per attribute per validation pass.
 *
 * A second, independent problem: mergeWildcardRules() returns a bare
 * Closure under the '*' key, and Laravel's Validator skips any non-implicit
 * rule - including a plain closure - when the attribute's value is empty,
 * before the closure ever runs. That's fatal for a Yii 'required' rule,
 * whose entire job is to validate emptiness. rules() wraps the closure in
 * LegacyYiiRule, which implements Laravel's legacy ImplicitRule contract,
 * so it still runs on empty values.
 *
 * A third, independent problem: the closure mergeWildcardRules() builds
 * calls Yii's Validator::validateAttribute() (singular) directly for every
 * matching rule, unconditionally. That method never checks a rule's 'when'
 * option at all - only Validator::validateAttributes() (plural), the batch
 * method Yii's own Model::validate() normally calls, does that (confirmed
 * against vendor/yiisoft/yii2/validators/Validator.php: the check is inside
 * validateAttributes(), not validateAttribute()). Without a fix, every
 * 'when'-conditioned rule in a Booked element - e.g. Service's minDays/
 * maxDays, required only when durationType is 'flexible_days' - runs
 * unconditionally (MIGRATION_PLAN.md finding #14). rules() filters those
 * rules out itself, before they ever reach mergeWildcardRules(), by
 * evaluating each rule's own 'when' callback against the current subject.
 *
 * @author Anvildev
 * @since 2.0.0
 */
class LegacyElementRules extends ElementRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $reflectionClass = new ReflectionClass($this->subject);

        if (!$reflectionClass->hasMethod('defineRules')) {
            return $rules;
        }

        $method = $reflectionClass->getMethod('defineRules');
        $yiiRules = $method->invoke($this->subject);
        $yiiRules = self::applyWhenConditions($yiiRules, $this->subject);

        $rules = LegacyYiiRules::mergeWildcardRules(
            rules: $rules,
            target: $this->subject,
            yiiRules: $yiiRules,
            copyErrors: true,
        );

        $target = $this->subject;
        $innerClosure = $rules['*'][0];
        $rules['*'][0] = new LegacyYiiRule(function(string $attribute, mixed $value, Closure $fail) use ($innerClosure, $target): void {
            $innerClosure($attribute, $value, $fail);
            $target->clearErrors($attribute);
        });

        return $rules;
    }

    /**
     * Drops any Yii rule whose 'when' callback evaluates false for the
     * current subject, before the rule ever reaches
     * LegacyYiiRules::validatorClosure() (see the class docblock for why
     * that closure can't honor 'when' itself).
     *
     * Evaluated once per rule here, using the subject's current state -
     * not once per (rule, attribute) pair the way Yii's own check runs -
     * since every 'when' closure Booked actually writes only inspects the
     * model, never the specific attribute being validated.
     *
     * @param array<int, mixed> $yiiRules
     * @return array<int, mixed>
     */
    private static function applyWhenConditions(array $yiiRules, object $subject): array
    {
        return array_values(array_filter($yiiRules, function($rule) use ($subject) {
            if (!is_array($rule) || !isset($rule['when']) || !is_callable($rule['when'])) {
                return true;
            }

            return (bool)($rule['when'])($subject);
        }));
    }
}
