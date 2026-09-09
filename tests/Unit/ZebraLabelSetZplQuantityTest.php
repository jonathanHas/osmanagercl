<?php

namespace Tests\Unit;

use App\Models\ZebraLabel;
use PHPUnit\Framework\TestCase;

class ZebraLabelSetZplQuantityTest extends TestCase
{
    public function test_inserts_a_pq_command_when_none_is_present(): void
    {
        $zpl = ZebraLabel::setZplQuantity('^XA^FO50,50^FDHello^FS^XZ', 2);

        $this->assertSame('^XA^FO50,50^FDHello^FS^PQ2,0,0,Y^XZ', $zpl);
    }

    public function test_replaces_an_existing_pq_command(): void
    {
        $zpl = ZebraLabel::setZplQuantity('^XA^FDHi^FS^PQ7,0,1,Y^XZ', 3);

        $this->assertSame('^XA^FDHi^FS^PQ3,0,0,Y^XZ', $zpl);
    }

    /**
     * The replicate parameter (3rd) must be 0. These labels carry no ^SN serialisation,
     * so a non-zero value is meaningless and doubles output on some GX firmware.
     */
    public function test_replicate_parameter_is_zero(): void
    {
        $this->assertStringContainsString(
            '^PQ1,0,0,Y',
            ZebraLabel::setZplQuantity('^XA^FDx^FS^XZ', 1)
        );
    }

    /** Only the first ^PQ is rewritten, so a multi-block file stays deterministic. */
    public function test_only_the_first_pq_is_rewritten(): void
    {
        $zpl = ZebraLabel::setZplQuantity('^XA^FDa^FS^PQ1,0,0,N^XZ^XA^FDb^FS^PQ5,0,0,N^XZ', 4);

        $this->assertSame('^XA^FDa^FS^PQ4,0,0,Y^XZ^XA^FDb^FS^PQ5,0,0,N^XZ', $zpl);
    }

    public function test_trailing_whitespace_before_xz_is_handled(): void
    {
        $this->assertSame(
            '^XA^FDx^FS^PQ2,0,0,Y^XZ',
            ZebraLabel::setZplQuantity("^XA^FDx^FS^XZ\n", 2)
        );
    }

    public function test_extract_print_quantity_reads_the_pq_value(): void
    {
        $this->assertSame(6, ZebraLabel::extractPrintQuantity('^XA^FDx^FS^PQ6,0,0,Y^XZ'));
        $this->assertSame(1, ZebraLabel::extractPrintQuantity('^XA^FDx^FS^XZ'));
    }
}
