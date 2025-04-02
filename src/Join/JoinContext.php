<?php

namespace protich\AutoJoinEloquent\Join;

use protich\AutoJoinEloquent\Join\JoinClauseInfo;
use Illuminate\Database\Eloquent\Model;

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
     * Constructor.
     *
     * @param JoinClauseInfo $joinInfo   The join clause information.
     * @param string         $chainKey   The cumulative join key (e.g., "agent__departments").
     * @param Model          $model      The current model instance.
     * @param string         $modelAlias The alias for the current model's table.
     */
    public function __construct(JoinClauseInfo $joinInfo, string $chainKey, Model $model, string $modelAlias)
    {
        $this->joinInfo = $joinInfo;
        $this->chainKey = $chainKey;
        $this->model = $model;
        $this->modelAlias = $modelAlias;
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
