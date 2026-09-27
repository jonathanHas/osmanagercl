<?php

namespace Tests\Unit\Rules;

use App\Rules\NotTrivialPin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotTrivialPinTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'all the same' => ['1111'],
            'all zeroes' => ['000000'],
            'ascending run' => ['1234'],
            'ascending from zero' => ['0123'],
            'ascending six' => ['456789'],
            'descending run' => ['654321'],
            'descending four' => ['4321'],
            'wrapping up' => ['9012'],
            'wrapping down' => ['1098'],
            'repeated pair' => ['1212'],
            'repeated pair zeroes' => ['8080'],
            'repeated pair six' => ['737373'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'ordinary four' => ['2580'],
            'ordinary six' => ['739104'],
            'near-run' => ['1235'],
            'repeated but not a pair' => ['1213'],
            'triple then one' => ['1112'],
            'pair repeated oddly' => ['12121'],
        ];
    }

    #[DataProvider('rejectedProvider')]
    public function test_it_rejects_a_guessable_pin(string $pin): void
    {
        $this->assertSame(['That PIN is too easy to guess.'], $this->failures($pin));
    }

    #[DataProvider('acceptedProvider')]
    public function test_it_accepts_a_reasonable_pin(string $pin): void
    {
        $this->assertSame([], $this->failures($pin));
    }

    public function test_it_leaves_non_numeric_input_to_the_other_rules(): void
    {
        $this->assertSame([], $this->failures('abcd'));
    }

    /**
     * @return array<int, string>
     */
    private function failures(string $pin): array
    {
        $messages = [];
        (new NotTrivialPin)->validate('pin', $pin, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }
}
