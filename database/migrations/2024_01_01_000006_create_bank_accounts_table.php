// database/migrations/2024_01_01_000006_create_bank_accounts_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('account_title');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('bank_code');
            $table->string('iban')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'account_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bank_accounts');
    }
};
