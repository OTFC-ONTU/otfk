<?php

namespace App\Policies;

/** Керування сутністю Setting доступне лише адміністратору (див. AdminOnlyPolicy). */
class SettingPolicy extends AdminOnlyPolicy {}
