<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Відновлення схеми після старої міграції видалення, до перекладу відгуків.
        if (! Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('role')->nullable();
                $table->text('quote');
                $table->string('photo')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('applicant_requests')) {
            Schema::create('applicant_requests', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone', 50);
                $table->string('email')->nullable();
                $table->foreignId('specialty_id')->nullable()->constrained('specialties')->nullOnDelete();
                $table->text('message')->nullable();
                $table->boolean('is_processed')->default(false);
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('feedback_messages')) {
            Schema::create('feedback_messages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('subject')->nullable();
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->string('ip')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Відновлені таблиці можуть містити нові дані: відкат їх не видаляє.
    }
};
