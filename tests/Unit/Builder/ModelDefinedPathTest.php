<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Builder;

use PHPUnit\Framework\Attributes\DataProvider;
use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Tests\Models\Agent;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;

/**
 * Test: ModelDefinedPathTest
 *
 * Verify complete model-defined paths are delegated to the base model.
 */
class ModelDefinedPathTest extends AutoJoinTestCase
{
    /**
     * Test model-defined path descriptor resolution.
     */
    public function test_model_status_path_is_described(): void
    {
        $builder = Agent::query();

        $this->assertInstanceOf(AutoJoinQueryBuilder::class, $builder);
        $this->assertTrue($builder->isModelDefinedPath('model__status'));

        $described = $builder->describeModelDefinedPath('model__status');

        $this->assertSame('status', $described['path']);
        $this->assertInstanceOf(
            ExpressionDescriptor::class,
            $described['descriptor']
        );
        $this->assertSame('path', $described['descriptor']->type());
        $this->assertSame('flags', $described['descriptor']->getPath());
    }

    /**
     * Test the package does not segment a model-defined path.
     */
    public function test_model_receives_the_complete_path(): void
    {
        $builder = Agent::query();

        $described = $builder->describeModelDefinedPath(
            'model__accessibleDepartments__id__count'
        );

        $this->assertSame(
            'accessibleDepartments__id__count',
            $described['path']
        );
        $this->assertSame(
            ['departments.id', 'groups.departments.id'],
            $described['descriptor']->paths()
        );
    }

    /**
     * Test invalid model-defined path throws.
     *
     * @return void
     */
    public function test_invalid_model_defined_path_throws(): void
    {
        $builder = Agent::query();

        $this->expectException(\InvalidArgumentException::class);

        $builder->describeModelDefinedPath('model__');
    }

    /**
     * Test model delegation still requires the reserved query marker.
     */
    public function test_unmarked_path_is_not_delegated_to_the_model(): void
    {
        $builder = Agent::query();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Column [status] is not a model-defined path.'
        );

        $builder->describeModelDefinedPath('status');
    }

    /**
     * Test relation hooks receive the complete path without the marker.
     */
    public function test_relation_hook_receives_model_defined_path(): void
    {
        Agent::$autoJoinRelationDescriptions = [];

        Agent::query()
            ->select('model__userNamed__Alice as user_name')
            ->toSql();

        $this->assertContains([
            'name' => 'userByName',
            'path' => 'userNamed__Alice',
        ], Agent::$autoJoinRelationDescriptions);
    }

    /**
     * Test constraint variants receive independent aliases and bindings.
     */
    public function test_model_paths_keep_relationship_constraints_distinct(): void
    {
        $query = Agent::query()->select([
            'model__userNamed__Alice as alice',
            'model__userNamed__Bob as bob',
        ]);

        $sql = $query->toSql();

        $this->assertSame(2, substr_count($sql, 'join "ost_users"'));
        $this->assertStringContainsString(
            '"B"."name" as "alice"',
            $sql
        );
        $this->assertStringContainsString(
            '"C"."name" as "bob"',
            $sql
        );
        $this->assertSame(['Alice', 'Bob'], $query->getBindings());
    }

    /**
     * Test identical constraint variants reuse their relationship join.
     */
    public function test_identical_model_paths_reuse_the_relationship_join(): void
    {
        $query = Agent::query()->select([
            'model__userNamed__Alice as first_name',
            'model__userNamed__Alice as second_name',
        ]);

        $this->assertSame(
            1,
            substr_count($query->toSql(), 'join "ost_users"')
        );
        $this->assertSame(['Alice'], $query->getBindings());
    }

    /**
     * Test plain paths do not reuse model-defined constraints.
     *
     * @param  list<string> $columns
     */
    #[DataProvider('mixedPathOrderProvider')]
    public function test_plain_and_model_paths_keep_constraints_separate(
        array $columns
    ): void {
        $query = Agent::query()->select($columns);
        $sql = $query->toSql();

        $this->assertSame(2, substr_count($sql, 'join "ost_users"'));
        $this->assertSame(1, substr_count($sql, '"name" = ?'));
        $this->assertSame(['Alice'], $query->getBindings());
    }

    /**
     * Provide both compilation orders for mixed path coverage.
     *
     * @return iterable<string,array{list<string>}>
     */
    public static function mixedPathOrderProvider(): iterable
    {
        yield 'model path first' => [[
            'model__userNamed__Alice as constrained',
            'userByName.name as plain',
        ]];

        yield 'plain path first' => [[
            'userByName.name as plain',
            'model__userNamed__Alice as constrained',
        ]];
    }

    /**
     * Test named-alias mode keeps constraint variant aliases unique.
     */
    public function test_named_alias_mode_keeps_variants_unique(): void
    {
        $query = Agent::query();
        $query->setUseSimpleAliases(false);
        $query->select([
            'model__userNamed__Alice as alice',
            'model__userNamed__Bob as bob',
        ]);

        $sql = $query->toSql();

        $this->assertSame(2, substr_count($sql, 'join "ost_users"'));
        $this->assertStringContainsString(
            'as "userByName"',
            $sql
        );
        $this->assertStringContainsString(
            'as "userByName__',
            $sql
        );
        $this->assertSame(['Alice', 'Bob'], $query->getBindings());
    }

    /**
     * Test a single-path count retains its model-defined source path.
     */
    public function test_single_count_relationship_receives_model_path(): void
    {
        Agent::$autoJoinRelationDescriptions = [];

        $query = Agent::query()
            ->select('model__userNamed__Alice__count as matching_users');
        $sql = $query->toSql();

        $this->assertStringContainsString('COUNT(', $sql);
        $this->assertSame(['Alice'], $query->getBindings());
        $this->assertContains([
            'name' => 'userByName',
            'path' => 'userNamed__Alice__count',
        ], Agent::$autoJoinRelationDescriptions);
    }

    /**
     * Test multi-path count strategies retain the model-defined source path.
     */
    #[DataProvider('multiPathCountProvider')]
    public function test_multi_path_count_relationships_receive_model_path(
        string $path
    ): void {
        Agent::$autoJoinRelationDescriptions = [];

        Agent::query()->select("{$path} as total")->toSql();
        $modelPath = substr(
            $path,
            strlen(AutoJoinQueryBuilder::MODEL_PATH_PREFIX)
        );

        $this->assertNotEmpty(Agent::$autoJoinRelationDescriptions);

        foreach (Agent::$autoJoinRelationDescriptions as $description) {
            $this->assertSame($modelPath, $description['path']);
        }
    }

    /**
     * Provide EXISTS and UNION multi-path count descriptors.
     *
     * @return iterable<string,array{string}>
     */
    public static function multiPathCountProvider(): iterable
    {
        yield 'exists strategy' => ['model__qualifiedDepartmentCount'];
        yield 'union strategy' => ['model__mixedComplexCount'];
    }
}
