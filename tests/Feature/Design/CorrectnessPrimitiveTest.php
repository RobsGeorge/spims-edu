<?php

namespace Tests\Feature\Design;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CorrectnessPrimitiveTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ badge

    #[Test]
    public function badge_emits_localized_label_in_english(): void
    {
        App::setLocale('en');
        $html = view('components.badge', ['value' => InvoiceStatus::Paid])->render();
        $this->assertStringContainsString('Paid', $html);
        $this->assertStringNotContainsString('PAID', $html);
    }

    #[Test]
    public function badge_emits_localized_label_in_arabic(): void
    {
        App::setLocale('ar');
        $html = view('components.badge', ['value' => InvoiceStatus::Paid])->render();
        $this->assertStringContainsString('مدفوع', $html);
    }

    #[Test]
    public function badge_emits_localized_label_in_french(): void
    {
        App::setLocale('fr');
        $html = view('components.badge', ['value' => InvoiceStatus::Paid])->render();
        $this->assertStringContainsString('Payé', $html);
    }

    #[Test]
    public function badge_throws_when_given_a_raw_string(): void
    {
        // Laravel wraps component exceptions in ViewException
        $this->expectException(\Throwable::class);
        view('components.badge', ['value' => 'PAID'])->render();
    }

    #[Test]
    public function badge_throws_when_given_a_plain_integer(): void
    {
        $this->expectException(\Throwable::class);
        view('components.badge', ['value' => 1])->render();
    }

    // ------------------------------------------------------------------ money

    #[Test]
    public function money_formats_minor_units_as_human_readable_amount(): void
    {
        $html = view('components.money', [
            'minor'    => 123456,
            'currency' => Currency::Usd,
        ])->render();

        $this->assertStringNotContainsString('123456', $html, 'Raw minor integer must not appear in output');
        // Money::format() returns "USD 1,234.56" — confirm the currency symbol is present
        $this->assertStringContainsString('USD', $html, 'Currency symbol must appear in output');
    }

    #[Test]
    public function money_renders_without_error_under_arabic_locale(): void
    {
        App::setLocale('ar');
        $html = view('components.money', [
            'minor'    => 5000,
            'currency' => Currency::Egp,
        ])->render();

        $this->assertStringContainsString('50', $html);
    }

    #[Test]
    public function money_throws_when_minor_is_a_string(): void
    {
        $this->expectException(\Throwable::class);
        view('components.money', [
            'minor'    => '123456',
            'currency' => Currency::Usd,
        ])->render();
    }

    // ------------------------------------------------------------------ icon

    #[Test]
    public function icon_renders_correct_bi_class_for_known_key(): void
    {
        $html = view('components.icon', ['name' => 'course'])->render();
        $this->assertStringContainsString('bi-journal-text', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    #[Test]
    public function icon_throws_for_unknown_concept_key(): void
    {
        $this->expectException(\Throwable::class);
        view('components.icon', ['name' => 'nonexistent-concept'])->render();
    }

    #[Test]
    public function icon_applies_size_classes(): void
    {
        $sm = view('components.icon', ['name' => 'student', 'size' => 'sm'])->render();
        $lg = view('components.icon', ['name' => 'student', 'size' => 'lg'])->render();

        $this->assertStringContainsString('spims-icon-sm', $sm);
        $this->assertStringContainsString('spims-icon-lg', $lg);
    }
}
