<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use protich\AutoJoinEloquent\Model\AutoJoinRelation;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Model\PathRequest;

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
class Agent extends BaseModel
{
    /**
     * Calls made to the complex relationship description hook.
     *
     * @var list<array{name:string,path:string}>
     */
    public static array $autoJoinRelationDescriptions = [];

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
            'model__qualifiedDepartmentCount' => ExpressionDescriptor::count(
                [
                    'qualifiedDepartments.id',
                    'groups.qualifiedDepartments.id',
                ],
                distinct: true
            ),
            'model__mixedComplexCount' => ExpressionDescriptor::count(
                [
                    'assignedDepartments.id',
                    'departments.id',
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
     * Describe only relationships whose query contains extra constraints.
     *
     * @param  string  $name
     * @param  string  $path
     * @return AutoJoinRelation
     *
     */
    public function describeAutoJoinRelation(
        string $name,
        string $path
    ): AutoJoinRelation {
        $description = parent::describeAutoJoinRelation($name, $path);
        self::$autoJoinRelationDescriptions[] = compact('name', 'path');

        return match ($name) {
            'namedUser',
            'returnedNamedUser' => $description
                ->whereRelated('name', 'Alice'),
            'flaggedUser' => $description->whereParent('flags', 1),
            'userWithoutPhone' => $description
                ->whereRelatedNull('phone'),
            'userWithPhone' => $description
                ->whereRelatedNull('phone', not: true),
            'invalidPivotUser' => $description
                ->wherePivot('status', 'active'),
            'assignedDepartments' => $description->wherePivot(
                'assigned_at',
                '>=',
                '2025-01-01'
            ),
            'pendingDepartments' => $description
                ->wherePivotNull('assigned_at'),
            'qualifiedDepartments' => $description
                ->whereRelated('name', 'Support'),
            default => $description,
        };
    }

    /**
     * A constrained belongs-to relation used to verify model-described joins.
     *
     * @return BelongsTo<User, $this>
     */
    public function namedUser(): BelongsTo
    {
        $relation = $this->belongsTo(User::class, 'user_id');
        $relation->where('users.name', 'Alice');

        return $relation;
    }

    /**
     * A constrained relation whose hook returns a replacement description.
     *
     * @return BelongsTo<User, $this>
     */
    public function returnedNamedUser(): BelongsTo
    {
        $relation = $this->belongsTo(User::class, 'user_id');
        $relation->where('users.name', 'Alice');

        return $relation;
    }

    /**
     * A relation constrained by a column on the owning model.
     *
     * @return BelongsTo<User, $this>
     */
    public function flaggedUser(): BelongsTo
    {
        $relation = $this->belongsTo(User::class, 'user_id');
        $relation->where('agents.flags', 1);

        return $relation;
    }

    /**
     * A relation constrained to related users without a phone number.
     *
     * @return BelongsTo<User, $this>
     */
    public function userWithoutPhone(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->whereNull('users.phone');
    }

    /**
     * A relation constrained to related users with a phone number.
     *
     * @return BelongsTo<User, $this>
     */
    public function userWithPhone(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->whereNotNull('users.phone');
    }

    /**
     * A non-pivot relation deliberately described with a pivot constraint.
     *
     * @return BelongsTo<User, $this>
     */
    public function invalidPivotUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->where('users.name', 'Alice');
    }

    /**
     * A constrained pivot relation used to verify pivot descriptions.
     *
     * @return BelongsToMany<Department, $this, \Illuminate\Database\Eloquent\Relations\Pivot, 'pivot'>
     */
    public function assignedDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'agent_department',
            'agent_id',
            'department_id'
        )->wherePivot('assigned_at', '>=', '2025-01-01');
    }

    /**
     * An unsupported through relation used to verify explicit failures.
     *
     * @return HasManyThrough<Department, User, $this>
     */
    public function departmentsThroughUser(): HasManyThrough
    {
        return $this->hasManyThrough(
            Department::class,
            User::class,
            'id',
            'manager_id',
            'user_id',
            'id'
        );
    }

    /**
     * A pivot relationship constrained to rows without an assignment date.
     *
     * @return BelongsToMany<Department, $this, \Illuminate\Database\Eloquent\Relations\Pivot, 'pivot'>
     */
    public function pendingDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'agent_department',
            'agent_id',
            'department_id'
        )->wherePivotNull('assigned_at');
    }

    /**
     * A constrained department relation used inside count subqueries.
     *
     * @return BelongsToMany<Department, $this, \Illuminate\Database\Eloquent\Relations\Pivot, 'pivot'>
     */
    public function qualifiedDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'agent_department',
            'agent_id',
            'department_id'
        )->where('departments.name', 'Support');
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
