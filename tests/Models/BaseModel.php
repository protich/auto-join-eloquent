<?php

namespace protich\AutoJoinEloquent\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Tests\Traits\AutoJoinTestTrait;

/**
 * Base model for the auto-join test graph.
 *
 * @method static AutoJoinQueryBuilder query()
 */
abstract class BaseModel extends Model
{
    use AutoJoinTestTrait;
}
