<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAgentsTable extends Migration
{
    /**
     * Create agents table.
     *
     * Includes flags column used for model-defined status paths
     * (e.g. model__status → flags).
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('agents', function (Blueprint $table) {
            $table->increments('id');

            // Bitmask flags used for model-level state (status, etc.)
            $table->unsignedInteger('flags')->default(0)->index();

            // Foreign key referencing users.id (nullable for test flexibility)
            $table->unsignedInteger('user_id')->nullable();

            // Optional position metadata
            $table->string('position')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Drop agents table.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('agents');
    }
}
