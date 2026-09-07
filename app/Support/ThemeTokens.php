<?php

namespace App\Support;

/**
 * Sacred Academic design tokens — default SPIMS branding (DESIGN.md).
 * Cool near-white field + liturgical burgundy spine + academic gold accent.
 *
 * Gold (#eac167 / #e9c16d) is accent only — never primary or body text.
 * Keep in sync with public/css/spims-theme.css.
 */
final class ThemeTokens
{
    /**
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function defaults(): array
    {
        return [
            'light' => [
                'bg1' => '#f8f9ff',
                'bg2' => '#eff4ff',
                'bg3' => '#e6eeff',
                'surface' => '#ffffff',
                'surfaceLow' => '#eff4ff',
                'surfaceBorder' => 'rgba(219, 192, 196, 0.55)',
                'hairline' => 'rgba(219, 192, 196, 0.65)',
                'title' => '#5d0326',
                'titleAccent' => '#380014',
                'text' => '#0b1c30',
                'textMuted' => '#554245',
                'link' => '#5d0326',
                'primary' => '#5d0326',
                'primaryHover' => '#380014',
                'primaryText' => '#ffffff',
                'accent' => '#eac167',
                'accentText' => '#251a00',
                'navBg' => 'rgba(248, 249, 255, 0.92)',
                'navBorder' => 'rgba(219, 192, 196, 0.45)',
                'navText' => '#0b1c30',
                'navActive' => '#812340',
                'navActiveBg' => '#ffd9df',
                'success' => '#10b981',
                'warning' => '#f59e0b',
                'danger' => '#ef4444',
                'shadow' => '0 4px 20px rgba(0, 0, 0, 0.05)',
                'shadowLift' => '0 6px 24px rgba(0, 0, 0, 0.08)',
            ],
            'dark' => [
                'bg1' => '#0d1322',
                'bg2' => '#151b2b',
                'bg3' => '#191f2f',
                'surface' => '#191f2f',
                'surfaceLow' => '#151b2b',
                'surfaceBorder' => 'rgba(85, 66, 69, 0.85)',
                'hairline' => 'rgba(219, 192, 196, 0.28)',
                'title' => '#ffb1c0',
                'titleAccent' => '#e9c16d',
                'text' => '#dde2f8',
                'textMuted' => '#dbc0c4',
                'link' => '#ffb1c0',
                'primary' => '#ffb1c0',
                'primaryHover' => '#ffd9df',
                'primaryText' => '#380014',
                'accent' => '#e9c16d',
                'accentText' => '#251a00',
                'navBg' => 'rgba(13, 19, 34, 0.92)',
                'navBorder' => 'rgba(85, 66, 69, 0.7)',
                'navText' => '#dde2f8',
                'navActive' => '#ffb1c0',
                'navActiveBg' => 'rgba(123, 30, 59, 0.45)',
                'success' => '#10b981',
                'warning' => '#f59e0b',
                'danger' => '#ef4444',
                'shadow' => '0 4px 20px rgba(0, 0, 0, 0.3)',
                'shadowLift' => '0 6px 24px rgba(0, 0, 0, 0.38)',
            ],
        ];
    }

    /**
     * Editor groups for the Theme studio. Keys match defaults() per mode.
     *
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'field' => ['bg1', 'bg2', 'bg3', 'surface', 'surfaceLow', 'surfaceBorder', 'hairline'],
            'type' => ['title', 'titleAccent', 'text', 'textMuted', 'link'],
            'action' => ['primary', 'primaryHover', 'primaryText', 'accent', 'accentText'],
            'nav' => ['navBg', 'navBorder', 'navText', 'navActive', 'navActiveBg'],
            'semantic' => ['success', 'warning', 'danger'],
            'chrome' => ['shadow', 'shadowLift'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::defaults()['light']);
    }

    public static function isColorToken(string $key): bool
    {
        $sample = self::defaults()['light'][$key] ?? '';

        return is_string($sample) && (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $sample);
    }

    /**
     * Token key → CSS custom property. Keep in sync with public/css/spims-theme.css.
     *
     * @return array<string, string>
     */
    public static function cssPropertyMap(): array
    {
        return [
            'bg1' => '--color-bg-1',
            'bg2' => '--color-bg-2',
            'bg3' => '--color-bg-3',
            'surface' => '--color-surface',
            'surfaceLow' => '--color-surface-low',
            'surfaceBorder' => '--color-surface-border',
            'hairline' => '--color-hairline',
            'title' => '--color-title',
            'titleAccent' => '--color-title-accent',
            'text' => '--color-text',
            'textMuted' => '--color-text-muted',
            'link' => '--color-link',
            'primary' => '--color-primary',
            'primaryHover' => '--color-primary-hover',
            'primaryText' => '--color-primary-text',
            'accent' => '--color-accent',
            'accentText' => '--color-accent-text',
            'navBg' => '--color-nav-bg',
            'navBorder' => '--color-nav-border',
            'navText' => '--color-nav-text',
            'navActive' => '--color-nav-active',
            'navActiveBg' => '--color-nav-active-bg',
            'success' => '--color-success',
            'warning' => '--color-warning',
            'danger' => '--color-danger',
            'shadow' => '--shadow-soft',
            'shadowLift' => '--shadow-lift',
        ];
    }

