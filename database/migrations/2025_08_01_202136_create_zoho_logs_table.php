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
        Schema::create('zoho_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->unsignedBigInteger('internal_id')->nullable();
            $table->string('zoho_record_id')->nullable();
            $table->json('payload');
            $table->json('response')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoho_logs');
    }
};
