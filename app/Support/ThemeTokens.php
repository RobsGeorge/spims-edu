<?php

namespace App\Support;

/**
 * Sacred Academic design tokens — default SPIMS branding (DESIGN.md).
 * Cool near-white field + liturgical burgundy spine + academic gold accent.
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
            ],
            'dark' => [
                'bg1' => '#0d1322',
                'bg2' => '#151b2b',
                'bg3' => '#191f2f',
                'surface' => '#191f2f',
                'surfaceLow' => '#151b2b',
                'surfaceBorder' => 'rgba(85, 66, 69, 0.85)',
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
            ],
        ];
    }

    /**
     * Map stored/default mode tokens onto CSS custom properties.
     *
     * @param  array<string, string>  $tokens
     * @return array<string, string>
     */
    public static function toCssVariables(array $tokens): array
    {
        $map = [
            'bg1' => '--color-bg-1',
            'bg2' => '--color-bg-2',
            'bg3' => '--color-bg-3',
            'surface' => '--color-surface',
            'surfaceLow' => '--color-surface-low',
            'surfaceBorder' => '--color-surface-border',
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
        ];

        $vars = [];
        foreach ($map as $key => $cssVar) {
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
        $lines[] = '@media (prefers-color-scheme: dark) {';
        $lines[] = '    body.theme-system {';
        foreach ($dark as $prop => $value) {
            $lines[] = '        '.$prop.': '.$value.';';
        }
        $lines[] = '    }';
        $lines[] = '}';

        return implode("\n", $lines);
    }
}
