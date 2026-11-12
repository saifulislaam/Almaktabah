<?php
/**
 * I18n — language detection, switching, and translation lookup.
 *
 * Scope note: this translates the site's chrome — header, sidebar, footer,
 * and the homepage — plus full RTL layout support for Arabic and Urdu.
 * Deeper content (admin panel, long-form settings pages, flash/validation
 * messages) intentionally stays English-only for now; translating every
 * string across 70+ files is a much larger effort than the site's core
 * navigable surface, and t() falls back to the English/default string
 * cleanly wherever a key isn't translated, so nothing breaks or shows
 * a blank — it just isn't localized yet. languages/*.php are the place to
 * extend coverage later.
 */
class I18n
{
    public const SUPPORTED = [
        'en' => ['label' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        'ar' => ['label' => 'Arabic',  'native' => 'العربية',  'dir' => 'rtl'],
        'ur' => ['label' => 'Urdu',    'native' => 'اردو',     'dir' => 'rtl'],
    ];

    private static array $strings = [];
    private static string $lang = 'en';
    private static bool $booted = false;

    public static function init(): void
    {
        if (self::$booted) return;
        self::$booted = true;
        self::$lang = self::resolveLang();
        $file = ROOT_PATH . '/languages/' . self::$lang . '.php';
        self::$strings = is_file($file) ? require $file : [];
    }

    private static function resolveLang(): string
    {
        if (!empty($_SESSION['language_preference'])) {
            return self::validOrDefault($_SESSION['language_preference']);
        }
        if (!empty($_COOKIE['almaktabah_lang'])) {
            return self::validOrDefault($_COOKIE['almaktabah_lang']);
        }
        return 'en';
    }

    public static function validOrDefault(?string $lang): string
    {
        return $lang !== null && isset(self::SUPPORTED[$lang]) ? $lang : 'en';
    }

    public static function current(): string
    {
        self::init();
        return self::$lang;
    }

    public static function dir(): string
    {
        self::init();
        return self::SUPPORTED[self::$lang]['dir'] ?? 'ltr';
    }

    public static function isRtl(): bool
    {
        return self::dir() === 'rtl';
    }

    public static function t(string $key, ?string $default = null): string
    {
        self::init();
        return self::$strings[$key] ?? $default ?? $key;
    }

    /** Persists the choice: a 1-year cookie for everyone, plus the account itself if logged in. */
    public static function setLanguage(string $lang): string
    {
        $lang = self::validOrDefault($lang);
        setcookie('almaktabah_lang', $lang, time() + 365 * 86400, '/', '', false, false);
        $_SESSION['language_preference'] = $lang;
        if (!empty($_SESSION['user_id'])) {
            Database::execute('UPDATE users SET language_preference = ? WHERE id = ?', [$lang, $_SESSION['user_id']]);
        }
        self::$booted = false; // force re-init on next call so the switch takes effect immediately
        return $lang;
    }
}
