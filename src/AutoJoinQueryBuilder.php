<?php

namespace protich\AutoJoinEloquent;

use protich\AutoJoinEloquent\Compilers\QueryCompiler;
use protich\AutoJoinEloquent\Join\JoinContext;
use protich\AutoJoinEloquent\Join\JoinClauseInfo;
use protich\AutoJoinEloquent\Join\JoinAliasManager;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Log;

class AutoJoinQueryBuilder extends EloquentBuilder
{

    /**
     * Cache for the base model's columns.
     *
     * @var array<mixed>|null
     */
    protected $baseModelColumns = null;

    /**
     * Option to use simple sequential aliases.
     *
     * @var bool
     */
    protected $useSimpleAliases = true;


    /**
     * Array of relationship chain keys that have been auto-joined.
     *
     * This property tracks which joins have been applied to avoid duplicate join clauses.
     *
     * @var array<string>
     */
    protected array $autoJoinedRelations = [];


    /**
     * The default join type (e.g., 'left' or 'inner').
     *
     * @var string
     */
    protected $defaultJoinType = 'left';

    /**
     * Debug flag to enable SQL output.
     *
     * @var bool
     */
    public $debugOutput = false;

    /**
     * The array to store debug information for join operations.
     *
     * @var array<int|string, mixed>
     */
    protected array $debugLog = [];

    /**
     * The join alias manager instance.
     *
     * @var JoinAliasManager
     */
    protected JoinAliasManager $aliasManager;

    /**
     * The base model for the query builder.
     *
     * This property holds the instance of the model that the query builder is operating on.
     * It provides context for generating join clauses. If no base model is explicitly set,
     * the query builder will fall back to using the model returned by getModel().
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $baseModel;

    /**
     * Constructor.
     *
     * Initializes the AutoJoinQueryBuilder and creates a new JoinAliasManager.
     * The alias manager is responsible for handling alias mapping for relationships.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    public function __construct($query)
    {
        parent::__construct($query);
        $this->aliasManager = new JoinAliasManager($this->useSimpleAliases);
    }

    /**
     * Get the join alias manager.
     *
     * @return JoinAliasManager
     */
    public function getAliasManager(): JoinAliasManager
    {
        return $this->aliasManager;
    }

    /**
     * Set whether to use simple sequential aliases.
     *
     * This method provides an interface for traits and other code to configure
     * simple alias generation. It delegates the setting to the join alias manager.
     *
     * @param bool $useSimple
     * @return void
     */
    public function setUseSimpleAliases(bool $useSimple): void
    {
        $this->getAliasManager()->setUseSimpleAliases($useSimple);
    }


    /**
     * Set the default join type.
     *
     * @param string $type
     * @return void
     */
    public function setDefaultJoinType(string $type): void
    {
        $this->defaultJoinType = $type;
    }

    /**
     * Get the default join type.
     *
     * @return string
     */
    public function getDefaultJoinType(): string
    {
        return $this->defaultJoinType ?: 'left';
    }

    /**
     * Set the base model for the query builder.
     *
     * This method allows you to explicitly define the base model, providing context for
     * the auto join logic when generating join clauses.
     *
     * @param \Illuminate\Database\Eloquent\Model $baseModel The model instance to set as the base.
     * @return void
     */
    public function setBaseModel(Model $baseModel): void
    {
        $this->baseModel = $baseModel;
    }

    /**
     * Get the base model for the query builder.
     *
     * This method returns the base model that has been set. If no base model is explicitly set,
     * it falls back to using the model associated with the query builder via getModel().
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getBaseModel(): Model
    {
        return $this->baseModel ?: $this->getModel();
    }

    /**
     * Get the base alias for the model by delegating to the alias manager.
     *
     * This method uses the model's table name (via getModel()->getTable()) as the key and default.
     *
     * @return string The base alias used for auto-joining.
     */
    public function getBaseAlias(): string
    {
        return $this->getAliasManager()->resolveModelAlias(
            $this->getBaseModel(),
            $this->getBaseModel()->getTable(),
            $this->getBaseModel()->getTable()
        );
    }

    /**
     * Set the base alias by delegating to the alias manager.
     *
     * The alias is stored in the alias manager using the model's table name as the key.
     *
     * @param string $alias The alias to set.
     * @return void
     */
    public function setBaseAlias(string $alias): void
    {
        $this->getAliasManager()->setAlias($this->getBaseModel()->getTable(), $alias);
    }

