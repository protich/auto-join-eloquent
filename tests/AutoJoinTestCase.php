<?php

namespace protich\AutoJoinEloquent\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use protich\AutoJoinEloquent\Tests\Seeders\DefaultSeederFactory;
use protich\AutoJoinEloquent\Tests\Seeders\AbstractSeederFactory;

abstract class AutoJoinTestCase extends TestCase
{
    /**
     * Flag to enable debug output for SQL queries.
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Test schema name.
     *
     * This property can be overridden by downstream tests if a different schema is desired.
     *
     * @var string|null
     */
    protected $schema = null;

    /**
     * Seeder instance used for schema verification and data seeding.
     *
     * @var Seeder
     */
    protected $seeder;

    /**
     * Database connection instance.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $db;

    /**
     * Get the active schema name.
     *
     * If $this->schema is not set, defaults to 'default' (or can be set via the TEST_SCHEMA environment variable).
     *
     * @return string
     */
    protected function getSchema(): string
    {
        return $this->schema ?: 'default';
    }

    /**
     * Get the database connection instance.
     *
     * This is a convenience method to avoid calling Capsule::connection() repeatedly.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function getDb()
    {
        return $this->db;
    }

    /**
     * Shorthand method to get the schema builder.
     *
     * Returns the Schema Builder from the database connection.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function getSchemaBuilder()
    {
        return $this->db->getSchemaBuilder();
    }

    /**
     * Instantiate and return the seed factory.
     *
     * By default, returns an instance of DefaultSeederFactory.
     * Downstream tests can override this method to provide their own factory,
     * or return null to default to using static seeding files.
     *
     * @return AbstractSeederFactory|null
     */
    protected function getSeederFactory(): ?AbstractSeederFactory
    {
        return new DefaultSeederFactory();
    }

    /**
     * Set up the test environment.
     *
     * This method:
     * - Bootstraps the parent Testbench application.
     * - Configures the in‑memory SQLite database via Capsule.
     * - Loads migrations and seeds the test database using the active schema.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set the debug flag from the environment variable (AUTO_JOIN_DEBUG)
        $this->debug = (bool) env('AUTO_JOIN_DEBUG', false);

        // If schema is not set, get it from the environment variable (AUTO_JOIN_TEST_SCHEMA)
        // or default to "default".
        if ($this->schema === null) {
            $this->schema = env('AUTO_JOIN_TEST_SCHEMA', 'default');
        }

        // Configure the in‑memory SQLite database.
        $capsule = new Capsule;
        $config = [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
            'prefix'   => env('DB_PREFIX', ''),
        ];
        $connectionName = env('DB_CONNECTION', 'default');
        $capsule->addConnection($config, $connectionName);
        $capsule->getDatabaseManager()->setDefaultConnection($connectionName);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Store the database connection for easy access.
        $this->db = Capsule::connection();

        // Set up the database schema and seed data.
        $this->setupDatabases();
    }

    /**
     * Set up the database schema and seed data.
     *
     * This method:
     * - Determines the base path for the active schema (e.g., tests/Setup/schemas/default/).
     * - Instantiates the Seeder with the base path.
     * - Loads migrations from the schema's migration folder.
     * - Verifies that all expected tables exist using checkTables().
     * - Seeds common test data.
     *
     * @return void
     */
    protected function setupDatabases(): void
    {
        // Determine the base path using the active schema.
        $schema = $this->getSchema();
        $basePath = __DIR__ . '/Setup/schemas/' . $schema . '/';

        // Instantiate the Seeder with the base path.
        $this->seeder = new Seeder($basePath);

        // Load migrations from the schema's migration folder.
        $this->loadMigrationsFrom($this->seeder->getMigrationsPath());

        // Verify that all expected tables exist in the database.
        $this->seeder->checkTables();

        // Seed common test data.
        $this->seedData();
    }

    /**
     * Seed common test data.
     *
     * Delegates seeding to the Seeder's seedAll() method.
     * Uses the seed factory returned by getSeederFactory() if available.
     *
     * @return void
     */
    protected function seedData(): void
    {
        $factory = $this->getSeederFactory();
        $this->seeder->seedAll($factory);
    }

