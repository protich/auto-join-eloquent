<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Join;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use protich\AutoJoinEloquent\Join\JoinClauseInfo;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/**
 * Verify validation of relationship types at the join metadata boundary.
 */
class JoinClauseInfoTest extends AutoJoinTestCase
{
    /**
     * Ensure unsupported through relationships fail before join compilation.
     *
     * @return void
     */
    public function test_unsupported_relationship_type_fails_clearly(): void
    {
        $relation = (new Agent)->departmentsThroughUser();

        $this->assertInstanceOf(HasManyThrough::class, $relation);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Unsupported auto-join relationship type '
                . '[Illuminate\Database\Eloquent\Relations\HasManyThrough]. '
                . 'Supported types are BelongsTo, HasOne, HasMany, and '
                . 'BelongsToMany.'
        );

        JoinClauseInfo::createFromRelation($relation);
    }
}
