<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentsTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('departments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            // Manager_id to maps to agents.id.
            $table->unsignedInteger('manager_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('departments');
    }
}
