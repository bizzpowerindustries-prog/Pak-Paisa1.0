
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('phone_encrypted')->nullable();
            $table->string('cnic')->unique();
            $table->string('cnic_encrypted')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->string('pin')->nullable();
            $table->string('two_factor_secret')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->enum('kyc_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('status', ['active', 'blocked', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['phone', 'email']);
            $table->index('kyc_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};
