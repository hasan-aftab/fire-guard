<?php

namespace App\Services;

use InvalidArgumentException;
use Propaganistas\LaravelPhone\PhoneNumber;

class WhatsAppPhone
{
    public function normalize(string $input, bool $allowPossible = false): string
    {
        $phone = new PhoneNumber(trim($input));
        if ($allowPossible) {
            $phone->lenient();
        }
        if (! str_starts_with(trim($input), '+') || ! $phone->isValid()) {
            throw new InvalidArgumentException('Enter a valid international phone number beginning with +.');
        }

        return $phone->formatE164();
    }

    public function toMetaRecipient(string $canonical): string
    {
        return substr($this->normalize($canonical), 1);
    }
}
