<?php

namespace Tests\Unit;

use App\Models\CustomerRequestItem;
use PHPUnit\Framework\TestCase;

class CustomerRequestItemQuantityLabelTest extends TestCase
{
    private function line(array $attributes): CustomerRequestItem
    {
        return new CustomerRequestItem($attributes);
    }

    public function test_units_show_the_bare_number(): void
    {
        $this->assertSame('4', $this->line(['quantity' => 4, 'unit' => 'unit'])->quantityLabel());
        $this->assertSame('1.5', $this->line(['quantity' => 1.5, 'unit' => 'unit'])->quantityLabel());
    }

    public function test_cases_name_the_case_size(): void
    {
        $this->assertSame('2 cases of 6', $this->line(['quantity' => 2, 'unit' => 'case', 'case_units' => 6])->quantityLabel());
        $this->assertSame('1 case of 12', $this->line(['quantity' => 1, 'unit' => 'case', 'case_units' => 12])->quantityLabel());
    }

    public function test_cases_without_a_known_size(): void
    {
        $this->assertSame('1 case', $this->line(['quantity' => 1, 'unit' => 'case'])->quantityLabel());
        $this->assertSame('2 cases', $this->line(['quantity' => 2, 'unit' => 'case', 'case_units' => null])->quantityLabel());
    }
}
