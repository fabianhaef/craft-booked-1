<?php

namespace anvildev\booked\validators;

use Illuminate\Database\Query\Builder;

/**
 * Craft 6-only. Applies a Yii-style "param" value - what
 * craft\helpers\Db::parseParam() has always accepted from Booked's own
 * query setters (a plain scalar, an array of scalars, or a string with a
 * leading Yii comparison operator like '>=', '<=', 'not ', etc.) - onto a
 * native Laravel query builder directly.
 *
 * Booked's query classes used to hand these same values to
 * Db::parseParam() and feed the resulting Yii condition array through
 * ->andWhere(), which is itself a Craft 6 adapter mixin
 * (CraftCms\Yii2Adapter\Mixins\ElementQueryMixin::andWhere()) built on
 * CraftCms\Yii2Adapter\Helpers\Queries\buildCondition()/convertBindings() -
 * both confirmed buggy for some condition shapes (MIGRATION_PLAN.md
 * findings #10 and #12). This bypasses that whole bridge: no
 * Db::parseParam(), no andWhere(), no Yii condition array at any point -
 * just this method and Laravel's own where()/whereIn().
 *
 * The recognized operator prefixes are copied verbatim from the adapter's
 * own Db::_parseParamOperator() (vendor/craftcms/yii2-adapter/legacy/helpers/Db.php),
 * so values already written against Yii's grammar - like Craft's own
 * date-range condition rules' `">= $date"` - keep working unchanged.
 *
 * Deliberately narrower than Db::parseParam() itself: it only recognizes
 * the operator syntax Booked's own call sites actually use (plain values,
 * array values, and a single leading operator), not Yii's full param
 * grammar (case-insensitive search, :empty:, column-type coercion,
 * multi-value OR-of-operators). Extend this method's own tests first if
 * Booked ever needs more of that grammar.
 *
 * @author Anvildev
 * @since 2.0.0
 */
class LegacyQueryParam
{
    /** @var array<int, string> */
    private const array OPERATORS = ['not ', '!=', '<=', '>=', '<', '>', '='];

    /**
     * @param mixed $value A plain scalar, an array of scalars, or an
     *     operator-prefixed string (e.g. '>= 2026-01-01'). Null is a no-op.
     */
    public static function apply(Builder $query, string $column, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        if (is_array($value)) {
            $query->whereIn($column, $value);

            return;
        }

        if (is_string($value)) {
            foreach (self::OPERATORS as $operator) {
                if (stripos($value, $operator) === 0) {
                    $rest = trim(mb_substr($value, strlen($operator)));
                    $query->where($column, $operator === 'not ' ? '!=' : $operator, $rest);

                    return;
                }
            }
        }

        $query->where($column, $value);
    }
}
