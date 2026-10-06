<?php

namespace Tests\Unit;

use App\Support\PosProductAttributes;
use PHPUnit\Framework\TestCase;

class PosProductAttributesTest extends TestCase
{
    private const DEPOSIT_XML = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
        .'<!DOCTYPE properties SYSTEM "http://java.sun.com/dtd/properties.dtd">'."\n"
        ."<properties>\n"
        ."<comment>osmanager deposit</comment>\n"
        ."<entry key=\"deposit.id\">80184d44-d2ca-4a05-b243-3e5166612849</entry>\n"
        ."<entry key=\"deposit.name\">Bottle deposit 0.25</entry>\n"
        ."<entry key=\"deposit.price\">0.25</entry>\n"
        ."</properties>\n";

    private const DEPOSIT = [
        'deposit.id' => '80184d44-d2ca-4a05-b243-3e5166612849',
        'deposit.name' => 'Bottle deposit 0.25',
        'deposit.price' => '0.25',
    ];

    public function test_serialize_matches_the_till_format_byte_for_byte(): void
    {
        $this->assertSame(self::DEPOSIT_XML, PosProductAttributes::serialize(self::DEPOSIT));
    }

    public function test_serialize_of_nothing_is_null(): void
    {
        $this->assertNull(PosProductAttributes::serialize([]));
    }

    public function test_parse_round_trips(): void
    {
        $this->assertSame(self::DEPOSIT, PosProductAttributes::parse(PosProductAttributes::serialize(self::DEPOSIT)));
        $this->assertSame(self::DEPOSIT, PosProductAttributes::parse(self::DEPOSIT_XML));
    }

    public function test_with_deposit_keeps_foreign_keys(): void
    {
        $xml = PosProductAttributes::serialize(['foo' => 'bar'], 'till');

        $result = PosProductAttributes::parse(
            PosProductAttributes::withDeposit($xml, self::DEPOSIT['deposit.id'], 'Bottle deposit 0.25', '0.25')
        );

        $this->assertSame(['foo' => 'bar'] + self::DEPOSIT, $result);
    }

    public function test_with_deposit_on_nothing_gives_the_exact_xml(): void
    {
        $this->assertSame(
            self::DEPOSIT_XML,
            PosProductAttributes::withDeposit(null, self::DEPOSIT['deposit.id'], 'Bottle deposit 0.25', '0.25')
        );
    }

    public function test_without_deposit_on_deposit_only_xml_is_null(): void
    {
        $this->assertNull(PosProductAttributes::withoutDeposit(self::DEPOSIT_XML));
        $this->assertNull(PosProductAttributes::withoutDeposit(null));
    }

    public function test_without_deposit_keeps_foreign_keys(): void
    {
        $xml = PosProductAttributes::serialize(['foo' => 'bar'] + self::DEPOSIT);

        $this->assertSame(['foo' => 'bar'], PosProductAttributes::parse(PosProductAttributes::withoutDeposit($xml)));
    }

    public function test_values_and_keys_are_escaped(): void
    {
        $xml = PosProductAttributes::serialize(['a&b' => 'x < y & "z"']);

        $this->assertStringContainsString('<entry key="a&amp;b">x &lt; y &amp; &quot;z&quot;</entry>', $xml);
        $this->assertSame(['a&b' => 'x < y & "z"'], PosProductAttributes::parse($xml));
    }

    public function test_garbage_parses_to_nothing(): void
    {
        $this->assertSame([], PosProductAttributes::parse('not xml <<<'));
        $this->assertSame([], PosProductAttributes::parse(''));
        $this->assertSame([], PosProductAttributes::parse('<other><entry key="a">b</entry></other>'));
    }

    public function test_has_deposit_and_deposit_id(): void
    {
        $this->assertTrue(PosProductAttributes::hasDeposit(self::DEPOSIT_XML));
        $this->assertSame(self::DEPOSIT['deposit.id'], PosProductAttributes::depositId(self::DEPOSIT_XML));
        $this->assertFalse(PosProductAttributes::hasDeposit(PosProductAttributes::serialize(['foo' => 'bar'])));
        $this->assertNull(PosProductAttributes::depositId(null));
    }
}
