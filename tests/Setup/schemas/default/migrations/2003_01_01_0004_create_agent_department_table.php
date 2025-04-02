<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentDepartmentTable extends Migration
{
    public function up()
    {
        Capsule::schema()->create('agent_department', function (Blueprint $table) {
            $table->unsignedInteger('agent_id');
            $table->unsignedInteger('department_id');
            // Add assigned_at timestamp to track when an agent was assigned to a department.
            $table->timestamp('assigned_at')->nullable();
            $table->primary(['agent_id', 'department_id']);
        });
    }

    public function down()
    {
        Capsule::schema()->dropIfExists('agent_department');
    }
}
