<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Домени, яким підвал передає SEO-вагу: головний університет і МОН. */
    private const TRUSTED_HOSTS = ['ontu.edu.ua', 'onaft.edu.ua', 'mon.gov.ua'];

    /**
     * Зовнішні посилання-партнери в підвалі отримують rel="nofollow", щоб сайт
     * коледжу не «ручався» за сторонні ресурси (якщо їх зламають або вони
     * займуться спам-SEO). ОНТУ та МОН лишаються звичайними посиланнями;
     * далі прапорець змінюють в адмінці «Підвал і вигляд».
     */
    public function up(): void
    {
        if (! Schema::hasColumn('quick_links', 'nofollow')) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->boolean('nofollow')->default(true)->after('open_new_tab');
            });
        }

        DB::table('quick_links')->where('location', 'footer_partner')->get(['id', 'url'])
            ->filter(function ($link) {
                $host = strtolower((string) parse_url((string) $link->url, PHP_URL_HOST));

                return in_array(preg_replace('/^www\./', '', $host), self::TRUSTED_HOSTS, true);
            })
            ->each(fn ($link) => DB::table('quick_links')->where('id', $link->id)->update(['nofollow' => false]));
    }

    public function down(): void
    {
        if (Schema::hasColumn('quick_links', 'nofollow')) {
            Schema::table('quick_links', function (Blueprint $table) {
                $table->dropColumn('nofollow');
            });
        }
    }
};