    /**
     * Get the base table name for the current model.
     *
     * This method returns the table name of the model associated with the current query,
     * allowing you to use it for aliasing or other purposes.
     *
     * @return string
     */
    public function getBaseTable(): string
    {
        return $this->getBaseModel()->getTable();
    }

    /**
     * Resolve the join alias for a given relationship chain using the provided model.
     *
     * This method delegates alias resolution to the JoinAliasManager. The manager checks if
     * the model defines a custom alias (via a property like $joinAliases) and uses it if available.
     * Otherwise, it generates an alias (using simple sequential aliases if enabled) or uses the
     * provided default. The resolved alias is cached in the manager.
     *
     * @param Model  $model              The model instance.
     * @param string $relationshipChain  The normalized relationship chain (e.g., "agent__departments").
     * @param string|null $default       Optional default alias if none is defined.
     * @return string                    The resolved join alias.
     */
    public function resolveJoinAlias(Model $model, string $relationshipChain, ?string $default = null): string
    {
        return $this->getAliasManager()->resolveModelAlias($model, $relationshipChain, $default);
    }

    /**
     * Extract the base expression and alias from a raw expression.
     *
     * This method checks if the input contains an alias (using the "as" keyword).
     * If an alias is present, it returns an array with the base expression (without the alias)
     * and the alias. Otherwise, it returns the original expression and a null alias.
     *
     * @param string $expression The raw expression (e.g., "users as u" or "users").
     * @return array{expression: string, alias: ?string}
     */
    protected function parseAliasClause(string $expression): array
    {
        $alias = null;
        $expression = trim($expression);
        if (preg_match('/^(.*?)\s+as\s+(.*?)$/i', $expression, $matches)) {
            $expression = trim($matches[1]);
            $alias = trim($matches[2]);
        }
        return [
            'expression' => $expression,
            'alias' => $alias,
        ];
    }

    /**
     * Extract and return only the alias from a raw expression.
     *
     * This method delegates to parseAliasClause() and returns the alias if available,
     * otherwise it returns null.
     *
     * @param string $expression The raw expression.
     * @return string|null
     */
    protected function parseAlias(string $expression): ?string
    {
        $parsed = $this->parseAliasClause($expression);
        return $parsed['alias'] ?? null;
    }

    /**
     * Parse a relationship chain string into structured segments.
     *
     * Optionally, if a base table name is provided and the first segment matches,
     * then that segment is removed from the chain.
     *
     * For example, if the base table is "users" and the chain string is
     * "users__agent__departments|inner", the method will remove "users" and return:
     * [
     *   ['relation' => 'agent', 'join' => 'left'],
     *   ['relation' => 'departments', 'join' => 'inner']
     * ]
     *
     * @param string      $chainString   A chain string (e.g., "users__agent__departments|inner")
     * @param string|null $baseTableName Optional base table name to remove from the start of the chain.
     * @return array<int,array{relation: string, join: string}> An array of segments with keys 'relation' and 'join'.
     */
    protected function parseRelationshipChain(string $chainString, ?string $baseTableName = null): array
    {
        // If no segments were found, return early.
        if (empty($segments = array_filter(explode('__', $chainString)))) {
            return [];
        }

        // If a base table name is provided and the first segment matches it (case-insensitive), remove it.
        if ($baseTableName !== null && !strcasecmp($segments[0], $baseTableName)) {
            array_shift($segments);
        }

        $defaultJoinType = $this->getDefaultJoinType();
        return array_map(function ($segment) use($defaultJoinType) {
            $parts = explode('|', $segment, 2);
            return [
                'relation' => $parts[0],
                'join'     => $parts[1] ?? $defaultJoinType,
            ];
        }, $segments);
    }

