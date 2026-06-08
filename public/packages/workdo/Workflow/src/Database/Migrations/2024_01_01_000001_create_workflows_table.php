<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if(!Schema::hasTable('workflows'))
        {
            Schema::create('workflows', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('module', 100);
                $table->string('submodule', 100);
                $table->integer('is_active')->default(1);

                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

                $table->timestamps();

            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('workflows');
    }
};
