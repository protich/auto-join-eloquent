<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model
{
    use AutoJoinTestTrait;

    protected $table = 'users';

    protected $fillable = ['name', 'phone', 'email'];

    /**
     * A user is associated with an agent.
     * @return HasOne<Agent,$this>
     */
    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }
}
