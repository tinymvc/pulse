<?php

use Spark\Database\DB;
use Spark\Database\Schema\Blueprint;
use Spark\Facades\Schema;

return new class {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->onStorageConnection(function () {
            Schema::create(config('pulse.storage.table', 'pulse_entries'), function (Blueprint $table) {
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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->onStorageConnection(fn() => Schema::dropIfExists(config('pulse.storage.table', 'pulse_entries')));
    }
    /** Spark's schema builder resolves the active DB binding for its grammar and PDO. */
    private function onStorageConnection(Closure $migrate): void
    {
        $default = app(DB::class);
        $target = DB::connection(config('pulse.storage.connection'));
        if ($target === $default) {
            $migrate();
            return;
        }

        app()->instance(DB::class, $target);
        try {
            $migrate();
        } finally {
            app()->instance(DB::class, $default);
        }
    }
};
