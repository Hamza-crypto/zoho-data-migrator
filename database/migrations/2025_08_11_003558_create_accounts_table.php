<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_id')->nullable();
            $table->string('zoho_parent_id')->nullable();
            $table->string('fresh_crm_id')->nullable();
            $table->string('fresh_crm_parent_id')->nullable();
            $table->string('name')->index();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_new')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
