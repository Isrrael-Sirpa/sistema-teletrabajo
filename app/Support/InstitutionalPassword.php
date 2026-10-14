<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class InstitutionalPassword
{
    public static function rule(): Password
    {
        return Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();
    }
}