    /**
     * Parse a column expression into its components: relationship chain, field, and alias.
     *
     * Handles dot-based and relationship-based expressions such as:
     * - 'agent__departments.name as dept_name' → field = 'name', alias = 'dept_name'
     * - 'agent__departments__manager' → field inferred via relationship, alias inferred if enabled
     * - 'status' → no chain, direct field
     *
     * If a dot is found, the expression is split into chain and field.
     * If not, and the expression contains a relationship marker (`__`), it is parsed as a relationship chain
     * and normalized to infer the field and possibly an alias.
     *
     * @param string $column The raw expression (e.g. 'user__agent.name as alias')
     * @param string|null $baseTable Optional base table name to strip from beginning of chain
     * @param bool $allowAutoAliasing Whether to allow alias inference when none is provided
     * @return array{chain: array<int, array{relation: string, join: string}>, field: string|null, alias: string|null}
     */
    protected function parseColumnChain(string $column, ?string $baseTable = null, bool $allowAutoAliasing = true): array
    {
        $parsed = $this->parseAliasClause($column);
        $expression = trim($parsed['expression']);
        $alias = $parsed['alias'];

        $field = $expression;
        $chain = [];

        $lastDotPos = strrpos($expression, '.');

        if ($lastDotPos !== false) {
            // Dot found → split into relationship chain and field
            $chainPart = substr($expression, 0, $lastDotPos);
            $field = substr($expression, $lastDotPos + 1);
            $chain = $this->parseRelationshipChain($chainPart, $baseTable);
        } elseif (str_contains($expression, '__')) {
            // No dot but has relationship markers → parse as chain, defer field inference
            $field = null;
            $chain = $this->parseRelationshipChain($expression, $baseTable);
        }

        return $this->normalizeChainParts([
            'chain' => $chain,
            'field' => $field,
            'alias' => $alias,
        ], $allowAutoAliasing);
    }

    /**
     * Normalize chain parts by inferring a field and possibly an alias if missing.
     *
     * This method is only triggered when a field is missing in the parsed expression.
     * - If the last segment is a valid relationship, its related model's primary key is used as the field.
     * - If the last segment is not a relationship, it is treated as a field and removed from the chain.
     * - If no alias is explicitly provided, and auto-aliasing is enabled, the alias is inferred from the chain.
     *
     * @param array{chain: array<int, array{relation: string, join: string}>, field: string|null, alias: string|null} $parts
     * @param bool $allowAutoAliasing Whether to infer an alias from the relationship path if none is provided
     * @return array{chain: array<int, array{relation: string, join: string}>, field: string|null, alias: string|null}
     */
    protected function normalizeChainParts(array $parts, bool $allowAutoAliasing = true): array
    {
        if (!empty($parts['field']) || empty($parts['chain'])) {
            return $parts;
        }

        $model = $this->getBaseModel();
        $chain = $parts['chain'];

        // Remove and inspect the last segment
        $final = array_pop($chain);

        // Traverse intermediate chain
        foreach ($chain as $step) {
            $relation = $step['relation'];

            if (!method_exists($model, $relation)) {
                return $parts;
            }

            $rel = $model->{$relation}();
            if (!($rel instanceof Relation)) {
                return $parts;
            }

            $model = $rel->getRelated();
        }

        $last = $final['relation'];

        // Last is not a relationship method → treat as field
        if (!method_exists($model, $last)) {
            return [
                'chain' => $chain,
                'field' => $last,
                'alias' => $parts['alias'],
            ];
        }

        $relation = $model->{$last}();
        if (!($relation instanceof Relation)) {
            return [
                'chain' => $chain,
                'field' => $last,
                'alias' => $parts['alias'],
            ];
        }

        // Valid relationship — re-append and infer field and alias
        $chain[] = $final;
        $field = $relation->getRelated()->getKeyName();

        return [
            'chain' => $chain,
            'field' => $field,
            'alias' => $parts['alias'] ?? ($allowAutoAliasing
            ?  implode('__', array_column($chain, 'relation'))
            : null),
        ];
    }

