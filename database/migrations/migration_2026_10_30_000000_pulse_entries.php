<?php

use Spark\Database\Schema\Blueprint;
use Spark\Facades\Schema;

return new class {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pulse_entries', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('batch_id', 36)->index();
            $table->string('type', 30)->index();
            $table->string('family_hash', 64)->nullable()->index();
            $table->float('duration')->nullable()->index();
            $table->longText('content');
            $table->text('tags')->nullable();
            $table->string('created_at', 30)->index();
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_entries');
    }
};
