<?php

declare(strict_types=1);

namespace App\Support;

class TextNormalizer
{
    /**
     * @var array<string, string>
     */
    private const PRINCIPAL_ALIASES = [
        'novel' => 'Novell',
        'novell pharma' => 'Novell',
        'sanbe farma' => 'Sanbe',
        'bernofarm' => 'Berno',
        'generik novell' => 'Novell Generik',
        'yarindo generik' => 'Yarindo Generik',
        'fahrenheit generik' => 'Fahrenheit Generik',
    ];

    /**
     * Collapses duplicate whitespace characters into single spaces.
     */
    public static function collapseWhitespace(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $trimmed = trim($value);

        return (string) preg_replace('/\s+/u', ' ', $trimmed);
    }

    /**
     * Normalizes principal name, trimming, converting empty/dash strings to null,
     * and resolving known aliases.
     */
    public static function normalizePrincipal(?string $value): ?string
    {
        $collapsed = self::collapseWhitespace($value);

        if ($collapsed === '' || preg_match('/^-+$/', $collapsed)) {
            return null;
        }

        $stripped = (string) preg_replace('/^(?:PT|CV|UD|PD|NV)\.?\s+/ui', '', $collapsed);

        if ($stripped === '' || preg_match('/^-+$/', $stripped)) {
            return null;
        }

        $lower = mb_strtolower($stripped);

        return self::PRINCIPAL_ALIASES[$lower] ?? $stripped;
    }
}
