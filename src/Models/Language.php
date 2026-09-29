<?php

namespace Agentoom\Core\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function boot()
    {
        // check if the current language that is updating or creating is set as default,
        // if it is set as default remove default true from all the other languages
        parent::boot();

        static::creating(function (Language $language) {
            static::ensureAtLeastOneActive($language);
            static::ensureDefaultIsActive($language);
        });

        static::created(function (Language $language) {
            if ($language->is_default) {
                Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
            } else {
                static::fixDefaultLanguageNotSet($language);
            }
        });

        static::updating(function (Language $language) {
            static::ensureAtLeastOneActive($language);
            static::ensureDefaultIsActive($language);

            if ($language->is_default) {
                Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
            } else {
                static::fixDefaultLanguageNotSet($language);
            }
        });
    }

    public static function fixDefaultLanguageNotSet(Language $language): void
    {
        if (Language::where('is_default', true)->count() === 0) {
            $language->update(['is_default' => true]);
        }
    }

    protected static function ensureAtLeastOneActive(Language $language): void
    {
        // If this is the only active language and we're trying to deactivate it, prevent it
        if (! $language->is_active) {
            $activeCount = Language::where('is_active', true)->where('id', '!=', $language->id)->count();
            if ($activeCount === 0) {
                $language->is_active = true;
            }
        }
    }

    protected static function ensureDefaultIsActive(Language $language): void
    {
        // Default language must always be active
        if ($language->is_default) {
            $language->is_active = true;
        }
    }

    protected static function newFactory(): LanguageFactory
    {
        return LanguageFactory::new();
    }
}
