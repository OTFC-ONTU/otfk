<?php

namespace App\Policies;

/** Керування сутністю User доступне лише адміністратору (див. AdminOnlyPolicy). */
class UserPolicy extends AdminOnlyPolicy {}
