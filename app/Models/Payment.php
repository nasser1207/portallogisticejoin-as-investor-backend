<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    public const STATUS_PENDING          = 'pending';
    public const STATUS_SENT             = 'sent';
    public const STATUS_RECEIVED         = 'received';
    public const STATUS_REPORTED_MISSING = 'reported_missing';

    protected $fillable = [
        'contract_id',
        'month_number',
        'amount',
        'due_date',
        'payment_date',
        'receipt_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date'     => 'date',
            'payment_date' => 'date',
            'amount'       => 'decimal:2',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'contract_id'  => $this->contract_id,
            'month_number' => $this->month_number,
            'amount'       => (float) $this->amount,
            'due_date'     => $this->due_date?->toDateString(),
            'payment_date' => $this->payment_date?->toDateString(),
            'receipt_path' => $this->receipt_path,
            'receipt_url'  => $this->receipt_path
                                ? Storage::disk('public')->url($this->receipt_path)
                                : null,
            'status'       => $this->status,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
        ];
    }
}