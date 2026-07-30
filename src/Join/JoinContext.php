<?php

namespace protich\AutoJoinEloquent\Join;

use Illuminate\Database\Eloquent\Model;
use protich\AutoJoinEloquent\Model\AutoJoinRelation;

/**
 * Class: JoinContext
 *
 * Carry the relationship metadata, current model state, aliases, and
 * model-described constraints required to compile one join in a path.
 */
class JoinContext
{
    /**
     * The join clause information.
     *
     * @var JoinClauseInfo
     */
    protected JoinClauseInfo $joinInfo;

    /**
     * The cumulative chain key for the relationship chain.
     *
     * @var string
     */
    protected string $chainKey;

    /**
     * The current model instance.
     *
     * @var Model
     */
    protected Model $model;

    /**
     * The alias for the current model's table.
     *
     * @var string
     */
    protected string $modelAlias;

    /**
     * The name of the relation for this join context.
     *
     * @var string
     */
    protected string $relationName;

    /**
     * Model-provided constraints for a complex relationship.
     *
     * The object is present for every join and remains empty for a normal
     * relationship.
     *
     * @var AutoJoinRelation
     */
    protected AutoJoinRelation $autoJoinRelation;

    /**
     * Constructor.
     *
     * @param JoinClauseInfo $joinInfo   The join clause information.
     * @param string         $chainKey   The cumulative join key (e.g., "agent__departments").
     * @param Model          $model      The current model instance.
     * @param string         $modelAlias The alias for the current model's table.
     * @param AutoJoinRelation $autoJoinRelation Model-described constraints,
     *                                           or an empty description for a
     *                                           normal relationship.
     */
    public function __construct(
        JoinClauseInfo $joinInfo,
        string $chainKey,
        Model $model,
        string $modelAlias,
        AutoJoinRelation $autoJoinRelation
    ) {
        $this->joinInfo = $joinInfo;
        $this->chainKey = $chainKey;
        $this->model = $model;
        $this->modelAlias = $modelAlias;
        $this->autoJoinRelation = $autoJoinRelation;
    }

    /**
     * Get the join clause information.
     *
     * @return JoinClauseInfo
     */
    public function getJoinInfo(): JoinClauseInfo
    {
        return $this->joinInfo;
    }

    /**
     * Get the cumulative chain key.
     *
     * @return string
     */
    public function getChainKey(): string
    {
        return $this->chainKey;
    }

    /**
     * Get the current model instance.
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the alias for the current model's table.
     *
     * @return string
     */
    public function getModelAlias(): string
    {
        return $this->modelAlias;
    }

    /**
     * Get the model-facing relationship description for this join.
     *
     * @return AutoJoinRelation
     */
    public function getAutoJoinRelation(): AutoJoinRelation
    {
        return $this->autoJoinRelation;
    }

    /**
     * Check if the join clause uses a pivot table.
     *
     * @return bool
     */
    public function hasPivotTable(): bool
    {
        return $this->joinInfo->hasPivotTable();
    }

    /**
     * Set the relation name.
     *
     * @param string $relationName The name of the relation.
     * @return self
     */
    public function setRelationName(string $relationName): self
    {
        $this->relationName = $relationName;
        return $this;
    }

    /**
     * Get the relation name.
     *
     * @return string The name of the relation.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }
}
