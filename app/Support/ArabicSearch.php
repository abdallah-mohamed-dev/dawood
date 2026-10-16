<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Search that ignores the Arabic spelling differences people type
 * interchangeably: أ/إ/آ/ٱ → ا · ة → ه · ى → ي · ؤ → و · ئ → ي, plus
 * tashkeel and tatweel dropped. "احمد" finds "أحمد", "مكتبه" finds "مكتبة".
 *
 * Both sides are folded the same way: the typed term in PHP (fold()), the
 * stored column in SQL (a chain of REPLACE() — plain SQLite, nothing to
 * register on the connection). resources/js/app.js normalizeArabic() does the
 * same for searches that run in the browser; keep the three in step.
 */
final class ArabicSearch
{
    /** @var array<string, string> */
    private const LETTERS = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
        'ؤ' => 'و',
        'ئ' => 'ي',
    ];

    /** Tashkeel (fathatan … sukun) and tatweel. */
    private const MARKS = ["\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}", "\u{0650}", "\u{0651}", "\u{0652}", "\u{0640}"];

    public static function fold(string $text): string
    {
        return mb_strtolower(strtr(str_replace(self::MARKS, '', $text), self::LETTERS));
    }

    /**
     * The same folding as SQL, around an already-wrapped column expression.
     * Only fixed Arabic letters are inlined — never user input.
     */
    public static function sqlFold(string $expression): string
    {
        foreach (self::LETTERS as $from => $to) {
            $expression = "replace({$expression}, '{$from}', '{$to}')";
        }

        foreach (self::MARKS as $mark) {
            $expression = "replace({$expression}, '{$mark}', '')";
        }

        return $expression;
    }

    /** Whether $haystack contains $needle, ignoring the differences above. */
    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains(self::fold($haystack), self::fold($needle));
    }

    /**
     * Adds whereArabicLike() / orWhereArabicLike() to every query builder
     * (Eloquent builders forward to them too).
     */
    public static function registerMacros(): void
    {
        Builder::macro('whereArabicLike', function (string $column, string $term, string $boolean = 'and') {
            /** @var Builder $this */
            return $this->whereRaw(
                ArabicSearch::sqlFold($this->getGrammar()->wrap($column)).' like ?',
                ['%'.ArabicSearch::fold($term).'%'],
                $boolean,
            );
        });

        Builder::macro('orWhereArabicLike', function (string $column, string $term) {
            /** @var Builder $this */
            return $this->whereArabicLike($column, $term, 'or');
        });
    }
}
