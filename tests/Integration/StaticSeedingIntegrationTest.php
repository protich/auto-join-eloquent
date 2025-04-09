<?php

namespace protich\AutoJoinEloquent\Tests\Integration;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Seeders\AbstractSeederFactory;

/**
 * Class StaticSeedingIntegrationTest
 *
 * Verifies that the seeding of schemas works correctly using static seeding files.
 * This is achieved by returning null from getSeederFactory() so that the seeder falls back
 * to the default static seeding mechanism.
 *
 * @package protich\AutoJoinEloquent\Tests\Integration
 */
class StaticSeedingIntegrationTest extends AutoJoinTestCase
{
    /**
     * Override getSeederFactory() to return null, forcing use of static seeding files.
     *
     * @return AbstractSeederFactory|null
     */
    protected function getSeederFactory(): ?AbstractSeederFactory
    {
        return null;
    }

    /**
     * Test that all expected tables exist in the database using static seeding files.
     *
     * Optionally outputs the contents of each table if debugging is enabled.
     *
     * @return void
     */
    public function testStaticSeeding(): void
    {
        $this->assertTablesNonEmpty();
    }
}
