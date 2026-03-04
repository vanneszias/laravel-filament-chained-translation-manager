<?php

declare(strict_types=1);

namespace Statikbe\FilamentTranslationManager;

class FilamentTranslationManager
{
    /** @var array<int, string> */
    public static array $locales = [];

    /**
     * @param  array<int, string>  $locales
     */
    public static function setLocales(array $locales): void
    {
        static::$locales = $locales;
    }

    /**
     * @return array<int, string>
     */
    public static function getLocales(): array
    {
        return static::$locales;
    }
}
