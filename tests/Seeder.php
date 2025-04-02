<?php

namespace protich\AutoJoinEloquent\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;

class Seeder
{

    /**
     * Base path to the active schema folder.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Constructor.
     *
     * @param string $basePath The base path for the active schema (e.g., __DIR__ . '/Setup/default/').
     */
    public function __construct(string $basePath)
    {
        // Ensure the base path ends with a directory separator.
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the migrations path for the active schema.
     *
     * @return string
     */
    public function getMigrationsPath(): string
    {
        return $this->basePath . 'migrations';
    }


    /**
     * Load structured seed data for a given table.
     *
     * For a given table, this method uses the predictable naming convention
     * "{table}_table.php" (all lowercase) in the "seeders" folder under the base path.
     * For example, for the "users" table, it will load:
     *   {basePath}/seeders/users_table.php
     *
     * @param string $table The table name.
     * @return array An array of seed records.
     * @throws \Exception if the seeder file does not exist.
     */
    protected function getSeedData(string $table): array
    {
        return self::loadSeedData($table, $this->basePath);
    }

    /**
     * Seed all common data from the active schema.
     *
     * Reads the list of expected tables from the schema metadata file (schema.php)
     * and then loads seed data for each table from predictable seeder files.
     *
     * @return void
     * @throws \Exception if the schema file is missing or invalid.
     */
    public function seedAll(): void
    {
        $schemaFile = $this->basePath . 'schemas.php';
        if (!file_exists($schemaFile)) {
            throw new \Exception("Schema file not found at: {$schemaFile}");
        }
        $schemaData = require $schemaFile;
        if (!isset($schemaData['tables']) || !is_array($schemaData['tables'])) {
            throw new \Exception("No 'tables' key found in schema file.");
        }
        foreach ($schemaData['tables'] as $table) {
            self::seedTable($table, $this->getSeedData($table));
        }
    }

    /**
     * Verify that the specified tables exist in the database.
     *
     * If the $tables parameter is empty, this method loads the expected tables from the schema metadata
     * file (e.g. schema.php) in the active schema folder. Then, for each table, it checks whether the table
     * exists using Capsule's schema builder. If any expected table is missing, an exception is thrown.
     *
     * @param array $tables Optional array of table names to check. If empty, the list is loaded from the schema file.
     * @return void
     * @throws \Exception if an expected table is missing or if the schema file is not found/invalid.
     */
    public function checkTables(array $tables = []): void
    {
        if (empty($tables)) {
            $schemaFile = $this->basePath . 'schemas.php';
            if (!file_exists($schemaFile)) {
                throw new \Exception("Schema file not found at: {$schemaFile}");
            }
            $schemaData = require $schemaFile;
            if (!isset($schemaData['tables']) || !is_array($schemaData['tables'])) {
                throw new \Exception("No 'tables' key found in schema file.");
            }
            $tables = $schemaData['tables'];
        }

        foreach ($tables as $table) {
            if (!Capsule::schema()->hasTable($table)) {
                throw new \Exception("Expected table '{$table}' was not created. Check your migrations.");
            }
        }
    }


    /**
     * Seed a given table with records.
     *
     * If the provided $records array is empty, this method calls loadSeedData() to load
     * default seed data from the appropriate seeder file.
     *
     * @param string $table The table name.
     * @param array $records Optional array of records (each record is an associative array).
     * @return void
     */
    public static function seedTable(string $table, array $records = []): void
    {
        if (empty($records)) {
            $schema = env('TEST_SCHEMA', 'default');
            $basePath = __DIR__ . '/Setup/' . $schema . '/';
            $records = self::loadSeedData($table, $basePath);
        }

        $db = Capsule::connection();
        foreach ($records as $record) {
            $db->table($table)->insert($record);
        }
    }

    /**
     * Load seed data for a given table from a predictable seeder file.
     *
     * This method expects the seeder file to be named using the pattern:
     *    {table}_table.php (all lowercase)
     * and to reside in the "seeders" folder under the given base path.
     *
     * For example, if the base path is "/path/to/Setup/default/" and the table is "users",
     * it will look for:
     *    /path/to/Setup/default/seeders/users_table.php
     *
     * @param string $table    The table name.
     * @param string $basePath The base path for the schema (e.g., __DIR__ . '/Setup/default/').
     * @return array The array of seed records.
     * @throws \Exception if the seeder file does not exist.
     */
    public static function loadSeedData(string $table, string $basePath): array
    {
        $file = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'seeders' . DIRECTORY_SEPARATOR . strtolower($table) . '_table.php';
        if (!file_exists($file)) {
            throw new \Exception("Seeder file not found: {$file}");
        }
        return require $file;
    }
}
