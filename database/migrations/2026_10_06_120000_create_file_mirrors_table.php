<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Черга дзеркалювання файлів старого сайту на публічний диск (команда otfk:mirror-files).
     * source_url — джерело, path — відносний шлях на диску public; sha256/size дозволяють
     * перевірити й відновити файли після переїзду на інший хостинг.
     */
    public function up(): void
    {
        if (Schema::hasTable('file_mirrors')) {
            return;
        }

        Schema::create('file_mirrors', function (Blueprint $table) {
            $table->id();
            $table->text('source_url');
            $table->char('source_hash', 64)->unique();
            $table->string('path', 700)->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->string('mime', 150)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_mirrors');
    }
};
