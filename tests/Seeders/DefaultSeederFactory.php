<?php

namespace protich\AutoJoinEloquent\Tests\Seeders;

use Faker\Factory as Faker;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Class DefaultSeederFactory
 *
 * Extends AbstractSeederFactory to seed the database with sample data.
 * This seeder creates users, agents, departments, and pivot records.
 *
 * - Creates 10 users (with user 1 fixed as "Peter Rotich").
 * - Ensures user 1 is an agent.
 * - Creates 8 agents (user 1 plus 7 random others).
 * - Creates 6 departments with predefined names.
 * - Makes user 1 (agent id 1) the manager of all departments.
 * - Grants user 1 access to all departments; other agents get 4 random departments.
 *
 * @package protich\AutoJoinEloquent\Tests\Seeders
 */
class DefaultSeederFactory extends AbstractSeederFactory
{
    /**
     * Seed the database with sample data.
     *
     * @return void
     */
    public function seedData(): void
    {
        $faker = Faker::create();
        $db = Capsule::connection();

        // 1. Create 10 users.
        $usersData = [];

        // First user: fixed as Peter Rotich.
        $usersData[] = [
            'name'       => 'Peter Rotich',
            'email'      => 'peter@osticket.com',
            'password'   => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Create 9 additional random users.
        for ($i = 1; $i < 10; $i++) {
            $usersData[] = [
                'name'       => $faker->name,
                'email'      => $faker->unique()->safeEmail,
                'password'   => bcrypt('secret'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        $db->table('users')->insert($usersData);
        // Assuming auto-increment, user IDs will be 1 through 10.
        $userIds = range(1, 10);

        // 2. Create agents.
        // Force user 1 to be an agent, then randomly select 7 from the remaining users.
        $agentUserIds = [1];
        $remainingUserIds = range(2, 10);
        shuffle($remainingUserIds);
        $agentUserIds = array_merge($agentUserIds, array_slice($remainingUserIds, 0, 7));

        $agentsData = [];
        foreach ($agentUserIds as $userId) {
            $agentsData[] = [
                'user_id'    => $userId,
                'position'   => $userId == 1 ? 'Auto Join Package Developer' : $faker->jobTitle,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        $db->table('agents')->insert($agentsData);
        // Assuming agent IDs are sequential from 1 to 8.
        $agentIds = range(1, 8);

        // 3. Create 6 departments.
        $deptNames = ['Sales', 'Marketing', 'Development', 'Support', 'HR', 'Finance'];
        $departmentsData = [];
        for ($i = 0; $i < 6; $i++) {
            $name = $deptNames[$i] ?? ucfirst($faker->word);
            $departmentsData[] = [
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        $db->table('departments')->insert($departmentsData);
        // Assuming department IDs are sequential from 1 to 6.
        $departmentIds = range(1, 6);

        // 4. Make user 1 the manager for all departments.
        foreach ($departmentIds as $deptId) {
            $db->table('departments')
               ->where('id', $deptId)
               ->update(['manager_id' => 1]);
        }

        // 5. Assign department access.
        // User 1 gets access to all departments; others get 4 random departments.
        $pivotData = [];
        foreach ($agentIds as $agentId) {
            // Determine assigned departments:
            // If agent id is 1, assign all departments; otherwise, assign 4 random departments.
            $assignedDepts = ($agentId === 1)
                ? $departmentIds
                : (function($deps) {
                    shuffle($deps);
                    return array_slice($deps, 0, 4);
                })($departmentIds);

            foreach ($assignedDepts as $deptId) {
                $pivotData[] = [
                    'agent_id'      => $agentId,
                    'department_id' => $deptId,
                    'assigned_at'   => $faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d H:i:s'),
                ];
            }
        }
        $db->table('agent_department')->insert($pivotData);
    }
}

