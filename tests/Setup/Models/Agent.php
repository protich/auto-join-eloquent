<?php
namespace protich\AutoJoinEloquent\Tests\Models;

use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use AutoJoinTestTrait;

    protected $table = 'agents';

    protected $fillable = ['user_id', 'position'];

    /**
     * An agent belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * An agent belongs to many departments (via the pivot table).
     */
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'agent_department', 'agent_id', 'department_id')
                    ->withPivot('assigned_at');
    }

    /**
     * An agent may have many tickets assigned.
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'agent_id');
    }
}
