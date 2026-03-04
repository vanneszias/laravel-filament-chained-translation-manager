<?php

declare(strict_types=1);

namespace Statikbe\FilamentTranslationManager\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Statikbe\FilamentTranslationManager\FilamentTranslationManager;
use Statikbe\FilamentTranslationManager\Http\Livewire\TranslationEditForm;
use Statikbe\FilamentTranslationManager\Services\TranslationDataService;

/**
 * @extends Page<\Filament\Pages\PageConfiguration>
 */
class TranslationManagerPage extends Page implements HasForms
{
    use InteractsWithForms;

    const PAGE_LIMIT = 20;

    /** @var array<int, string> */
    public array $groups = [];

    /** @var array<int, string> */
    public array $locales = [];

    /** @var Collection<int, array<string, mixed>> */
    public Collection $filteredTranslations;

    public string $searchTerm = '';

    public bool $onlyShowMissingTranslations = false;

    /** @var array<int, string> */
    public array $selectedGroups = [];

    /** @var array<int, string> */
    public array $selectedLocales = [];

    public int $pageCounter = 1;

    public int $pagedTranslations = 0;

    public int $totalFilteredTranslations = 0;

    public int $totalTranslations = 0;

    public int $totalMissingFilteredTranslations = 0;

    protected array $queryString = [
        'pageCounter' => ['except' => 1, 'as' => 'page'],
        'searchTerm' => ['as' => 'search', 'except' => ''],
        'onlyShowMissingTranslations' => ['except' => false, 'as' => 'showMissing'],
        'selectedGroups',
        'selectedLocales',
    ];

    protected $listeners = [TranslationEditForm::EVENT_TRANSLATIONS_SAVED => 'translationsSaved'];

    protected string $view = 'filament-translation-manager::pages.translation-manager-page';

    public static function shouldRegisterNavigation(): bool
    {
        /** @var mixed $gate */
        $gate = config('filament-translation-manager.gate', config('filament-translation-manager.access.gate'));
        if (is_string($gate) && $gate !== '') {
            return Gate::allows($gate);
        }

        return true;
    }

    public static function getNavigationGroup(): ?string
    {
        /** @var mixed $group */
        $group = config('filament-translation-manager.navigation_group');

        if (!is_string($group)) {
            return null;
        }

        $translated = trans($group);

        return is_string($translated) ? $translated : null;
    }

    public static function getNavigationLabel(): string
    {
        $translated = trans('filament-translation-manager::messages.title');

        return is_string($translated) ? $translated : '';
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        /** @var mixed $icon */
        $icon = config('filament-translation-manager.navigation_icon');

        return is_string($icon) ? $icon : null;
    }

    public static function getNavigationSort(): ?int
    {
        /** @var mixed $sort */
        $sort = config('filament-translation-manager.navigation_sort');

        return is_int($sort) ? $sort : null;
    }

    public function getTitle(): string
    {
        $translated = trans('filament-translation-manager::messages.title');

        return is_string($translated) ? $translated : '';
    }

    public function mount(): void
    {
        /** @var mixed $gate */
        $gate = config('filament-translation-manager.gate', config('filament-translation-manager.access.gate'));
        if (is_string($gate) && $gate !== '') {
            Gate::authorize($gate);
        }

        $service = app(TranslationDataService::class);

        $groups = $service->getTranslationGroups();

        /** @var array<int, string> $ignoreGroups */
        $ignoreGroups = config('filament-translation-manager.ignore_groups', []) ?? [];

        $this->groups = collect($groups)->diff($ignoreGroups)->values()->all();

        $this->locales = FilamentTranslationManager::getLocales();
        $this->selectedLocales = $this->locales;

        $this->filterTranslations();
    }

    public function form(Schema $schema): Schema
    {
        $searchPlaceholder = trans('filament-translation-manager::messages.search_term_placeholder');
        $groupsPlaceholder = trans('filament-translation-manager::messages.selected_groups_placeholder');
        $localesPlaceholder = trans('filament-translation-manager::messages.selected_languages_placeholder');
        $missingLabel = trans('filament-translation-manager::messages.only_show_missing_translations_lbl');

        return $schema->components([
            TextInput::make('searchTerm')
                ->hiddenLabel()
                ->placeholder(is_string($searchPlaceholder) ? $searchPlaceholder : '')
                ->prefixIcon('heroicon-o-magnifying-glass'),

            Select::make('selectedGroups')
                ->hiddenLabel()
                ->placeholder(is_string($groupsPlaceholder) ? $groupsPlaceholder : '')
                ->multiple()
                ->options(array_combine($this->groups, $this->groups)),

            Select::make('selectedLocales')
                ->hiddenLabel()
                ->placeholder(is_string($localesPlaceholder) ? $localesPlaceholder : '')
                ->multiple()
                ->options(array_combine($this->locales, $this->locales))
                ->columnSpan(1),

            Toggle::make('onlyShowMissingTranslations')
                ->label(is_string($missingLabel) ? $missingLabel : '')
                ->default(false),
        ])->columns(2);
    }

    public function filterTranslations(): void
    {
        $service = app(TranslationDataService::class);
        $filteredLocales = $this->selectedLocales !== [] ? $this->selectedLocales : $this->locales;

        $all = collect($service->loadTranslations($this->locales, $this->groups));
        $this->totalTranslations = $all->count();

        $filtered = $this->searchTerm ? $service->applySearchFilter($all, $this->searchTerm) : $all;

        if ($this->onlyShowMissingTranslations) {
            $filtered = $service->applyMissingFilter($filtered, $filteredLocales);
        }

        if ($this->selectedGroups !== []) {
            $filtered = $service->applyGroupFilter($filtered, $this->selectedGroups);
        }

        $this->totalMissingFilteredTranslations = $service->countMissing($filtered, $filteredLocales);

        $paginated = $service->paginate($filtered, $this->pageCounter, self::PAGE_LIMIT);
        $this->totalFilteredTranslations = $paginated['total'];
        $this->pagedTranslations = $paginated['paged'];
        $this->filteredTranslations = $paginated['items'];
    }

    public function submitFilters(): void
    {
        $this->pageCounter = 1;
        $this->filterTranslations();
    }

    public function previousPage(): void
    {
        if ($this->pageCounter > 1) {
            $this->pageCounter--;
            $this->filterTranslations();
        }
    }

    public function nextPage(): void
    {
        if (($this->pageCounter * self::PAGE_LIMIT) <= $this->totalFilteredTranslations) {
            $this->pageCounter++;
            $this->filterTranslations();
        }
    }

    public function translationsSaved(
        string $group,
        string $translationKey,
        array $newTranslation,
        ?array $initialTranslations = null,
    ): void {
        $service = app(TranslationDataService::class);
        $filteredLocales = $this->selectedLocales !== [] ? $this->selectedLocales : $this->locales;

        /** @var array<string, string|null> $oldTranslations */
        $oldTranslations = $initialTranslations ?? [];

        /** @var array<string, string|null> $newTranslations */
        $newTranslations = $newTranslation;

        $oldMissing = $service->isTranslationMissing($oldTranslations, $filteredLocales);
        $newMissing = $service->isTranslationMissing($newTranslations, $filteredLocales);

        if ($oldMissing && !$newMissing) {
            $this->totalMissingFilteredTranslations--;
        }

        if (!$oldMissing && $newMissing) {
            $this->totalMissingFilteredTranslations++;
        }
    }
}
