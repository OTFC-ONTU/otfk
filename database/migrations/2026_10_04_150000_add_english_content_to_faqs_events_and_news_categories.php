<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['faqs', 'events', 'news_categories'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->boolean('translation_published')->default(false);
                $table->string('translation_source_hash', 64)->nullable();
                $table->string($name === 'faqs' ? 'question_en' : 'title_en')->nullable();
                if ($name === 'faqs') {
                    $table->text('answer_en')->nullable();
                } elseif ($name === 'events') {
                    $table->text('description_en')->nullable();
                    $table->string('location_en')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['faqs', 'events', 'news_categories'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $fields = match ($name) {
                    'faqs' => ['question_en', 'answer_en'],
                    'events' => ['title_en', 'description_en', 'location_en'],
                    'news_categories' => ['title_en'],
                };
                $table->dropColumn(array_merge($fields, ['translation_published', 'translation_source_hash']));
            });
        }
    }
};
