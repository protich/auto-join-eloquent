<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;

class Agent extends Model
{
    use AutoJoinTestTrait;

    protected $table = 'agents';

    protected $fillable = ['user_id', 'position', 'flags'];

    /**
     * Describe a model-defined auto-join path.
     *
     * @param  string            $path
     * @param  array<int,string> $segments
     * @return array<string,mixed>
     */
    public static function describeAutoJoinPath(string $path, array $segments): array
    {
        return match ($path) {
            'status' => [
                'type' => 'path',
                'path' => 'flags',
            ],
            default => parent::describeAutoJoinPath($path, $segments),
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
     * An agent belongs to many departments via the pivot table.
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
}
