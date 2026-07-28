<?php

namespace Tests\Unit\Support;

use App\Enums\Currency;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    public function it_formats_minor_units_correctly(): void
    {
        $money = Money::fromMinor(15050, Currency::Usd);

        $this->assertSame('USD 150.50', $money->format());
    }

    #[Test]
    public function it_rejects_negative_minor_units(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinor(-1, Currency::Egp);
    }

    #[Test]
    public function it_adds_same_currency(): void
    {
        $a = Money::fromMinor(100, Currency::Egp);
        $b = Money::fromMinor(250, Currency::Egp);

        $this->assertSame(350, $a->add($b)->minorUnits);
    }

    #[Test]
    public function it_rejects_currency_mismatch_on_add(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinor(100, Currency::Usd)->add(Money::fromMinor(100, Currency::Egp));
    }
}
