<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Адреси файлів диска public (Storage::url — FileUpload і вкладення Filament) — від хоста поточного
 * запиту, як asset() публічної частини. Інакше при іншому хості, ніж APP_URL (www, порт, http/https),
 * браузер блокує завантаження файлу (CORS) і поле файлу Filament безкінечно «очікує».
 */
class PublicStorageUrl
{
    public static function useRequestHost(Request $request): void
    {
        if (filled($request->getHost())) {
            config(['filesystems.disks.public.url' => $request->getSchemeAndHttpHost().'/storage']);
        }
    }
}
