<?php

namespace App\Support\Ui;

use InvalidArgumentException;

/**
 * Controlled Bootstrap Icon map for <x-icon>. Keep in lockstep with
 * docs/design-system.md "Icon Vocabulary".
 */
final class IconVocabulary
{
    /** @var array<string, string> */
    public const MAP = [
        'course' => 'bi-journal-text',
        'student' => 'bi-person',
        'enrollment' => 'bi-person-check',
        'grade' => 'bi-star-half',
        'finance' => 'bi-wallet2',
        'assessment' => 'bi-clipboard-check',
        'attendance' => 'bi-calendar-check',
        'credential' => 'bi-award',
        'settings' => 'bi-gear',
        'delete' => 'bi-trash',
        'add' => 'bi-plus-circle',
        'edit' => 'bi-pencil',
        'view' => 'bi-eye',
        'download' => 'bi-download',
        'warning' => 'bi-exclamation-triangle',
        'success' => 'bi-check-circle',
        'empty-state' => 'bi-inbox',
        'home' => 'bi-house',
        'catalog' => 'bi-grid',
        'learning' => 'bi-book-half',
        'teach' => 'bi-easel2',
        'academic' => 'bi-mortarboard',
        'admin' => 'bi-building-gear',
        'superadmin' => 'bi-shield-lock',
        'live' => 'bi-broadcast',
        'discussion' => 'bi-chat-dots',
        'announcement' => 'bi-megaphone',
        'notification' => 'bi-bell',
        'event' => 'bi-calendar-event',
        'quiz' => 'bi-ui-radios',
        'project' => 'bi-kanban',
        'survey' => 'bi-ui-checks',
        'advising' => 'bi-compass',
        'application' => 'bi-file-earmark-text',
        'report' => 'bi-graph-up',
        'people' => 'bi-people',
        'theme' => 'bi-palette',
        'translation' => 'bi-translate',
        'program' => 'bi-diagram-3',
        'offering' => 'bi-collection',
        'search' => 'bi-search',
        'inbox' => 'bi-inbox',
        'history' => 'bi-clock-history',
        'check-in' => 'bi-qr-code-scan',
        'roster' => 'bi-person-lines-fill',
        'content' => 'bi-folder2-open',
        'completion' => 'bi-flag',
        'transcript' => 'bi-file-text',
        'login' => 'bi-box-arrow-in-right',
        'register' => 'bi-person-plus',
        'lock' => 'bi-lock',
        'page' => 'bi-bookmark',
        'wallet' => 'bi-wallet2',
        'calendar' => 'bi-calendar3',
    ];

    public static function classFor(string $name): string
    {
        if (! array_key_exists($name, self::MAP)) {
            throw new InvalidArgumentException(
                "<x-icon> unknown concept key: '{$name}'. "
                .'Available: '.implode(', ', array_keys(self::MAP))
            );
        }

        return self::MAP[$name];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::MAP);
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::MAP);
    }
}
