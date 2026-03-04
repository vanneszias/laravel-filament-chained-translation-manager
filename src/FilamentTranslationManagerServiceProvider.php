<?php

declare(strict_types=1);

namespace Statikbe\FilamentTranslationManager;

use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Statikbe\FilamentTranslationManager\Http\Livewire\TranslationEditForm;
use Statikbe\FilamentTranslationManager\Pages\TranslationManagerPage;
use Statikbe\FilamentTranslationManager\Widgets\TranslationStatusWidget;

class FilamentTranslationManagerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-translation-manager';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)->hasViews()->hasTranslations()->hasConfigFile();
    }

    public function packageBooted(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-translation-manager.php', 'filament-translation-manager');

        /** @var array<int, string>|null $supportedLocales */
        $supportedLocales = config(
            'filament-translation-manager.locales',
            config('filament-translation-manager.supported_locales'),
        );

        if ($supportedLocales === null || $supportedLocales === []) {
            $supportedLocales = array_values(array_filter([
                (string) config('app.locale', ''),
                (string) config('app.fallback_locale', ''),
            ]));
        }

        FilamentTranslationManager::setLocales($supportedLocales);

        // Livewire v4 (Filament v5+) auto-discovers components by namespace convention,
        // and the livewire.finder binding used by ::component() no longer exists.
        // Manual registration is only needed for Livewire v3 (Filament v4).
        if (\Composer\InstalledVersions::satisfies(new \Composer\Semver\VersionParser, 'livewire/livewire', '^3')) {
            Livewire::component('translation-manager-page', TranslationManagerPage::class);
            Livewire::component('translation-edit-form', TranslationEditForm::class);
            Livewire::component('translation-status', TranslationStatusWidget::class);
        }
    }
}
