<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use Illuminate\Database\Eloquent\Model;
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
     * Each supported path delegates to a dedicated method to keep
     * this router small and maintainable.
     *
     * @param  string            $path
     * @param  array<int,string> $segments
     * @return array<string,mixed>
     */
    public static function describeAutoJoinPath(string $path, array $segments): array
    {
        return match ($path) {
            'status'                 => static::describeStatusPath($segments),
            'accessibleDepartments' => static::describeAccessibleDepartmentsPath($segments),
            default                 => parent::describeAutoJoinPath($path, $segments),
        };
    }

    /**
     * Describe the "status" model path.
     *
     * Maps logical status to the underlying flags column.
     *
     * @param  array<int,string> $segments
     * @return array<string,mixed>
     */
    protected static function describeStatusPath(array $segments): array
    {
        return [
            'type' => 'path',
            'path' => 'flags',
        ];
    }

    /**
     * Describe the "accessibleDepartments" model path.
     *
     * Combines:
     * - direct department membership
     * - group-based department access
     *
     * This enables queries like:
     *
     * - model__accessibleDepartments__id__count
     * - model__accessibleDepartments__id__in
     *
     * @param  array<int,string> $segments
     * @return array<string,mixed>
     */
    protected static function describeAccessibleDepartmentsPath(array $segments): array
    {
        return [
            'type'     => 'count',
            'paths'    => [
                'departments.id',
                'groups.departments.id',
            ],
            'distinct' => true,
        ];
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
