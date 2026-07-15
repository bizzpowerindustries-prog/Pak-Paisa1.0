// database/migrations/2024_01_01_000007_create_devices_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_id')->unique();
            $table->string('device_name');
            $table->string('device_type');
            $table->string('os_version');
            $table->string('app_version');
            $table->string('push_token')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['user_id', 'device_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('devices');
    }
};
