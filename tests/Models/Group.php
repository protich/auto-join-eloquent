<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use protich\AutoJoinEloquent\Model\AutoJoinRelation;
use protich\AutoJoinEloquent\Tests\Models\Department;

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
class Group extends BaseModel
{
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
     * A deliberately undescribed constrained relation used by failure tests.
     *
     * @return HasMany<Group, $this>
     */
    public function namedChildren(): HasMany
    {
        $relation = $this->hasMany(Group::class, 'parent_id');
        $relation->where('groups.name', 'Escalations');

        return $relation;
    }

    /**
     * Describe constrained group relationships used by auto-join tests.
     *
     * @param  string  $name
     * @param  string  $path
     * @return AutoJoinRelation
     */
    public function describeAutoJoinRelation(
        string $name,
        string $path
    ): AutoJoinRelation {
        $description = parent::describeAutoJoinRelation($name, $path);

        return match ($name) {
            'qualifiedDepartments' => $description
                ->whereRelated('name', 'Support'),
            default => $description,
        };
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

    /**
     * A constrained department relation used inside nested count paths.
     *
     * @return BelongsToMany<Department, $this, \Illuminate\Database\Eloquent\Relations\Pivot, 'pivot'>
     */
    public function qualifiedDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'group_departments',
            'group_id',
            'department_id'
        )->where('departments.name', 'Support');
    }
}
