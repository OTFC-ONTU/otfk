<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['specialties', 'departments', 'programs'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->string('title_en')->nullable();
                $table->longText('description_en')->nullable();
                $table->boolean('translation_published')->default(false);
                $table->string('translation_source_hash', 64)->nullable();

                if ($name === 'specialties') {
                    $table->text('short_description_en')->nullable();
                    $table->string('degree_en')->nullable();
                    $table->string('study_form_en')->nullable();
                    $table->string('duration_en')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['specialties', 'departments', 'programs'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $columns = ['title_en', 'description_en', 'translation_published', 'translation_source_hash'];
                if ($name === 'specialties') {
                    $columns = array_merge($columns, ['short_description_en', 'degree_en', 'study_form_en', 'duration_en']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};
