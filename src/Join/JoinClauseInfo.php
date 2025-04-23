<?php

namespace protich\AutoJoinEloquent\Join;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;

use  protich\AutoJoinEloquent\Join\JoinContext;

class JoinClauseInfo
{
    /**
     * The Eloquent relationship instance.
     *
     * @var \Illuminate\Database\Eloquent\Relations\Relation
     */
    protected Relation $relation;

    /**
     * The join type (e.g., 'left' or 'inner').
     *
     * @var string
     */
    protected string $joinType;

    /**
     * Create a new JoinClauseInfo instance.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $relation
     * @param  string  $joinType  The join type to use (defaults to 'left').
     */
    public function __construct(Relation $relation, string $joinType = 'left')
    {
        $this->relation = $relation;
        $this->joinType = $joinType;
    }


    /**
     * Get the join type.
     *
     * @return string
     */
    public function getJoinType(): string
    {
        return $this->joinType;
    }

    /**
     * Check if the relationship uses a pivot table.
     *
     * @return bool
     */
    public function hasPivotTable(): bool
    {
        return (bool) $this->getPivotTable();
    }

    /**
     * Get the pivot table name if the relationship uses a pivot table.
     *
     * @return string|null
     */
    public function getPivotTable(): ?string
    {
        if ($this->relation instanceof BelongsToMany) {
            return $this->relation->getTable();
        }
        return null;
    }

    /**
     * Get the fully qualified foreign pivot key for a BelongsToMany relationship.
     *
     * This method returns the foreign pivot key (i.e. the key on the pivot table that refers to the parent model)
     * if the current relationship is an instance of BelongsToMany.
     *
     * @return string The qualified foreign pivot key.
     *
     * @throws \Exception If the relationship is not an instance of BelongsToMany.
     */
    public function getForeignPivotKey(): string
    {
        if ($this->relation instanceof BelongsToMany) {
            return $this->relation->getQualifiedForeignPivotKeyName();
        }
        throw new \Exception("The relationship is not an instance of BelongsToMany.");
    }

    /**
     * Get the fully qualified related pivot key for a BelongsToMany relationship.
     *
     * This method returns the related pivot key (i.e. the key on the pivot table that refers to the related model)
     * if the current relationship is an instance of BelongsToMany.
     *
     * @return string The qualified related pivot key.
     *
     * @throws \Exception If the relationship is not an instance of BelongsToMany.
     */
    public function getRelatedPivotKey(): string
    {
        if ($this->relation instanceof BelongsToMany) {
            return $this->relation->getQualifiedRelatedPivotKeyName();
        }
        throw new \Exception("The relationship is not an instance of BelongsToMany.");
    }

    /**
     * Get the related model instance.
     *
     * This method retrieves the related model from the current relationship.
     * It is useful when constructing joins or referencing properties of the related model.
     *
     * @return \Illuminate\Database\Eloquent\Model The related model instance.
     */
    public function getRelatedModel(): Model
    {
        return $this->relation->getRelated();
    }

    /**
     * Get the table name for the related model.
     *
     * This method retrieves the table name of the related model from the current relationship.
     * It is useful when constructing joins or when you need to reference the related table in queries.
     *
     * @return string The name of the related model's table.
     */
    public function getRelatedTable(): string
    {
        return $this->getRelatedModel()->getTable();
    }

    /**
     * Get the join method corresponding to the join type.
     *
     * For example, 'inner' returns 'join', and 'left' returns 'leftJoin'.
     *
     * @return string
     */
    public function getJoinMethod(): string
    {
        switch (strtolower($this->joinType)) {
            case 'inner':
                return 'join';
            case 'left':
            default:
                return 'leftJoin';
        }
    }

    /**
     * Get the fully qualified keys for the current relationship.
     *
     * This method returns an associative array with keys 'foreign' and 'owner',
     * representing the fully qualified foreign key and owner key respectively.
     *
     * @return array{foreign: string, owner: string}
     *
     * @throws \Exception If the relationship type is unsupported.
     */
    public function getQualifiedKeys(): array
    {
        $relation = $this->relation;
        switch (true) {
            case $relation instanceof BelongsTo:
                return [
                    'foreign' => $relation->getQualifiedForeignKeyName(),
                    'owner'   => $relation->getQualifiedOwnerKeyName(),
                ];
            case $relation instanceof HasOne:
            case $relation instanceof HasMany:
                return [
                    'foreign' => $relation->getQualifiedForeignKeyName(),
                    'owner'   => $relation->getQualifiedParentKeyName(),
                ];
            case $relation instanceof BelongsToMany:
                return [
                    'foreign' => $relation->getQualifiedForeignPivotKeyName(),
                    'owner'   => $relation->getQualifiedRelatedPivotKeyName(),
                ];
            default:
                throw new \Exception("Unsupported relationship type: " . get_class($relation));
        }
    }

