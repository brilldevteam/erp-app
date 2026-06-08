<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if(!Schema::hasTable('workflow_actions'))
        {
            Schema::create('workflow_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id');
                $table->string('type', 50);
                $table->json('config');
                $table->text('message')->nullable();
                $table->timestamps();

                $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('workflow_actions');
    }
};
