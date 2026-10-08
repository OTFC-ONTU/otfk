<?php

/*
 * Перекриття конфігурації Filament (решта ключів — з пакета, mergeConfigFrom).
 * Filament 4 типово бере диск FILESYSTEM_DISK (local, приватний); завантаження адмінки
 * (фото, документи, вкладення редактора) мають лишатися на public, як у Filament 3.
 */
return [
    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),
];
