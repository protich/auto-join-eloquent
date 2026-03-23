<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;

/**
 * Class: Group
 *
 * Test model representing a group in the auto-join test graph.
 *
 * Relationships:
 * - may belong to a parent group
 * - may have child groups
 * - belongs to many agents
 * - belongs to many departments
 */
class Group extends Model
{
    use AutoJoinTestTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'parent_id',
    ];

    /**
     * Get the parent group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    /**
     * Get the child groups.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    /**
     * Get the agents assigned to this group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function agents()
    {
        return $this->belongsToMany(
            Agent::class,
            'agent_groups',
            'group_id',
            'agent_id'
        );
    }

    /**
     * Get the departments assigned to this group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function departments()
    {
        return $this->belongsToMany(
            Department::class,
            'group_departments',
            'group_id',
            'department_id'
        );
    }
}
