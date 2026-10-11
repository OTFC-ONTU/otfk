<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Автопереклад через Claude API (App\Services\AutoTranslation\*):
     * translation_jobs — черга й журнал запитів (токени, приблизна вартість, помилки) для дашборда;
     * translation_models — кеш списку моделей з Models API (оновлюється не частіше разу на тиждень).
     */
    public function up(): void
    {
        if (! Schema::hasTable('translation_jobs')) {
            Schema::create('translation_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('translatable_type', 120);
                $table->unsignedBigInteger('translatable_id');
                $table->string('label', 255)->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('model', 100)->nullable();
                $table->text('fields')->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('cache_read_tokens')->default(0);
                $table->unsignedInteger('cache_write_tokens')->default(0);
                $table->decimal('cost_usd', 12, 6)->nullable();
                $table->text('error')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('finished_at')->nullable()->index();
                $table->timestamps();
                $table->index(['translatable_type', 'translatable_id']);
            });
        }

        if (! Schema::hasTable('translation_models')) {
            Schema::create('translation_models', function (Blueprint $table) {
                $table->string('id', 100)->primary();
                $table->string('display_name', 150)->nullable();
                $table->unsignedInteger('max_tokens')->nullable();
                $table->boolean('supports_effort')->nullable();
                $table->boolean('supports_structured_outputs')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamp('fetched_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_jobs');
        Schema::dropIfExists('translation_models');
    }
};
