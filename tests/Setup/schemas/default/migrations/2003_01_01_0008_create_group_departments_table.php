<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class: CreateGroupDepartmentsTable
 *
 * Create group_departments pivot table for the auto-join test graph.
 */
class CreateGroupDepartmentsTable extends Migration
{
    /**
     * Create group_departments table.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('group_departments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('group_id');
            $table->unsignedInteger('department_id');
            $table->timestamps();
        });
    }

    /**
     * Drop group_departments table.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('group_departments');
    }
}
