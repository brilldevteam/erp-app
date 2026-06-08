<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if(!Schema::hasTable('workflow_conditions'))
        {
            Schema::create('workflow_conditions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id');
                $table->string('field', 100);
                $table->string('operator', 20);
                $table->text('value');
                $table->timestamps();

                $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('workflow_conditions');
    }
};
