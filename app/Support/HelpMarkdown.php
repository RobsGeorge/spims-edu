<?php

namespace App\Support;

use League\CommonMark\CommonMarkConverter;

/**
 * Renders help Markdown to sanitized HTML (allowlisted tags only).
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
    ];

    private CommonMarkConverter $converter;

    public function __construct(?CommonMarkConverter $converter = null)
    {
        $this->converter = $converter ?? new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function toHtml(string $markdown): string
    {
        $html = $this->converter->convert($markdown)->getContent();

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
}