    public static function cssVariable(string $key): ?string
    {
        return self::cssPropertyMap()[$key] ?? null;
    }

    /**
     * Map stored/default mode tokens onto CSS custom properties.
     *
     * @param  array<string, string>  $tokens
     * @return array<string, string>
     */
    public static function toCssVariables(array $tokens): array
    {
        $vars = [];
        foreach (self::cssPropertyMap() as $key => $cssVar) {
            if (isset($tokens[$key]) && is_string($tokens[$key]) && $tokens[$key] !== '') {
                $vars[$cssVar] = $tokens[$key];
            }
        }

        if (isset($vars['--color-bg-1'], $vars['--color-bg-2'], $vars['--color-bg-3'])) {
            $vars['--gradient-bg'] = sprintf(
                'linear-gradient(160deg, %s 0%%, %s 48%%, %s 100%%)',
                $vars['--color-bg-1'],
                $vars['--color-bg-2'],
                $vars['--color-bg-3']
            );
        }

        return $vars;
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        return [
            'light' => array_merge($defaults['light'], is_array($stored['light'] ?? null) ? $stored['light'] : []),
            'dark' => array_merge($defaults['dark'], is_array($stored['dark'] ?? null) ? $stored['dark'] : []),
        ];
    }

    /**
     * Inline <style> block overriding CSS defaults from the active theme.
     *
     * @param  array<string, mixed>|null  $stored
     */
    public static function inlineStyleBlock(?array $stored): string
    {
        $resolved = self::resolve($stored);
        $light = self::toCssVariables($resolved['light']);
        $dark = self::toCssVariables($resolved['dark']);

        $lines = [];
        $lines[] = 'body.theme-light, body.theme-system {';
        foreach ($light as $prop => $value) {
            $lines[] = '    '.$prop.': '.$value.';';
        }
        $lines[] = '}';
        $lines[] = 'body.theme-dark {';
        foreach ($dark as $prop => $value) {
            $lines[] = '    '.$prop.': '.$value.';';
        }
        $lines[] = '}';
        // SYSTEM theme: body.theme-system follows OS via prefers-color-scheme: dark
        $lines[] = '@media (prefers-color-scheme: dark) {';
        $lines[] = '    body.theme-system {';
        foreach ($dark as $prop => $value) {
            $lines[] = '        '.$prop.': '.$value.';';
        }
        $lines[] = '    }';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * WCAG 2 relative luminance for a 6-digit hex color.
     */
    public static function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            throw new \InvalidArgumentException('Hex color required.');
        }

        $channel = static function (float $c): float {
            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(hexdec(substr($hex, 0, 2)) / 255)
            + 0.7152 * $channel(hexdec(substr($hex, 2, 2)) / 255)
            + 0.0722 * $channel(hexdec(substr($hex, 4, 2)) / 255);
    }

    /**
     * WCAG 2 contrast ratio between two 6-digit hex colors.
     */
    public static function contrastRatio(string $foreground, string $background): float
    {
        $l1 = self::relativeLuminance($foreground);
        $l2 = self::relativeLuminance($background);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Locked Sacred Academic pairs that must meet WCAG AA (4.5:1 text, 3:1 large/accent).
     *
     * @return list<array{0: string, 1: string, 2: float, 3: string}>
     */
    public static function aaPairs(): array
    {
        $light = self::defaults()['light'];
        $dark = self::defaults()['dark'];

        return [
            [$light['text'], $light['bg1'], 4.5, 'light text on field'],
            [$light['title'], $light['bg1'], 4.5, 'light title on field'],
            [$light['textMuted'], $light['bg1'], 4.5, 'light muted on field'],
            [$light['primaryText'], $light['primary'], 4.5, 'light button label'],
            ['#f8f9ff', '#380014', 4.5, 'field on deep burgundy'],
            [$dark['text'], $dark['bg1'], 4.5, 'dark text on field'],
            [$dark['title'], $dark['bg1'], 4.5, 'dark title on field'],
            [$light['accent'], '#380014', 3.0, 'gold accent on burgundy (large)'],
        ];
    }
}
