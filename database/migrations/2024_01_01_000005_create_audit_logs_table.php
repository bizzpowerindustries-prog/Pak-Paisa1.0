// database/migrations/2024_01_01_000005_create_audit_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action');
            $table->string('module');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address');
            $table->string('user_agent');
            $table->string('session_id')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'action', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};
