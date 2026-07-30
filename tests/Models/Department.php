<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Model\PathRequest;

class Department extends Model
{
    use AutoJoinTestTrait;

    /**
     * Paths offered to this downstream model for description.
     *
     * @var list<string>
     */
    public static array $autoJoinPathRequests = [];

    protected $table = 'departments';

    protected $fillable = ['name', 'manager_id'];

    /**
     * Describe virtual expressions owned by the department model.
     *
     * @param  PathRequest $request
     * @return ExpressionDescriptor|null
     */
    public static function describeAutoJoinPath(
        PathRequest $request
    ): ?ExpressionDescriptor {
        self::$autoJoinPathRequests[] = $request->path;

        return match ($request->path) {
            'displayName' => ExpressionDescriptor::path('name'),
            default => null,
        };
    }

    /**
     * A department's manager is an agent.
     *
     * @return BelongsTo<Agent,$this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'manager_id');
    }

    /**
     * A department belongs to many agents (via the pivot table).
     *
     * @return BelongsToMany<
     *     Agent,
     *     $this,
     *     \Illuminate\Database\Eloquent\Relations\Pivot,
     *     'pivot'
     * >
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_department', 'department_id', 'agent_id')
                    ->withPivot('assigned_at');
    }

    /**
     * Get the groups assigned to this department.
     *
     * @return BelongsToMany<
     *     Group,
     *     $this,
     *     \Illuminate\Database\Eloquent\Relations\Pivot,
     *     'pivot'
     * >
     */
    public function groups(): BelongsToMany
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
