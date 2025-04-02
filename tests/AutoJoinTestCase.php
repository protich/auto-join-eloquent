<?php

namespace protich\AutoJoinEloquent\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

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

        // Set the debug flag from the environment variable (AUTO_JOIN_DEBUG_SQL)
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
     * This method delegates seeding to the Seeder's seedAll() method.
     *
     * @return void
     */
    protected function seedData(): void
    {
        $this->seeder->seedAll();
    }

    /**
     * Print query results as an ASCII table for debugging purposes.
     *
     * Each result should be an associative array where the keys represent the column names.
     *
     * @param array $results The query results.
     * @return void
     */
    protected function debugResults(array $results): void
    {

        if (!$this->debug)
            return;

        if (empty($results)) {
            echo "No results.\n";
            return;
        }

        // Get headers from the first row
        $headers = array_keys($results[0]);

        // Calculate the maximum width for each column (header and values)
        $widths = [];
        foreach ($headers as $header) {
            $widths[$header] = strlen($header);
        }
        foreach ($results as $row) {
            foreach ($headers as $header) {
                $value = isset($row[$header]) ? (string)$row[$header] : '';
                $widths[$header] = max($widths[$header], strlen($value));
            }
        }

        // Function to build a horizontal line
        $buildLine = function() use ($headers, $widths): string {
            $line = '+';
            foreach ($headers as $header) {
                $line .= str_repeat('-', $widths[$header] + 2) . '+';
            }
            return $line;
        };

        $line = $buildLine();

        // Print header row
        echo $line . "\n";
        $headerRow = '|';
        foreach ($headers as $header) {
            $headerRow .= ' ' . str_pad($header, $widths[$header], ' ', STR_PAD_RIGHT) . ' |';
        }
        echo $headerRow . "\n";
        echo $line . "\n";

        // Print each data row
        foreach ($results as $row) {
            $rowLine = '|';
            foreach ($headers as $header) {
                $value = isset($row[$header]) ? (string)$row[$header] : '';
                $rowLine .= ' ' . str_pad($value, $widths[$header], ' ', STR_PAD_RIGHT) . ' |';
            }
            echo $rowLine . "\n";
        }
        echo $line . "\n";
    }

}