    /**
     * Print query results as an ASCII table for debugging purposes.
     *
     * Each result should be an associative array where the keys represent the column names.
     *
     * @param array $results The query results.
     * @param string $title   Optional title (e.g., table name) to display before the results.
     * @return void
     */
    protected function debugResults(array $results, string $title = ''): void
    {
        if (!$this->debug) {
            return;
        }

        if ($title !== '') {
            echo "\n$title\n";
        }

        if (empty($results)) {
            echo "No results.\n";
            return;
        }

        // Convert first row to an array.
        $firstRow = is_object($results[0]) ? get_object_vars($results[0]) : (array)$results[0];
        $headers = array_keys($firstRow);

        // Calculate the maximum width for each column.
        $widths = [];
        foreach ($headers as $header) {
            $widths[$header] = strlen($header);
        }
        foreach ($results as $row) {
            $row = is_object($row) ? get_object_vars($row) : (array)$row;
            foreach ($headers as $header) {
                $value = array_key_exists($header, $row) ? (string)$row[$header] : '';
                $widths[$header] = max($widths[$header], strlen($value));
            }
        }

        // Build a horizontal separator line.
        $buildLine = function() use ($headers, $widths): string {
            $line = '+';
            foreach ($headers as $header) {
                $line .= str_repeat('-', $widths[$header] + 2) . '+';
            }
            return $line;
        };

        $line = $buildLine();

        // Print header row.
        echo $line . "\n";
        $headerRow = '|';
        foreach ($headers as $header) {
            $headerRow .= ' ' . str_pad($header, $widths[$header], ' ', STR_PAD_RIGHT) . ' |';
        }
        echo $headerRow . "\n";
        echo $line . "\n";

        // Print each data row.
        foreach ($results as $row) {
            $row = is_object($row) ? get_object_vars($row) : (array)$row;
            $rowLine = '|';
            foreach ($headers as $header) {
                $value = array_key_exists($header, $row) ? (string)$row[$header] : '';
                $rowLine .= ' ' . str_pad($value, $widths[$header], ' ', STR_PAD_RIGHT) . ' |';
            }
            echo $rowLine . "\n";
        }
        echo $line . "\n";
    }

    /**
     * Assert that a table or result set is not empty, and optionally output debug information.
     *
     * This helper method accepts either a table name (string) or a result set (array of objects/arrays).
     * If a table name is provided, it fetches the records from the database.
     * It asserts that the number of records is greater than zero.
     * Additionally, a third parameter can override the global debug flag, forcing debug output
     * if set to true. This flag defaults to true.
     *
     * @param string|array $source     The table name or result set to be asserted.
     * @param string       $title      Optional title for debug output (defaults to an empty string).
     * @param bool         $forceDebug Optional flag to force debug output regardless of the global debug flag (defaults to true).
     *
     * @return void
     */
    protected function assertNonEmptyResults(string|array $source, string $title = '', bool $forceDebug = true): void
    {
        if (is_string($source)) {
            // Fetch results from the specified table.
            $results = array_map('get_object_vars', $this->db->table($source)->get()->all());
            $title = $title ?: $source;
        } else {
            // Assume it's already a result set.
            $results = $source;
        }

        $this->assertNotEmpty($results, "{$title}: The query should return one or more records.");

        //  Print results if debug is on or forced
        if ($forceDebug || $this->debug) {
            $this->debugResults($results, $title);
        }
    }

    /**
     * Assert that all expected tables have non-empty results.
     *
     * Retrieves the list of tables from the seeder if not provided, asserts that the list is an array,
     * and then iterates over each table to ensure that it contains data.
     *
     * @param array|null $tables Optional array of table names. If null, uses the seeder's tables.
     * @return void
     */
    protected function assertTablesNonEmpty(?array $tables = null): void
    {
        $tables = $tables ?? $this->seeder->getTables();
        $this->assertIsArray($tables, 'Tables should be an array.');
        foreach ($tables as $table) {
            $this->assertNonEmptyResults($table, $table);
        }
    }

}