    /**
     * Build key expressions for the join condition for non-pivot relationships.
     *
     * This method uses the fully qualified keys (obtained via getQualifiedKeys())
     * from the relationship and reassembles them with the provided aliases.
     * For a BelongsTo relationship, the base model (child) holds the foreign key, so the base alias is used for the foreign key
     * and the join alias for the owner key. For HasOne/HasMany relationships, the foreign key is on the joined table,
     * so the join alias is used for the foreign key and the base alias for the owner key.
     *
     * @param \Illuminate\Database\Query\Grammars\Grammar $grammar   The query grammar instance.
     * @param string                                      $baseAlias The alias for the base model's table.
     * @param string                                      $joinAlias The alias for the joined (related) table.
     * @return array{foreign: Expression, owner: Expression}
     *
     * @throws \Exception If the relationship is an instance of BelongsToMany or if key formats are invalid.
     */
    public function getKeyExpressions(Grammar $grammar, string $baseAlias, string $joinAlias): array
    {
        // BelongsToMany relationships require pivot join methods.
        if ($this->relation instanceof BelongsToMany) {
            throw new \Exception("BelongsToMany relationships require pivot join methods. Use getPivotKeyExpressions() and getPivotRelatedKeyExpressions() instead.");
        }

        // Retrieve the fully qualified keys from the relation as a named array.
        $keys = $this->getQualifiedKeys();
        // Split the qualified keys to extract the column names.
        $foreignParts = explode('.', $keys['foreign']); // e.g., "table.foreign_field"
        $ownerParts   = explode('.', $keys['owner']);   // e.g., "other_table.owner_field"
        if (count($foreignParts) !== 2 || count($ownerParts) !== 2) {
            throw new \Exception("Invalid key format. Expected fully qualified keys with a single dot.");
        }
        $foreignColumn = end($foreignParts);
        $ownerColumn   = end($ownerParts);
        if ($this->relation instanceof BelongsTo) {
            // For belongsTo, the base model holds the foreign key.
            $foreignExpr = new Expression(
                sprintf('%s.%s', $grammar->wrap($baseAlias), $grammar->wrap($foreignColumn))
            );
            $ownerExpr = new Expression(
                sprintf('%s.%s', $grammar->wrap($joinAlias), $grammar->wrap($ownerColumn))
            );
        } else {
            // For hasOne/hasMany, the joined (related) model holds the foreign key.
            $foreignExpr = new Expression(
                sprintf('%s.%s', $grammar->wrap($joinAlias), $grammar->wrap($foreignColumn))
            );
            $ownerExpr = new Expression(
                sprintf('%s.%s', $grammar->wrap($baseAlias), $grammar->wrap($ownerColumn))
            );
        }

        return [
            'foreign' => $foreignExpr,
            'owner'   => $ownerExpr,
        ];
    }

    /**
     * Build key expressions for the join between the base model and the pivot table.
     *
     * In a BelongsToMany relationship, the base model's primary key (using the base alias)
     * is matched against the pivot table's foreign key that references the base model (using the pivot alias).
     *
     * @param Grammar $grammar   The query grammar instance.
     * @param string  $baseAlias The alias for the base model's table.
     * @param string  $pivotAlias The alias for the pivot table.
     * @return array{base: Expression, pivot: Expression}
     *
     * @throws \Exception If the foreign pivot key format is invalid.
     */
    public function getPivotKeyExpressions(Grammar $grammar, string $baseAlias, string $pivotAlias): array
    {
        // Retrieve the primary key from the base model.
        $baseKey = $this->relation->getParent()->getKeyName();

        // Get the fully qualified foreign pivot key (e.g., "pivotTable.agent_id").
        $foreignPivotKey = $this->getForeignPivotKey();
        $parts = explode('.', $foreignPivotKey);
        if (count($parts) !== 2) {
            throw new \Exception("Foreign pivot key format is invalid: " . $foreignPivotKey);
        }
        $field = end($parts);

        $baseExpr = new Expression(
            sprintf('%s.%s', $grammar->wrap($baseAlias), $grammar->wrap($baseKey))
        );
        $pivotExpr = new Expression(
            sprintf('%s.%s', $grammar->wrap($pivotAlias), $grammar->wrap($field))
        );

        return [
            'base'  => $baseExpr,
            'pivot' => $pivotExpr,
        ];
    }

