<?php

namespace protich\AutoJoinEloquent\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use protich\AutoJoinEloquent\Tests\Seeders\AbstractSeederFactory;

/**
 * Class Seeder
 *
 * Handles seeding of database tables based on a provided schema.
 *
 * This class reads a schema file (which defines the tables and optional factory)
 * and uses static seeding files or an AbstractSeederFactory instance to seed data.
 *
 * @package protich\AutoJoinEloquent\Tests
 */
class Seeder
{
    /**
     * Base directory path where schema and seeders are located.
     *
     * @var string
     */
    protected $basePath;

    /**
     * List of tables defined in the schema file.
     *
     * @var array<string>
     */
    protected $tables;

    /**
     * Optional schema factory value used for advanced seeding.
     *
     * @var mixed|null
     */
    protected $schemaFactory;

    /**
     * Seeder constructor.
     *
     * Initializes the seeder by setting the base path and loading the schema file.
     *
     * @param string $basePath Base path for the schema and seeders.
     * @throws Exception If the schema file is not found or is invalid.
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $schemaFile = $this->basePath . 'schemas.php';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found at: {$schemaFile}");
        }

        /** @var array<string, array<string>> $schemaData */
        $schemaData = require $schemaFile;
        if (!isset($schemaData['tables']) || !is_array($schemaData['tables'])) { // @phpstan-ignore-line
            throw new Exception("No 'tables' key found in schema file.");
        }

        $this->tables = $schemaData['tables'];
        $this->schemaFactory = $schemaData['factory'] ?? null;
    }

    /**
     * Get the path to the migrations directory.
     *
     * @return string Full path to the migrations directory.
     */
    public function getMigrationsPath(): string
    {
        return $this->basePath . 'migrations';
    }

    /**
     * Retrieve seed data for a specific table.
     *
     * @param string $table The name of the table.
     * @return array<string|int, mixed> An array of seed records.
     */
    protected function getSeedData(string $table): array
    {
        return self::loadSeedData($table, $this->basePath);
    }

    /**
     * Get the list of tables defined in the schema.
     *
     * @return array<string> Array of table names.
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * Seed all tables defined in the schema.
     *
     * If an AbstractSeederFactory instance is provided, delegate seeding to that factory.
     * Otherwise, fall back to the default seeding logic by seeding each table using seed
     * data loaded from the corresponding seeder file.
     *
     * @param AbstractSeederFactory|null $factory Optional seeder factory instance.
     * @return void
     */
    public function seedAll(?AbstractSeederFactory $factory = null): void
    {
        if ($factory !== null) {
            // Delegate seeding to the provided factory instance.
            $factory->seedData();
            return;
        }

        // No seeder factory provided; use default seeding for each table.
        foreach ($this->tables as $table) {
            self::seedTable($table, $this->getSeedData($table));
        }
    }

    /**
     * Check if the specified tables exist in the database.
     *
     * @param array<string> $tables Optional array of table names to check.
     * @throws Exception If any expected table does not exist.
     */
    public function checkTables(array $tables = []): void
    {
        if (empty($tables)) {
            $tables = $this->tables;
        }

        foreach ($tables as $table) {
            if (!Capsule::schema()->hasTable($table)) {
                throw new Exception("Expected table '{$table}' was not created. Check your migrations.");
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
     * @param array<string|int, mixed> $records Optional array of records (each record is an associative array).
     * @return void
     */
    public static function seedTable(string $table, array $records = []): void
    {
        $db = Capsule::connection();
        foreach ($records as $record) {
            /** @var array<string|int, mixed> $record */
            $db->table($table)->insert($record);
        }
    }

    /**
     * Load seed data for a given table from a predictable seeder file.
     *
     * Expects the seeder file to be named using the pattern:
     *    {table}_table.php (all lowercase)
     * and to reside in the "seeders" folder under the given base path.
     *
     * @param string $table    The table name.
     * @param string $basePath The base path for the schema.
     * @return array<string|int, mixed> The array of seed records.
     * @throws Exception if the seeder file does not exist.
     */
    public static function loadSeedData(string $table, string $basePath): array
    {
        $file = rtrim($basePath, DIRECTORY_SEPARATOR)
              . DIRECTORY_SEPARATOR . 'seeders'
              . DIRECTORY_SEPARATOR . strtolower($table) . '_table.php';
        if (!file_exists($file)) {
            throw new Exception("Seeder file not found: {$file}");
        }
        return require $file; // @phpstan-ignore-line
    }
}
