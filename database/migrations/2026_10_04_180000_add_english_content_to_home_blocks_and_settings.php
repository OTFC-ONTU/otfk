<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->tables() as $name => $fields) {
            Schema::table($name, function (Blueprint $table) use ($fields) {
                $table->boolean('translation_published')->default(false);
                $table->string('translation_source_hash', 64)->nullable();
                foreach ($fields as $field) {
                    if (in_array($field, ['quote', 'value'], true)) {
                        $table->text($field.'_en')->nullable();
                    } else {
                        $table->string($field.'_en')->nullable();
                    }
                }
            });
        }
        Cache::forget('settings.map');
        Cache::forget('settings.translations');
    }

    public function down(): void
    {
        foreach ($this->tables() as $name => $fields) {
            Schema::table($name, function (Blueprint $table) use ($fields) {
                $table->dropColumn(array_merge(
                    array_map(fn ($field) => $field.'_en', $fields),
                    ['translation_published', 'translation_source_hash']
                ));
            });
        }
        Cache::forget('settings.map');
        Cache::forget('settings.translations');
    }

    private function tables(): array
    {
        return [
            'banners' => ['title', 'subtitle', 'image_alt', 'link_label'],
            'quick_links' => ['title', 'description'],
            'testimonials' => ['name', 'role', 'quote'],
            'stat_items' => ['label'],
            'settings' => ['value'],
        ];
    }
};
