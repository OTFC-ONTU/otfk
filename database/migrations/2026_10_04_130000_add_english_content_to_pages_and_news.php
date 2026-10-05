<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'news'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->string('title_en')->nullable();
                $table->text('excerpt_en')->nullable();
                $table->longText('body_en')->nullable();
                $table->boolean('translation_published')->default(false);
                $table->string('translation_source_hash', 64)->nullable();

                if ($name === 'pages') {
                    $table->string('meta_title_en')->nullable();
                    $table->string('meta_description_en', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['pages', 'news'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $columns = ['title_en', 'excerpt_en', 'body_en', 'translation_published', 'translation_source_hash'];
                if ($name === 'pages') {
                    $columns = array_merge($columns, ['meta_title_en', 'meta_description_en']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};