    /**
     * Build key expressions for the join between the pivot table and the related model.
     *
     * In a BelongsToMany relationship, the pivot table holds the foreign key referencing the related model.
     * This method returns the expression for the pivot table's key (using the pivot alias)
     * and the related model's primary key (using the related alias).
     *
     * @param Grammar $grammar     The query grammar instance.
     * @param string  $pivotAlias  The alias for the pivot table.
     * @param string  $relatedAlias The alias for the related model's table.
     * @return array{pivot: Expression, related: Expression}
     *
     * @throws \Exception If the related pivot key format is invalid.
     */
    public function getPivotRelatedKeyExpressions(Grammar $grammar, string $pivotAlias, string $relatedAlias): array
    {
        // Get the fully qualified related pivot key (e.g., "pivotTable.department_id").
        $relatedPivotKey = $this->getRelatedPivotKey();
        $parts = explode('.', $relatedPivotKey);
        if (count($parts) !== 2) {
            throw new \Exception("Related pivot key format is invalid: " . $relatedPivotKey);
        }
        $field = end($parts);

        // Retrieve the primary key from the related model.
        $relatedKey = $this->relation->getRelated()->getKeyName();

        $pivotExpr = new Expression(
            sprintf('%s.%s', $grammar->wrap($pivotAlias), $grammar->wrap($field))
        );
        $relatedExpr = new Expression(
            sprintf('%s.%s', $grammar->wrap($relatedAlias), $grammar->wrap($relatedKey))
        );

        return [
            'pivot'   => $pivotExpr,
            'related' => $relatedExpr,
        ];
    }

    /**
     * Get additional join conditions from the relationship's query.
     *
     * This method examines the "wheres" property of the relationship’s query builder
     * and extracts simple "Basic" conditions.
     *
     * @return list<array{left: string, operator: string, right: string}> An array of conditions, each as an associative array with keys 'left', 'operator', and 'right'.
     */
    public function getConditions(): array
    {
        $conditions = [];
        // Get the underlying Query\Builder from the Eloquent builder.
        /** @var array<string, array<string, string>> $wheres */
        $wheres = $this->relation->getQuery()->getQuery()->wheres;

        foreach ($wheres as $where) {
            if (isset($where['type']) && $where['type'] === 'Basic') {
                $conditions[] = [
                    'left'     => $where['column'],
                    'operator' => $where['operator'],
                    'right'    => $where['value']
                ];
            }
        }

        return $conditions;
    }

    /**
     * Build join condition expressions from additional conditions using the given grammar.
     *
     * This method retrieves the additional join conditions (as defined in the relationship's query)
     * and converts each condition into an array containing Expression objects for 'left' and 'right',
     * along with the operator.
     *
     * @param \Illuminate\Database\Query\Grammars\Grammar $grammar The query grammar instance.
     * @return list<array{left: string, operator: string, right: string}> An array of conditions, each as an associative array with keys 'left', 'operator', and 'right'.
     */
    public function getConditionsExpressions(Grammar $grammar): array
    {
        $conditionsExpressions = [];
        foreach ($this->getConditions() as $condition) {
            $conditionsExpressions[] = [
                'left'     => new Expression($grammar->wrap($condition['left'])),
                'operator' => $condition['operator'],
                'right'    => new Expression($grammar->wrap($condition['right']))
            ];
        }
        return $conditionsExpressions;
    }


    /**
     * For debugging: Return a string representation of the join clause information.
     *
     * @return string
     */
    public function __toString(): string
    {
        $conds = empty($this->getConditions()) ? 'none' : json_encode($this->getConditions());
        $qualifiedKeys = $this->getQualifiedKeys();
        return sprintf(
            "JoinType: %s, ForeignKey: %s, OwnerKey: %s, HasPivotTable: %s, PivotTable: %s, Conditions: %s",
            $this->joinType,
            $qualifiedKeys['foreign'],
            $qualifiedKeys['owner'],
            $this->hasPivotTable() ? 'true' : 'false',
            $this->getPivotTable() ?? 'none',
            $conds
        );
    }

    /**
     * Create a new JoinClauseInfo instance from a relationship.
     *
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param string $joinType  The join type to use (defaults to 'left').
     * @return self
     *
     * @throws \Exception If the relationship type is unsupported.
     */
    public static function createFromRelation(Relation $relation, string $joinType = 'left'): self
    {
        return new self($relation, $joinType);
    }

    /**
     * Build a JoinContext instance for the given relationship.
     *
     * This method creates a new JoinClauseInfo instance (using createFromRelation())
     * and then uses it to construct a JoinContext value object that encapsulates the join details,
     * the cumulative chain key, the current (base) model, and its current alias.
     *
     * @param Relation $relation The Eloquent relationship instance.
     * @param string   $chainKey The cumulative join key for the relationship chain.
     * @param Model    $currentModel The current base model instance.
     * @param string   $currentAlias The current alias for the base table.
     * @param string   $joinType The default join type to use (optional, defaults to 'left').
     * @return JoinContext
     *
     * @throws \Exception If the relationship type is unsupported.
     */
    public static function buildJoinContext(Relation $relation, string $chainKey, Model $currentModel, string $currentAlias, string $joinType = 'left'): JoinContext
    {
        // Create a JoinClauseInfo instance from the relation and join type.
        $joinInfo = self::createFromRelation($relation, $joinType);
        // Build and return the JoinContext value object.
        return new JoinContext($joinInfo, $chainKey, $currentModel, $currentAlias);
    }
}
