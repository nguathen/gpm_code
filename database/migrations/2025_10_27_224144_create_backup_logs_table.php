<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['manual', 'auto', 'sync'])->default('auto');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->enum('operation', ['backup_to_drive', 'sync_from_drive', 'restore'])->default('backup_to_drive');
            $table->enum('status', ['queued', 'running', 'completed', 'failed'])->default('queued');
            $table->integer('total_files')->default(0);
            $table->integer('processed_files')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->bigInteger('total_size')->default(0); // bytes
            $table->float('duration', 8, 2)->nullable(); // seconds
            $table->text('error_message')->nullable();
            $table->json('failed_files')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('group_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('backup_logs');
    }
};
