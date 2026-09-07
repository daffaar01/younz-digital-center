<?php

namespace Tests\Unit;

use App\Ai\OrderIntakeParser;
use PHPUnit\Framework\TestCase;

class OrderIntakeParserTest extends TestCase
{
    public function test_it_extracts_print_specification_without_inventing_price(): void
    {
        $result = (new OrderIntakeParser)->parse('Print dua rangkap warna A4 bolak-balik dijilid, ambil sore.');

        $this->assertSame('print', $result['service']);
        $this->assertSame('A4', $result['paper_size']);
        $this->assertSame('color', $result['color_mode']);
        $this->assertSame('duplex', $result['sides']);
        $this->assertSame(2, $result['copies']);
        $this->assertSame('jilid', $result['finishing']);
        $this->assertFalse($result['requires_follow_up']);
        $this->assertArrayNotHasKey('price', $result);
    }
}
