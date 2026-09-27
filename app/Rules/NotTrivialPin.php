<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects the PINs an attacker would try first on a shop-floor tablet: all one
 * digit (1111), a straight run up or down (1234, 654321, 0123) and a repeated
 * two-digit pair (1212, 8080).
 *
 * Non-numeric or wrong-length input is left alone — digits_between and
 * required own those messages.
 */
class NotTrivialPin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ctype_digit($value)) {
            return;
        }

        $digits = str_split($value);
        $length = count($digits);

        if ($length < 2) {
            return;
        }

        // All the same digit.
        if (count(array_unique($digits)) === 1) {
            $fail('That PIN is too easy to guess.');

            return;
        }

        // A straight run, ascending or descending, with wraparound so 9012
        // and 1098 are caught too.
        foreach ([1, -1] as $step) {
            $run = true;
            for ($i = 1; $i < $length; $i++) {
                if (((int) $digits[$i] - (int) $digits[$i - 1] + 10) % 10 !== ($step + 10) % 10) {
                    $run = false;
                    break;
                }
            }
            if ($run) {
                $fail('That PIN is too easy to guess.');

                return;
            }
        }

        // A two-digit pair repeated to fill the PIN (1212, 808080).
        if ($length % 2 === 0) {
            $pair = substr($value, 0, 2);
            if (str_repeat($pair, intdiv($length, 2)) === $value) {
                $fail('That PIN is too easy to guess.');
            }
        }
    }
}
