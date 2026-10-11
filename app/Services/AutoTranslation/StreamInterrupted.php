<?php

namespace App\Services\AutoTranslation;

/** Потік відповіді API обірвався до message_stop — тимчасова помилка, запис повторюється. */
final class StreamInterrupted extends \RuntimeException
{
}
