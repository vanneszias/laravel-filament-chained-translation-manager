<?php

declare(strict_types=1);

namespace Statikbe\FilamentTranslationManager\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statikbe\LaravelChainedTranslator\ChainedTranslationManager;

class TranslationDataService
{
    public function __construct(
        private readonly ChainedTranslationManager $translationManager,
    ) {}

    /**
     * @return array<int, string>
     */
    public function getTranslationGroups(): array
    {
        /** @var array<int, string> $groups */
        $groups = [];

        $rawGroups = $this->translationManager->getTranslationGroups();

        foreach ($rawGroups as $group) {
            if (! is_string($group)) {
                continue;
            }

            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Load all translations for the given locales and groups.
     *
     * @param  array<int, string>  $locales
     * @param  array<int, string>  $groups
     * @return array<int, array<string, mixed>>
     */
    public function loadTranslations(array $locales, array $groups): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $data = [];

        foreach ($locales as $locale) {
            foreach ($groups as $group) {
                $this->addTranslationsToData($data, $locale, $group);
            }
        }

        return array_values($data);
    }

    /**
     * @param  array<string, array<string, mixed>>  $data
     */
    private function addTranslationsToData(array &$data, string $locale, string $group): void
    {
        /** @var array<string, string> $translations */
        $translations = $this->translationManager->getTranslationsForGroup($locale, $group);

        foreach ($translations as $key => $translation) {
            $dataKey = $group.'.'.$key;
            if (! array_key_exists($dataKey, $data)) {
                $data[$dataKey] = [
                    'title' => $group.' - '.$key,
                    'type' => 'group',
                    'group' => $group,
                    'translation_key' => $key,
                    'translations' => [],
                ];
            }

            /** @var array<string, string> $existingTranslations */
            $existingTranslations = $data[$dataKey]['translations'];
            $existingTranslations[$locale] = $translation;
            $data[$dataKey]['translations'] = $existingTranslations;
        }
    }

    /**
     * Apply search filter to a collection of translations.
     *
     * @param  Collection<int, array<string, mixed>>  $translations
     * @return Collection<int, array<string, mixed>>
     */
    public function applySearchFilter(Collection $translations, string $searchTerm): Collection
    {
        return $translations->filter(static function (mixed $translationItem) use ($searchTerm): bool {
            if (Str::contains((string) $translationItem['title'], $searchTerm, true)) {
                return true;
            }

            /** @var array<string, string|null> $itemTranslations */
            $itemTranslations = $translationItem['translations'];

            foreach ($itemTranslations as $translation) {
                if (Str::contains((string) $translation, $searchTerm, true)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Apply missing-translations filter to a collection of translations.
     *
     * @param  Collection<int, array<string, mixed>>  $translations
     * @param  array<int, string>  $filteredLocales
     * @return Collection<int, array<string, mixed>>
     */
    public function applyMissingFilter(Collection $translations, array $filteredLocales): Collection
    {
        return $translations->filter(function (mixed $translationItem) use ($filteredLocales): bool {
            /** @var array<string, string|null> $itemTranslations */
            $itemTranslations = $translationItem['translations'];

            return $this->isTranslationMissing($itemTranslations, $filteredLocales);
        });
    }

    /**
     * Apply group filter to a collection of translations.
     *
     * @param  Collection<int, array<string, mixed>>  $translations
     * @param  array<int, string>  $selectedGroups
     * @return Collection<int, array<string, mixed>>
     */
    public function applyGroupFilter(Collection $translations, array $selectedGroups): Collection
    {
        return $translations->filter(static fn (mixed $translationItem): bool => in_array(
            (string) $translationItem['group'],
            $selectedGroups,
            true,
        ));
    }

    /**
     * Paginate a collection of translations.
     *
     * @param  Collection<int, array<string, mixed>>  $translations
     * @return array{items: Collection<int, array<string, mixed>>, total: int, paged: int}
     */
    public function paginate(Collection $translations, int $pageCounter, int $pageLimit): array
    {
        /** @var Collection<int, array<string, mixed>> $sorted */
        $sorted = $translations->sortBy([
            ['group', 'asc'],
            ['key', 'asc'],
        ]);

        $offset = $pageCounter > 1 ? ($pageCounter - 1) * $pageLimit : 0;
        $total = $sorted->count();

        /** @var Collection<int, array<string, mixed>> $items */
        $items = $sorted->slice($offset, $pageLimit);

        return [
            'items' => $items,
            'total' => $total,
            'paged' => $offset + $pageLimit,
        ];
    }

    /**
     * Count missing translations across a collection.
     *
     * @param  Collection<int, array<string, mixed>>  $translations
     * @param  array<int, string>  $filteredLocales
     */
    public function countMissing(Collection $translations, array $filteredLocales): int
    {
        return (int) $translations->reduce(
            function (int $carry, mixed $translationItem) use ($filteredLocales): int {
                /** @var array<string, string|null> $itemTranslations */
                $itemTranslations = $translationItem['translations'];

                return $carry + ($this->isTranslationMissing($itemTranslations, $filteredLocales) ? 1 : 0);
            },
            0,
        );
    }

    /**
     * Check if a translation is missing for any of the filtered locales.
     *
     * @param  array<string, string|null>  $translations
     * @param  array<int, string>  $filteredLocales
     */
    public function isTranslationMissing(array $translations, array $filteredLocales): bool
    {
        if (count(array_intersect($filteredLocales, array_keys($translations))) !== count($filteredLocales)) {
            return true;
        }

        foreach ($translations as $locale => $translation) {
            if (! in_array($locale, $filteredLocales, true)) {
                continue;
            }

            if ($translation === null || $translation === '' || trim($translation) === '') {
                return true;
            }
        }

        return false;
    }
}
