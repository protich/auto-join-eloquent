<?php

namespace protich\AutoJoinEloquent\Model;

use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * Class: AutoJoinRelation
 *
 * Model-facing description of the constraints required by a complex relation.
 *
 * The package creates this object from the Eloquent relation and passes it to
 * the model's describeAutoJoinRelation() hook. The resulting description is
 * authoritative: models must add every row-affecting constraint that the
 * auto-joiner cannot derive from normal relationship metadata.
 *
 * The package verifies that a complex relationship receives a non-empty
 * description, but it cannot prove that the description is equivalent to
 * arbitrary Eloquent query state.
 */
final class AutoJoinRelation
{
    /**
     * Constraint target for the joined related table.
     *
     * @var string
     */
    private const TARGET_RELATED = 'related';

    /**
     * Constraint target for the table owning the relationship method.
     *
     * @var string
     */
    private const TARGET_PARENT = 'parent';

    /**
     * Constraint target for a BelongsToMany pivot table.
     *
     * @var string
     */
    private const TARGET_PIVOT = 'pivot';

    /**
     * Normalized constraints supplied by the model.
     *
     * @var list<
     *     array{
     *         target:string,
     *         type:'basic',
     *         column:string,
     *         operator:string,
     *         value:mixed
     *     }|array{
     *         target:string,
     *         type:'null',
     *         column:string,
     *         not:bool
     *     }
     * >
     */
    private array $constraints = [];

    /**
     * Create a model-facing description for an Eloquent relationship.
     *
     * @param  Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $relation
     */
    public function __construct(private readonly Relation $relation)
    {
    }

    /**
     * Get the original unconstrained Eloquent relationship.
     *
     * Models may inspect its standard metadata when deciding how to describe
     * additional constraints.
     *
     * @return Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>
     */
    public function relation(): Relation
    {
        return $this->relation;
    }

    /**
     * Add a value constraint targeting the joined related table.
     *
     * Two arguments use equality:
     * `whereRelated('status', 'active')`.
     * Three arguments specify an operator:
     * `whereRelated('priority', '>=', 10)`.
     *
     * @param  string  $column
     * @param  mixed   $operatorOrValue
     * @param  mixed   $value
     * @return $this
     *
     * @throws InvalidArgumentException If the column or operator is invalid.
     */
    public function whereRelated(
        string $column,
        mixed $operatorOrValue,
        mixed $value = null
    ): self {
        return $this->where(
            self::TARGET_RELATED,
            $column,
            $operatorOrValue,
            $value,
            func_num_args()
        );
    }

    /**
     * Add a value constraint targeting the relationship's parent table.
     *
     * The method accepts the same two- or three-argument forms as
     * whereRelated().
     *
     * @param  string  $column
     * @param  mixed   $operatorOrValue
     * @param  mixed   $value
     * @return $this
     *
     * @throws InvalidArgumentException If the column or operator is invalid.
     */
    public function whereParent(
        string $column,
        mixed $operatorOrValue,
        mixed $value = null
    ): self {
        return $this->where(
            self::TARGET_PARENT,
            $column,
            $operatorOrValue,
            $value,
            func_num_args()
        );
    }

    /**
     * Add a value constraint targeting a BelongsToMany pivot table.
     *
     * The method accepts the same two- or three-argument forms as
     * whereRelated(). Using a pivot constraint with a non-pivot relationship
     * is rejected when the join is compiled.
     *
     * @param  string  $column
     * @param  mixed   $operatorOrValue
     * @param  mixed   $value
     * @return $this
     *
     * @throws InvalidArgumentException If the column or operator is invalid.
     */
    public function wherePivot(
        string $column,
        mixed $operatorOrValue,
        mixed $value = null
    ): self {
        return $this->where(
            self::TARGET_PIVOT,
            $column,
            $operatorOrValue,
            $value,
            func_num_args()
        );
    }

    /**
     * Add an IS NULL or IS NOT NULL constraint for the related table.
     *
     * @param  string  $column
     * @param  bool    $not Use true for IS NOT NULL.
     * @return $this
     *
     * @throws InvalidArgumentException If the column is invalid.
     */
    public function whereRelatedNull(string $column, bool $not = false): self
    {
        return $this->whereNull(self::TARGET_RELATED, $column, $not);
    }

    /**
     * Add an IS NULL or IS NOT NULL constraint for the parent table.
     *
     * @param  string  $column
     * @param  bool    $not Use true for IS NOT NULL.
     * @return $this
     *
     * @throws InvalidArgumentException If the column is invalid.
     */
    public function whereParentNull(string $column, bool $not = false): self
    {
        return $this->whereNull(self::TARGET_PARENT, $column, $not);
    }

    /**
     * Add an IS NULL or IS NOT NULL constraint for a pivot table.
     *
     * @param  string  $column
     * @param  bool    $not Use true for IS NOT NULL.
     * @return $this
     *
     * @throws InvalidArgumentException If the column is invalid.
     */
    public function wherePivotNull(string $column, bool $not = false): self
    {
        return $this->whereNull(self::TARGET_PIVOT, $column, $not);
    }

    /**
     * Determine whether the model supplied any join constraints.
     *
     * @return bool
     */
    public function hasConstraints(): bool
    {
        return $this->constraints !== [];
    }

    /**
     * Package-internal normalized representation used by the join builder.
     *
     * @internal
     *
     * @return list<
     *     array{
     *         target:string,
     *         type:'basic',
     *         column:string,
     *         operator:string,
     *         value:mixed
     *     }|array{
     *         target:string,
     *         type:'null',
     *         column:string,
     *         not:bool
     *     }
     * >
     */
    public function constraints(): array
    {
        return $this->constraints;
    }

    /**
     * Add a normalized value constraint for the requested table target.
     *
     * @param  string  $target
     * @param  string  $column
     * @param  mixed   $operatorOrValue
     * @param  mixed   $value
     * @param  int     $argumentCount Number of arguments supplied to the
     *                                public fluent method.
     * @return $this
     *
     * @throws InvalidArgumentException If the column or operator is invalid.
     */
    private function where(
        string $target,
        string $column,
        mixed $operatorOrValue,
        mixed $value,
        int $argumentCount
    ): self {
        $column = $this->normalizeColumn($column);

        if ($argumentCount === 2) {
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            if (! is_string($operatorOrValue) || trim($operatorOrValue) === '') {
                throw new InvalidArgumentException(
                    'Auto-join relation operators must be non-empty strings.'
                );
            }

            $operator = trim($operatorOrValue);
        }

        $this->constraints[] = [
            'target' => $target,
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add a normalized null constraint for the requested table target.
     *
     * @param  string  $target
     * @param  string  $column
     * @param  bool    $not
     * @return $this
     *
     * @throws InvalidArgumentException If the column is invalid.
     */
    private function whereNull(string $target, string $column, bool $not): self
    {
        $this->constraints[] = [
            'target' => $target,
            'type' => 'null',
            'column' => $this->normalizeColumn($column),
            'not' => $not,
        ];

        return $this;
    }

    /**
     * Validate a model-provided column name.
     *
     * Table qualification is intentionally rejected because the fluent method
     * already identifies the table target and the package supplies its alias.
     *
     * @param  string  $column
     * @return non-empty-string
     *
     * @throws InvalidArgumentException If the column is empty or qualified.
     */
    private function normalizeColumn(string $column): string
    {
        $column = trim($column);

        if ($column === '' || str_contains($column, '.')) {
            throw new InvalidArgumentException(
                'Auto-join relation columns must be non-empty, unqualified names.'
            );
        }

        return $column;
    }
}
