<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use AutoJoinTestTrait;

    protected $table = 'departments';

    protected $fillable = ['name', 'manager_id'];

    /**
     * A department's manager is an agent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function manager()
    {
        return $this->belongsTo(Agent::class, 'manager_id');
    }

    /**
     * A department belongs to many agents (via the pivot table).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'agent_department', 'department_id', 'agent_id')
                    ->withPivot('assigned_at');
    }

    /**
     * Get the groups assigned to this department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(
            Group::class,
            'group_departments',
            'department_id',
            'group_id'
        );
    }

    /**
     * A department may have many tickets.
     */
    // public function tickets()
    // {
    //     return $this->hasMany(Ticket::class, 'dept_id');
    // }
}
