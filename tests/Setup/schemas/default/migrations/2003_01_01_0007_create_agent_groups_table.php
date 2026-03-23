<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class: CreateAgentGroupsTable
 *
 * Create agent_groups pivot table for the auto-join test graph.
 */
class CreateAgentGroupsTable extends Migration
{
    /**
     * Create agent_groups table.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('agent_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('agent_id');
            $table->unsignedInteger('group_id');
            $table->timestamps();
        });
    }

    /**
     * Drop agent_groups table.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('agent_groups');
    }
}
