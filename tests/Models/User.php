<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use AutoJoinTestTrait;

    protected $table = 'users';

    protected $fillable = ['name', 'phone', 'email'];

    /**
     * A user is associated with an agent.
     */
    public function agent()
    {
        return $this->hasOne(Agent::class);
    }
}
