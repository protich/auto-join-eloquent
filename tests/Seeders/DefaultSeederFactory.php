<?php

namespace protich\AutoJoinEloquent\Tests\Seeders;

use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

/**
 * Class: DefaultSeederFactory
 *
 * Seed the default test schema with a deterministic relational graph.
 *
 * This factory remains the canonical seeding entry point for the default
 * schema while breaking the graph into small protected methods for
 * readability and future extension.
 */
class DefaultSeederFactory extends AbstractSeederFactory
{
    /**
     * Faker instance used for seeded sample data.
     *
     * @var Generator
     */
    protected Generator $faker;

    /**
     * Database connection used for inserts and updates.
     *
     * @var Connection
     */
    protected Connection $db;

    /**
     * Seeded user ids.
     *
     * @var array<int,int>
     */
    protected array $userIds = [];

    /**
     * Seeded agent ids.
     *
     * @var array<int,int>
     */
    protected array $agentIds = [];

    /**
     * Seeded department ids.
     *
     * @var array<int,int>
     */
    protected array $departmentIds = [];

    /**
     * Seeded group ids.
     *
     * @var array<int,int>
     */
    protected array $groupIds = [];

    /**
     * Seed the database with the default test graph.
     *
     * @return void
     */
    public function seedData(): void
    {
        $this->faker = Faker::create();
        $this->db    = Capsule::connection();

        $this->seedUsers();
        $this->seedAgents();
        $this->seedDepartments();
        $this->assignDepartmentManagers();
        $this->seedAgentDepartments();

        // Group graph is optional for now. Keep these calls in place so
        // the default schema evolves in one obvious location.
        if ($this->hasTable('groups')) {
            $this->seedGroups();
        }

        if ($this->hasTable('agent_groups')) {
            $this->seedAgentGroups();
        }

        if ($this->hasTable('group_departments')) {
            $this->seedGroupDepartments();
        }
    }

    /**
     * Seed users.
     *
     * Creates one fixed user followed by additional random users.
     *
     * @return void
     */
    protected function seedUsers(): void
    {
        $usersData = [
            [
                'name'       => 'Peter Rotich',
                'email'      => 'peter@osticket.com',
                'password'   => bcrypt('secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        for ($i = 1; $i < 10; $i++) {
            $usersData[] = [
                'name'       => $this->faker->name,
                'email'      => $this->faker->unique()->safeEmail,
                'password'   => bcrypt('secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->db->table('users')->insert($usersData);

        // Default test schema is recreated fresh, so ids are predictable.
        $this->userIds = range(1, 10);
    }

    /**
     * Seed agents.
     *
     * Forces user 1 to be an agent, then assigns seven more random users.
     *
     * @return void
     */
    protected function seedAgents(): void
    {
        $agentUserIds     = [1];
        $remainingUserIds = range(2, 10);

        shuffle($remainingUserIds);

        $agentUserIds = array_merge(
            $agentUserIds,
            array_slice($remainingUserIds, 0, 7)
        );

        $agentsData = [];

        foreach ($agentUserIds as $userId) {
            $agentsData[] = [
                'flags'      => 1,
                'user_id'    => $userId,
                'position'   => $userId === 1
                    ? 'Auto Join Package Developer'
                    : $this->faker->jobTitle,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->db->table('agents')->insert($agentsData);

        $this->agentIds = range(1, count($agentsData));
    }

    /**
     * Seed departments.
     *
     * @return void
     */
    protected function seedDepartments(): void
    {
        $deptNames       = ['Sales', 'Marketing', 'Development', 'Support', 'HR', 'Finance'];
        $departmentsData = [];

        foreach ($deptNames as $name) {
            $departmentsData[] = [
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->db->table('departments')->insert($departmentsData);

        $this->departmentIds = range(1, count($departmentsData));
    }

    /**
     * Assign department managers.
     *
     * User 1 manages all departments in the default graph.
     *
     * @return void
     */
    protected function assignDepartmentManagers(): void
    {
        foreach ($this->departmentIds as $deptId) {
            $this->db->table('departments')
                ->where('id', $deptId)
                ->update(['manager_id' => 1]);
        }
    }

    /**
     * Seed direct agent-to-department assignments.
     *
     * Agent 1 receives all departments. Other agents receive four random
     * departments each.
     *
     * @return void
     */
    protected function seedAgentDepartments(): void
    {
        $pivotData = [];

        foreach ($this->agentIds as $agentId) {
            $assignedDepts = $agentId === 1
                ? $this->departmentIds
                : $this->pickRandomDepartments(4);

            foreach ($assignedDepts as $deptId) {
                $pivotData[] = [
                    'agent_id'      => $agentId,
                    'department_id' => $deptId,
                    'assigned_at'   => $this->faker
                        ->dateTimeBetween('-1 years', 'now')
                        ->format('Y-m-d H:i:s'),
                ];
            }
        }

        $this->db->table('agent_department')->insert($pivotData);
    }

    /**
     * Seed groups.
     *
     * Includes a simple parent/child shape so hierarchy is available for
     * future tests without being required by current ones.
     *
     * @return void
     */
    protected function seedGroups(): void
    {
        $groupsData = [
            [
                'name'       => 'Support',
                'parent_id'  => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Engineering',
                'parent_id'  => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Escalations',
                'parent_id'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $this->db->table('groups')->insert($groupsData);

        $this->groupIds = range(1, count($groupsData));
    }

    /**
     * Seed agent-to-group assignments.
     *
     * @return void
     */
    protected function seedAgentGroups(): void
    {
        if ($this->groupIds === []) {
            return;
        }

        $rows = [
            [
                'agent_id'    => 1,
                'group_id'    => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'agent_id'    => 2,
                'group_id'    => 2,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        $this->db->table('agent_groups')->insert($rows);
    }

    /**
     * Seed group-to-department assignments.
     *
     * @return void
     */
    protected function seedGroupDepartments(): void
    {
        if ($this->groupIds === [] || $this->departmentIds === []) {
            return;
        }

        $rows = [
            [
                'group_id'      => 1,
                'department_id' => 5,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'group_id'      => 2,
                'department_id' => 3,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'group_id'      => 3,
                'department_id' => 4,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ];

        $this->db->table('group_departments')->insert($rows);
    }

    /**
     * Pick a random subset of department ids.
     *
     * @param  int $count
     * @return array<int,int>
     */
    protected function pickRandomDepartments(int $count): array
    {
        $departments = $this->departmentIds;

        shuffle($departments);

        return array_slice($departments, 0, $count);
    }

    /**
     * Determine whether a table exists in the current schema.
     *
     * @param  string $table
     * @return bool
     */
    protected function hasTable(string $table): bool
    {
        return Capsule::schema()->hasTable($table);
    }
}
