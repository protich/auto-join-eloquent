<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->increments('id');
            // Name field for the user.
            $table->string('name');
            // Optional phone field.
            $table->string('phone')->nullable();
            // Email field for the user.
            $table->string('email')->nullable();
            // Password field for storing user passwords.
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down()
    {
        Capsule::schema()->dropIfExists('users');
    }
}
