<?php

namespace Agentoom\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCreditTopup extends Model
{
    protected $fillable = [
        'user_id',
        'uuid',
        'token',
        'status',
        'user_email',
        'amount',
        'type',
        'payment_method',
        'payment_id',
        'amount_paid',
        'currency',
        'completed_at',
        'payment_received_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'payment_received_at' => 'datetime',
    ];

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
