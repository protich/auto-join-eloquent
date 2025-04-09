<?php

namespace protich\AutoJoinEloquent\Tests\Seeders;

use protich\AutoJoinEloquent\Tests\Contracts\SeederFactoryInterface;

/**
 * Class AbstractSeederFactory
 *
 * Abstract base class for seeder factories. Concrete classes must implement the seedData() method.
 *
 * @package protich\AutoJoinEloquent\Tests\Seeders
 */
abstract class AbstractSeederFactory implements SeederFactoryInterface
{
    /**
     * Seed the database with sample data.
     *
     * Concrete implementations must provide their own seeding logic by implementing this method.
     *
     * @return void
     */
    abstract public function seedData(): void;
}
