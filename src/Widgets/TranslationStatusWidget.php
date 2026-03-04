<?php

declare(strict_types=1);

namespace Statikbe\FilamentTranslationManager\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

class TranslationStatusWidget extends Widget
{
    /** @var view-string */
    protected string $view = 'filament-translation-manager::widgets.translation-status';

    public static function getSort(): int
    {
        /** @var mixed $sort */
        $sort = config('filament-translation-manager.widget.sort');

        return is_int($sort) ? $sort : -1;
    }

    public static function canView(): bool
    {
        /** @var mixed $gate */
        $gate = config('filament-translation-manager.widget.gate', config('filament-translation-manager.access.gate'));
        if (is_string($gate) && $gate !== '') {
            return Gate::allows($gate);
        }

        return true;
    }

    protected function getViewData(): array
    {
        return [
            'missingTranslations' => 10,
        ];
    }
}
