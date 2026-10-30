<?php

use Spark\Database\Schema\Blueprint;
use Spark\Database\Schema\Schema;

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
        });

        Schema::create('pulse_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('bucket', 10)->index(); // '1m', '1h', '24h'
            $table->string('period', 30)->index(); // date/hour period string
            $table->string('type', 30)->index();
            $table->string('key', 120)->index();
            $table->float('value');
            $table->integer('count')->default(1);
            $table->index(['bucket', 'period', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_aggregates');
        Schema::dropIfExists('pulse_entries');
    }
};
