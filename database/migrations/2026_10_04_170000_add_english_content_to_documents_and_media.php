<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['document_categories', 'documents', 'videos', 'galleries', 'photos'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->boolean('translation_published')->default(false);
                $table->string('translation_source_hash', 64)->nullable();
                foreach ($this->fields($name) as $field) {
                    if ($field === 'description') {
                        $table->text($field.'_en')->nullable();
                    } else {
                        $table->string($field.'_en')->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['document_categories', 'documents', 'videos', 'galleries', 'photos'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropColumn(array_merge(
                    array_map(fn ($field) => $field.'_en', $this->fields($name)),
                    ['translation_published', 'translation_source_hash']
                ));
            });
        }
    }

    private function fields(string $table): array
    {
        return match ($table) {
            'document_categories' => ['title'],
            'photos' => ['caption'],
            default => ['title', 'description'],
        };
    }
};
