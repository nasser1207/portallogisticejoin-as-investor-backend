<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    public const TYPE_SALE = 'sale';
    public const TYPE_RENTAL = 'rental';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_NAFATH_PENDING = 'nafath_pending';
    public const STATUS_NAFATH_APPROVED = 'nafath_approved';
    public const STATUS_ADMIN_PENDING = 'admin_pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'file_path',
        'status',
        'nafath_reference',
        'admin_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