    /**
     * Resolve a column expression into a fully qualified SQL expression.
     *
     * This method parses a raw column expression using parseColumnChain(), which splits the expression
     * into a relationship chain, a terminal field, and an alias. If a chain is detected, the method applies
     * auto-join logic by delegating to resolveAutoJoinExpression(), generating the necessary JOIN clauses
     * and fully qualifying the column. If no chain is present, the field is resolved against the base model.
     * If the field matches a base model column, it is prefixed using the base alias (if applicable).
     *
     * Aliasing behavior:
     * - If `$alias` is explicitly provided, it takes precedence.
     * - If no alias is provided and the expression does not contain one, and the field is inferred
     *   (e.g., from a relationship-only expression like "agent__manager__user"), and `$allowAutoAliasing` is true,
     *   an alias may be automatically generated from the full relationship chain (e.g., "agent__manager__user").
     *
     * This ensures consistent and predictable column aliasing in result sets when fields are derived from relations.
     *
     * @param string $column The raw column expression (e.g., "agent.id as agent_id", "agent__manager__user").
     * @param string|null $alias Optional override for alias parsed from the expression.
     * @param bool $allowAutoAliasing Whether to allow auto-aliasing when the field is inferred.
     * @return \Illuminate\Database\Query\Expression The resolved SQL expression.
     */
    public function resolveColumnExpression( string $column,
        ?string $alias = null,
        bool $allowAutoAliasing = true): Expression
    {
        // Parse the column into components: chain, field, and default alias (with auto-aliasing logic)
        $parsed = $this->parseColumnChain($column, $this->getBaseModel()->getTable(), $allowAutoAliasing);

        // If a relationship chain is present, delegate to auto-join logic
        if (!empty($parsed['chain'])) {
            return $this->resolveAutoJoinExpression(
                $parsed['chain'],
                $parsed['field'],// @phpstan-ignore-line
                $alias ?? $parsed['alias']
            );
        }

        // No relationship chain: resolve directly on base model
        $fieldName = $parsed['field'];
        $fieldAlias = $alias ?? $parsed['alias'];

        // Add table alias if it's a field on the base model
        $tableAlias = $this->isBaseModelColumn($fieldName)// @phpstan-ignore-line
            ? $this->getBaseAlias()
            : null;

        return $this->buildColumnExpression($fieldName, $fieldAlias, $tableAlias);// @phpstan-ignore-line
    }

    /**
     * Resolve a column expression that requires auto-joining.
     *
     * Given a parsed relationship chain (from parseColumnChain()), this method iterates over each segment,
     * validates that the relationship exists using getValidRelation(), and then creates a JoinContext
     * (via JoinClauseInfo::buildJoinContext()) to delegate join processing.
     * If the join has not yet been applied, it processes the join (pivot or normal) via processAutoJoin().
     * Finally, it builds the fully qualified column expression using the alias from the final join.
     *
     * @param string[][] $chain The parsed relationship chain (an array of arrays with keys 'relation' and 'join').
     * @param string $fieldName The final field name to select.
     * @param string|null $fieldAlias An optional alias to use instead of any parsed alias.
     * @return \Illuminate\Database\Query\Expression The resolved column expression.
     * @throws \Exception If a relation method is missing or invalid.
     */
    public function resolveAutoJoinExpression(array $chain, string $fieldName, ?string $fieldAlias = null): Expression
    {
        // Initialize with the base model and its alias.
        $currentModel = $this->getBaseModel();
        $currentAlias = $this->getBaseAlias();
        $chainKeyParts = [];
        // Iterate through each segment in the chain.
        foreach ($chain as $item) {
            $relationName = $item['relation'];
            $joinType = $item['join'];
            $chainKeyParts[] = $relationName;
            $chainKey = implode('__', $chainKeyParts);

            // Validate that the current model's relation method exists and returns a valid relationship.
            $relation = $this->getValidRelation($currentModel, $relationName);

            // If this join has not yet been applied, process it.
            if (!in_array($chainKey, $this->autoJoinedRelations)) {
                // Build a join context object from the relation using JoinClauseInfo.
                $joinContext = JoinClauseInfo::buildJoinContext($relation,
                    $chainKey, $currentModel, $currentAlias, $joinType);
                // Set the relation name - useful for creating aliases
                $joinContext->setRelationName($relationName);
                // Process the join, which will delegate pivot joins if needed.
                $currentAlias = $this->processAutoJoin($joinContext);
                $currentModel = $relation->getRelated();
                $this->autoJoinedRelations[] = $chainKey;
            } else {
                // If already joined, update current model and alias from cache.
                $currentModel = $relation->getRelated();
                $currentAlias = $this->getAliasManager()->getAlias($chainKey, $currentAlias);
            }
        }

        // Build the final column expression using the resolved alias.
        return $this->buildColumnExpression($fieldName, $fieldAlias, $currentAlias);
    }

