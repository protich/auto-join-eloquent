<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentsTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('agents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('flags')->default(0);
            // Foreign key referencing users.id.
            $table->unsignedInteger('user_id')->nullable();
            // Position field for the agent.
            $table->string('position')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('agents');
    }
}
