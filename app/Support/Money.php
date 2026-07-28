<?php

namespace App\Support;

use App\Enums\Currency;
use InvalidArgumentException;

final class Money
{
    public function __construct(
        public readonly int $minorUnits,
        public readonly Currency $currency,
    ) {}

    public static function fromMinor(int $minorUnits, Currency $currency): self
    {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money minor units cannot be negative.');
        }

        return new self($minorUnits, $currency);
    }

    public function format(): string
    {
        $major = $this->minorUnits / 100;
        $symbol = $this->currency === Currency::Egp ? 'EGP ' : 'USD ';

        return $symbol.number_format($major, 2);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