    /**
     * Process an auto join for a given relationship using the provided JoinContext.
     *
     * For a normal (non-pivot) relationship, this method resolves the join alias,
     * builds the join conditions (including any additional constraints), and applies
     * the join using the addJoin() helper (which handles tracking). The join alias for
     * the related table is then returned.
     *
     * If the join info indicates a pivot table (belongsToMany), it delegates to processPivotJoin().
     *
     * @param \protich\AutoJoinEloquent\Join\JoinContext $context The join context.
     * @return string The alias for the related table after the join is applied.
     */
    protected function processAutoJoin(JoinContext $context): string
    {
        // If the join info indicates a pivot table (belongsToMany), delegate to processPivotJoin.
        if ($context->hasPivotTable()) {
            return $this->processPivotJoin($context);
        }

        // Process a normal join.
        $joinInfo  = $context->getJoinInfo();
        $grammar   = $this->getQuery()->getGrammar();

        // Resolve join alias using the current model, chain key, and relation name.
        $joinAlias = $this->resolveJoinAlias(
            $context->getModel(),
            $context->getChainKey(),
            $context->getRelationName(),
        );

        $keyExpressions = $joinInfo->getKeyExpressions($grammar, $context->getModelAlias(), $joinAlias);
        // Build join conditions (primary join condition plus any additional conditions).
        $joinConditions = [
            [
                'left'     => $keyExpressions['foreign'],
                'operator' => '=',
                'right'    => $keyExpressions['owner']
            ]
        ];
        // Merge additional conditions from the relationship.
        $joinConditions = array_merge($joinConditions, $joinInfo->getConditionsExpressions($grammar));

        // Build the table expression for the related table.
        $relatedTable   = $joinInfo->getRelatedTable();
        $tableExpression = new Expression(sprintf('%s as %s',
            $grammar->wrapTable($relatedTable),
            $grammar->wrap($joinAlias)));
        $joinMethod = $joinInfo->getJoinMethod(); // e.g., 'leftJoin'
        //
        // Use add the join
        $this->addJoin(
            $joinMethod,
            $tableExpression,
            $joinConditions,
            [
                'table'    => $tableExpression->getValue($grammar),// @phpstan-ignore-line
                'chainKey' => $context->getChainKey(), // Tagged chain key can be modified as needed.
                'alias'    => $joinAlias,
            ]
        );

        // Return the alias for the related table.
        return $joinAlias;
    }

    /**
     * Process a pivot join for a BelongsToMany relationship using the provided JoinContext.
     *
     * Handles the two-stage join for pivot relationships:
     *  - Stage 1: Join the pivot table using the base model's primary key and the pivot table's foreign pivot key.
     *  - Stage 2: Join the related table using the pivot table's related pivot key and the related model's primary key.
     *
     * @param JoinContext $context The join context.
     * @return string The alias for the final related table.
     *
     * @throws \Exception If key expressions cannot be built.
     */
    protected function processPivotJoin(JoinContext $context): string
    {
        $grammar    = $this->getQuery()->getGrammar();
        $joinInfo   = $context->getJoinInfo();
        $model      = $context->getModel();
        $chainKey   = $context->getChainKey();
        $modelAlias = $context->getModelAlias();

        // ----------------------
        // Stage 1: Join the pivot table.
        // ----------------------
        /** @var string $pivotTable */
        $pivotTable    = $joinInfo->getPivotTable(); // Retrieves the pivot table name.
        $pivotChainKey = $chainKey . '_pivot';
        $pivotAlias    = $this->resolveJoinAlias($model, $pivotChainKey, $pivotTable);

        // Build key expressions for the join between base model and pivot table.
        $pivotKeyExpressions = $joinInfo->getPivotKeyExpressions($grammar, $modelAlias, $pivotAlias);
        // Primary join condition: base key = pivot foreign key.
        $pivotConditions = [
            [
                'left'     => $pivotKeyExpressions['base'],
                'operator' => '=',
                'right'    => $pivotKeyExpressions['pivot']
            ]
        ];
        // Merge additional pivot conditions, if any.
        $pivotConditions = array_merge($pivotConditions,
            $joinInfo->getConditionsExpressions($grammar));

        $pivotTableExpr = new Expression(sprintf('%s as %s',
            $grammar->wrapTable($pivotTable),
            $grammar->wrap($pivotAlias)));
        $joinMethod     = $joinInfo->getJoinMethod(); // e.g., 'leftJoin'
        $this->addJoin(
            $joinMethod,
            $pivotTableExpr,
            $pivotConditions,
            [
                'chainKey' => $pivotChainKey,
                'table'    => $pivotTableExpr->getValue($grammar),// @phpstan-ignore-line
                'alias'    => $pivotAlias
            ]
        );

        // ----------------------
        // Stage 2: Join the related table.
        // ----------------------
        $relatedModel    = $joinInfo->getRelatedModel();
        $relatedTable    = $relatedModel->getTable();
        $relatedChainKey = $chainKey . '_related';
        $relatedAlias    = $this->resolveJoinAlias($model, $relatedChainKey, $relatedTable);

        // Build key expressions for the join between pivot table and related table.
        $pivotRelatedKeyExpressions = $joinInfo->getPivotRelatedKeyExpressions($grammar, $pivotAlias, $relatedAlias);
        // Primary join condition: pivot key (for related model) = related model's primary key.
        $relatedConditions = [
            [
                'left'     => $pivotRelatedKeyExpressions['pivot'],
                'operator' => '=',
                'right'    => $pivotRelatedKeyExpressions['related']
            ]
        ];
        // Merge additional related conditions, if any.
        $relatedConditions = array_merge($relatedConditions,
            $joinInfo->getConditionsExpressions($grammar));

        $relatedTableExpr = new Expression(sprintf('%s as %s',
            $grammar->wrapTable($relatedTable),
            $grammar->wrap($relatedAlias)));
        $this->addJoin(
            $joinMethod,
            $relatedTableExpr,
            $relatedConditions,
            [
                'chainKey' => $relatedChainKey,
                'table'    => $relatedTableExpr->getValue($grammar),// @phpstan-ignore-line
                'alias'    => $relatedAlias
            ]
        );

        // We need to save the chainKey Alias without pivot context.
        $this->getAliasManager()->setAlias($chainKey, $relatedAlias);

        // Return the alias for the related table.
        return $relatedAlias;
    }

