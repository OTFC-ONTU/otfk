<?php

namespace App\Policies;

/** Карта старих адрес і журнал 404 — лише адміністратору (див. AdminOnlyPolicy). */
class LegacyRedirectPolicy extends AdminOnlyPolicy {}
