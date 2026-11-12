<?php

namespace App\Rules;

use App\NetworkMonitoring\SnmpProtocolPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SnmpReadOnlyCommunity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! SnmpProtocolPolicy::communityIsSafe(is_string($value) ? $value : null)) {
            $fail('Community harus 8–128 karakter, tanpa spasi awal/akhir atau karakter kontrol, dan bukan public/private.');
        }
    }
}