    /**
     * Add a join to the query with tracking.
     *
     * This method encapsulates the join logic by accepting the join method,
     * table expression, join conditions, and an optional context for tracking purposes.
     *
     * The context should include a 'chainKey' that is already tagged with the join stage
     * (e.g. "$chainKey . '_pivot'") and an 'alias' key.
     *
     * @param string     $joinMethod      The join method to use (e.g., 'leftJoin', 'join').
     * @param Expression $tableExpression The table expression (with alias) to join.
     * @param array<mixed>    $conditions      An array of conditions, each as an associative array
     *                                    with keys 'left', 'operator', and 'right'.
     * @param array<string, mixed>      $context         Optional context information for tracking purposes.
     * @return void
     */
    protected function addJoin(string $joinMethod, $tableExpression, array $conditions, array $context = []): void
    {
        // Retrieve the current query builder instance.
        $query = $this->getQuery();
        /** @param \Illuminate\Database\Query\JoinClause $join */
        $query->$joinMethod($tableExpression, function (JoinClause $join) use ($conditions, $joinMethod, $context) {
            // Apply each condition to the join.
            /** @var array<int, array{left: string, operator: string, right: string}> $conditions */
            foreach ($conditions as $condition) {
                $join->on($condition['left'], $condition['operator'], $condition['right']);
            }
            // Track this join operation.
            $this->onJoin($join, array_merge($context, [
                'conditions' => $conditions,
                'joinMethod' => $joinMethod
            ]));
        });
    }


    /**
     * Track join operations.
     *
     * This method is called every time a join is processed by addJoin().
     * It records debugging information, including the join conditions,
     * the provided context, and a timestamp.
     *
     * @param \Illuminate\Database\Query\JoinClause $join The join clause instance.
     * @param array<int|string, mixed> $context Additional context information (e.g., tagged chainKey and alias).
     * @return void
     */
    protected function onJoin($join, array $context = []): void
    {
        $this->debugLog[] = array_merge($context, [
            'timestamp'  => microtime(true)
        ]);
    }

