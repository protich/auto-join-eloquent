<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Class: CreateGroupsTable
 *
 * Create groups table for the auto-join test graph.
 */
class CreateGroupsTable extends Migration
{
    /**
     * Create groups table.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('parent_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drop groups table.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('groups');
    }
}
