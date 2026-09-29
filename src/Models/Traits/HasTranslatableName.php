<?php

namespace Agentoom\Core\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasTranslatableName
{
    /**
     * Scope a query to find a translatable name.
     */
    public function scopeWhereName(Builder $query, string|array $name): Builder
    {
        $locales = ['en', 'it', 'fr', 'es', 'de'];

        return $query->where(function ($q) use ($name, $locales) {
            $names = is_array($name) ? $name : [$name];

            foreach ($names as $key => $value) {
                if (in_array($key, $locales)) {
                    $q->orWhere("name->{$key}", $value);
                } else {
                    foreach ($locales as $locale) {
                        $q->orWhere("name->{$locale}", $value);
                    }
                }
            }
        });
    }

    /**
     * Scope a query to find a translatable name in a list of names.
     */
    public function scopeWhereNameIn(Builder $query, array $names): Builder
    {
        $locales = ['en', 'it', 'fr', 'es', 'de'];

        return $query->where(function ($q) use ($names, $locales) {
            foreach ($locales as $locale) {
                $values = [];
                foreach ($names as $name) {
                    if (is_array($name)) {
                        if (isset($name[$locale])) {
                            $values[] = $name[$locale];
                        }
                    } else {
                        $values[] = $name;
                    }
                }

                if (! empty($values)) {
                    $q->orWhereIn("name->{$locale}", $values);
                }
            }
        });
    }

    /**
     * Scope a query to search for a translatable name using LIKE.
     */
    public function scopeWhereNameLike(Builder $query, string|array $name): Builder
    {
        $locales = ['en', 'it', 'fr', 'es', 'de'];

        return $query->where(function ($q) use ($name, $locales) {
            $names = is_array($name) ? $name : [$name];

            foreach ($names as $key => $value) {
                if (in_array($key, $locales)) {
                    $q->orWhere("name->{$key}", 'like', $value);
                } else {
                    foreach ($locales as $locale) {
                        $q->orWhere("name->{$locale}", 'like', $value);
                    }
                }
            }
        });
    }

    /**
     * Get the translated name attribute.
     */
    public function getNameTranslatedAttribute(): string
    {
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        if (is_array($this->name)) {
            $nameArr = $this->name;
            $first = reset($nameArr);

            return $this->name[$locale]
                ?? $this->name[$fallback]
                ?? ($first ?: 'Untitled');
        }

        return $this->name ?: 'Untitled';
    }

    /**
     * Scope a query to order by translatable name.
     */
    public function scopeOrderByTranslatedName(Builder $query, string $direction = 'asc'): Builder
    {
        $locale = app()->getLocale();

        return $query->orderBy("name->{$locale}", $direction);
    }
}
