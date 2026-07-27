<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Model\PathRequest;
use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;

/**
 * Class: Agent
 *
 * Test model used for validating auto-join behavior.
 *
 * This model participates in relationship graphs involving:
 * - users
 * - departments (direct access)
 * - groups (indirect access → departments)
 *
 * It also defines model-level auto-join paths used by the DSL,
 * such as:
 *
 * - model__status
 * - model__accessibleDepartments
 */
class Agent extends Model
{
    use AutoJoinTestTrait;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'agents';

    /**
     * Mass assignable attributes.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'user_id',
        'position',
        'flags',
    ];

    /**
     * Describe a model-defined auto-join path.
     *
     * This acts as the entry point for resolving `model__*` paths
     * into descriptor definitions understood by the auto-join compiler.
     *
     * @param  PathRequest  $request
     * @return ExpressionDescriptor
     *
     * @throws \RuntimeException If the test path is unsupported.
     */
    public static function describeAutoJoinPath(
        PathRequest $request
    ): ExpressionDescriptor {
        return match ($request->path) {
            'model__status' => ExpressionDescriptor::path('flags'),
            'model__accessibleDepartments__id__count' => ExpressionDescriptor::count(
                [
                    'departments.id',
                    'groups.departments.id',
                ],
                distinct: true
            ),
            default => throw new \RuntimeException(sprintf(
                'Unsupported test model path [%s].',
                $request->path
            )),
        };
    }

    /**
     * An agent belongs to a user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Direct departments the agent belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function departments()
    {
        return $this->belongsToMany(
            Department::class,
            'agent_department',
            'agent_id',
            'department_id'
        )->withPivot('assigned_at');
    }

    /**
     * Groups the agent belongs to.
     *
     * These provide indirect department access.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(
            Group::class,
            'agent_groups',
            'agent_id',
            'group_id'
        );
    }
}
