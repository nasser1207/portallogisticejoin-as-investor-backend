<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    // ── Status constants ──────────────────────────────────────────────────────

    public const STATUS_PENDING       = 'pending';       // awaiting user receipt upload
    public const STATUS_ADMIN_PENDING = 'admin_pending'; // receipt uploaded, awaiting admin
    public const STATUS_APPROVED      = 'approved';      // admin accepted
    public const STATUS_REJECTED      = 'rejected';      // admin rejected, new invoice issued

    // ── Maintenance amounts by year ───────────────────────────────────────────

    public const YEARS = [
        1 => ['amount' => 1500.00, 'monthly' => 125.00],
        2 => ['amount' => 2700.00, 'monthly' => 225.00],
        3 => ['amount' => 3300.00, 'monthly' => 325.00],
    ];

    protected $fillable = [
        'contract_id',
        'user_id',
        'year',
        'amount',
        'monthly_amount',
        'due_date',
        'receipt_path',
        'status',
        'admin_notes',
        'parent_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date'       => 'date',
            'amount'         => 'decimal:2',
            'monthly_amount' => 'decimal:2',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function reissued(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

  

    public function toApiArray(): array
    {
        return [
            'id'                => $this->id,
            'contract_id'       => $this->contract_id,
            'user_id'           => $this->user_id,
            'year'              => $this->year,
            'amount'            => (float) $this->amount,
            'monthly_amount'    => (float) $this->monthly_amount,
            'due_date'          => $this->due_date?->toDateString(),
            'receipt_path'      => $this->receipt_path,
            'receipt_url'       => $this->receipt_path
                                    ? Storage::disk('public')->url($this->receipt_path)
                                    : null,
            'status'            => $this->status,
            'admin_notes'       => $this->admin_notes,
            'parent_invoice_id' => $this->parent_invoice_id,
            'created_at'        => $this->created_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