    /**
     * Build an SQL expression for a column, with an optional alias and optional table alias.
     *
     * This method uses the query grammar to wrap the column name. If a table alias is provided,
     * the column is prefixed with the wrapped table alias and a dot. If a column alias is provided,
     * an "AS" clause is appended to the expression.
     *
     * @param string $columnName The name of the column/field.
     * @param string|null $columnAlias Optional alias for the column.
     * @param string|null $tableAlias Optional table alias; if provided, the column is prefixed with this alias.
     * @return \Illuminate\Database\Query\Expression The constructed SQL expression.
     */
    protected function buildColumnExpression(string $columnName, ?string $columnAlias = null, ?string $tableAlias = null)
    {
        $grammar = $this->getGrammar();

        // If a table alias is provided, prefix the column with it; otherwise, just wrap the column.
        if ($tableAlias !== null) {
            $expression = sprintf('%s.%s',
                $grammar->wrap($tableAlias),
                $grammar->wrap($columnName)
            );
        } else {
            $expression = $grammar->wrap($columnName);
        }

        // If a column alias is provided, append an alias clause.
        if ($columnAlias !== null) {
            $expression = sprintf('%s as %s', $expression, $grammar->wrap($columnAlias));
        }

        return new Expression($expression);
    }



    /**
     * Verify that the given model has a valid relationship method and return the relation instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The model instance.
     * @param string $relationName The name of the relationship method.
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     * @throws \Exception if the method does not exist or does not return a valid relationship.
     */
    protected function getValidRelation($model, string $relationName): Relation
    {
        // Ensure the current model has the method.
        if (!method_exists($model, $relationName)) {
            throw new \Exception("Method {$relationName} does not exist on " . get_class($model));
        }

        // Retrieve the relation instance.
        $relation = $model->$relationName();

        // Ensure that the returned value is a valid Eloquent relationship.
        if (!$relation instanceof Relation) {
            throw new \Exception("Method {$relationName} on " . get_class($model) . " is not a relationship.");
        }

        return $relation;
    }


    /**
     * Check if the given column exists on the base model's table.
     *
     * Retrieves and caches the column listing for the base model's table using the schema builder.
     *
     * @param string $column The column name to check.
     * @return bool          True if the column exists, false otherwise.
     */
    protected function isBaseModelColumn(string $column): bool
    {
        $model = $this->getBaseModel();
        if ($this->baseModelColumns === null) {
            $this->baseModelColumns = $model
                ->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($model->getTable());
        }
        return in_array($column, $this->baseModelColumns ?: [], true);
    }

    /**
     * Apply auto-join transformations to the given query.
     *
     * This method checks if the query's FROM clause already contains an alias using
     * parseAliasClause(). If an alias is found, it sets the base alias (and updates the
     * relation alias map using the parsed expression as the key). Then it rewrites the
     * FROM clause to enforce the base alias and delegates further query compilation to QueryCompiler.
     *
     * @param \Illuminate\Database\Query\Builder $query The query builder instance to transform.
     * @return void
     */
    public function autoJoinQuery(Query $query): void
    {
        $grammar = $this->getGrammar();
        $from = $query->from;

        // If the FROM clause is a string and contains an alias, extract
        // alias and table info.
        $info = $this->parseAliasClause($from);
        if ($info['alias'] !== null) {
            // NOTE: If the table part (i.e. $info['expression']) is customized,
            // it may result in invalid aliasing. To avoid inconsistencies, we
            // always use the model's table as the key for the base alias.
            $this->setBaseAlias($info['alias']);
        }

        // Rewrite the FROM clause with the base alias.
        $query->from = new Expression(sprintf(
            '%s as %s',
            $grammar->wrapTable($this->getBaseTable()),
            $grammar->wrap($this->getBaseAlias())
        ));

        // Delegate further query compilation to QueryCompiler.
        QueryCompiler::compile($this, $query);
    }

    /**
     * Retrieve the debug log containing details about each join operation.
     *
     * @return array<int|string, mixed>
     */
    public function getDebugLog(): array
    {
        return $this->debugLog;
    }

    /**
     * Override toSql() to log the compiled SQL query if debugging is enabled.
     *
     * This method calls the parent toSql() to generate the SQL query string.
     * If the debugOutput flag is true, it logs the SQL using Laravel's logging system
     * (via the Log facade at the debug level). Finally, it returns the generated SQL.
     *
     * @return string The compiled SQL query.
     */
    public function toSql(): string
    {
        $sql = parent::toSql();
        if ($this->debugOutput) {
            Log::debug('[AutoJoin Debug SQL] ' . $sql);
        }
        return $sql;
    }
}
