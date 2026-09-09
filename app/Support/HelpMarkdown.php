<?php

namespace App\Support;

use League\CommonMark\ConverterInterface;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Renders help / system-docs Markdown to sanitized HTML (allowlisted tags only).
 *
 * GitHub-flavored tables become real <table> markup, then get wrapped in
 * .spims-table-wrap so the design-system table CSS can style them.
 */
class HelpMarkdown
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'ul', 'ol', 'li',
        'a', 'strong', 'em', 'b', 'i',
        'h2', 'h3', 'h4',
        'code', 'pre', 'blockquote',
        'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div',
    ];

    private ConverterInterface $converter;

    public function __construct(?ConverterInterface $converter = null)
    {
        $this->converter = $converter ?? new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function toHtml(string $markdown): string
    {
        $html = $this->converter->convert($markdown)->getContent();
        $html = $this->decorateTables($html);

        return $this->sanitize($html);
    }

    public function sanitize(string $html): string
    {
        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $clean = strip_tags($html, $allowed);

        // Drop event handlers and javascript: URLs from remaining attributes.
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace(
            '/\s(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i',
            '',
            $clean
        ) ?? $clean;

        return trim($clean);
    }

    /**
     * Wrap each table in .spims-table-wrap and, for wide tables, add
     * data-label on cells so the mobile card fallback can label rows.
     */
    private function decorateTables(string $html): string
    {
        $decorated = preg_replace_callback(
            '/<table\b[^>]*>.*?<\/table>/is',
            function (array $match): string {
                $table = $match[0];
                $headers = $this->tableHeaders($table);
                $colCount = $headers !== []
                    ? count($headers)
                    : $this->firstRowCellCount($table);

                if ($colCount > 4 && $headers !== []) {
                    $table = $this->addCellLabels($table, $headers);
                }

                $table = $this->ensureTableClass($table);
                $wrapClass = $colCount > 4
                    ? 'spims-table-wrap spims-table-wrap--cards'
                    : 'spims-table-wrap';

                return '<div class="'.$wrapClass.'">'.$table.'</div>';
            },
            $html
        );

        return is_string($decorated) ? $decorated : $html;
    }

    /** @return list<string> */
    private function tableHeaders(string $table): array
    {
        if (! preg_match('/<thead\b[^>]*>.*?<\/thead>/is', $table, $thead)) {
            return [];
        }

        preg_match_all('/<th\b[^>]*>(.*?)<\/th>/is', $thead[0], $cells);

        return array_map(
            static fn (string $cell): string => trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $cells[1] ?? []
        );
    }

    private function firstRowCellCount(string $table): int
    {
        if (! preg_match('/<tr\b[^>]*>.*?<\/tr>/is', $table, $row)) {
            return 0;
        }

        preg_match_all('/<t[dh]\b/i', $row[0], $cells);

        return count($cells[0] ?? []);
    }

    /**
     * @param  list<string>  $headers
     */
    private function addCellLabels(string $table, array $headers): string
    {
        $index = 0;
        $count = count($headers);

        $labelled = preg_replace_callback(
            '/<td\b([^>]*)>/i',
            function (array $td) use ($headers, $count, &$index): string {
                $label = $headers[$index % $count] ?? '';
                $index++;
                $attrs = $td[1];
                if ($label === '' || str_contains(strtolower($attrs), 'data-label')) {
                    return $td[0];
                }

                return '<td'.$attrs.' data-label="'.e($label).'">';
            },
            $table
        );

        return is_string($labelled) ? $labelled : $table;
    }

    private function ensureTableClass(string $table): string
    {
        if (preg_match('/<table\b[^>]*\bclass=/i', $table) === 1) {
            return preg_replace(
                '/(<table\b[^>]*\bclass=")([^"]*)(")/i',
                '$1$2 help-article-table$3',
                $table,
                1
            ) ?? $table;
        }

        return preg_replace('/<table\b/i', '<table class="help-article-table"', $table, 1) ?? $table;
    }
}
