<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'driver',
        'api_key',
        'url',
    ];

    /**
     * Encrypt and decrypt the API key.
     */
    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Get the models associated with this provider.
     */
    public function models()
    {
        return $this->hasMany(ProviderModel::class);
    }
}
