<?php

namespace protich\AutoJoinEloquent\Tests\Traits;

use protich\AutoJoinEloquent\Traits\AutoJoinTrait;
use protich\AutoJoinEloquent\Tests\Seeder;

trait AutoJoinTestTrait
{
    use AutoJoinTrait;

    /**
     * Seed records into the model's table.
     *
     * This static method instantiates the model to retrieve its table name via getTable() and then
     * delegates the insertion of data to Seeder::seedTable(). If no custom data is provided, the Seeder
     * automatically loads the default seed data from the predictable seeder file.
     *
     * Example usage:
     *
     *     User::seed(); // Loads default seed data from the seeder file.
     *     User::seed([
     *         ['name' => 'Custom User', 'phone' => '000-000-0000', 'email' => 'custom@example.com'],
     *     ]);
     *
     * @param array<int|string, mixed> $data Optional array of records (each record is an associative array).
     * @return void
     */
    public static function seed(array $data = []): void
    {
        /** @phpstan-ignore-next-line */
        $table = (new static)->getTable();
        Seeder::seedTable($table, $data);
    }
}
