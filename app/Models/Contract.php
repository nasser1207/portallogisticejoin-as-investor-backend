<?php

namespace App\Models;

use App\Contracts\Signable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model implements Signable
{
    public const TYPE_SALE   = 'sale';
    public const TYPE_RENTAL = 'rental';

    // ── Status constants ──────────────────────────────────────────────────────

    public const STATUS_DRAFT           = 'draft';
    public const STATUS_SENT            = 'sent';
    public const STATUS_NAFATH_PENDING  = 'nafath_pending';
    public const STATUS_NAFATH_APPROVED = 'nafath_approved';
    public const STATUS_ADMIN_PENDING   = 'admin_pending';
    public const STATUS_APPROVED        = 'approved';
    public const STATUS_REJECTED        = 'rejected';

    public const STATUS_NEED_TO_PAY    = 'need_to_pay';
    public const STATUS_RECEIPT_REVIEW = 'receipt_review';
    public const STATUS_ACCEPTED       = 'accepted';

    // ── Business constants ────────────────────────────────────────────────────

    public const ACTIVATION_DAYS     = 35;
    public const PAYMENT_MONTHS      = 12;
    public const FULL_PRICE          = 6600;
    public const PAYMENT_WINDOW_DAYS = 60;
    public const RENT_RATE           = 0.10;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'file_path',
        'payment_receipt_path',
        'sale_receipt_path',
        'status',
        'nafath_reference',
        'admin_id',
        'approved_at',
        'total_amount',
        'monthly_payment_amount',
        'total_amount_paid',
        'timer_started_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at'            => 'datetime',
            'timer_started_at'       => 'datetime',
            'total_amount'           => 'decimal:2',
            'monthly_payment_amount' => 'decimal:2',
            'total_amount_paid'      => 'decimal:2',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('month_number');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('year');
    }

    // ── Signable interface ────────────────────────────────────────────────────

    public function getSignableId(): int
    {
        return $this->id;
    }

    public function getSignableType(): string
    {
        return 'contract';
    }

    public function getSignableFilePath(): string
    {
        $clean = trim((string) $this->file_path);
        return "/home/portallogist/public_html/portallogistice/build/storage/{$clean}";
    }

    public function getSignableNationalId(): string
    {
        return (string) ($this->user?->national_id ?? '');
    }

    public function onNafathApproved(): void
    {
        $this->update(['status' => self::STATUS_NAFATH_APPROVED]);
    }

    public function onSigningComplete(): void
    {
        $this->update(['status' => self::STATUS_ADMIN_PENDING]);
    }

    public function onNafathRejected(): void
    {
        // Reset to sent so user can retry
        $this->update(['status' => self::STATUS_SENT]);
    }

    public function storeNafathReference(string $requestId): void
    {
        $this->update([
            'nafath_reference' => $requestId,
            'status'           => self::STATUS_NAFATH_PENDING,
        ]);
    }

    public function getNafathReference(): ?string
    {
        return $this->nafath_reference;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function activationDate(): ?\Carbon\Carbon
    {
        return $this->approved_at?->copy()->addDays(self::ACTIVATION_DAYS);
    }

    public function isActivated(): bool
    {
        $date = $this->activationDate();
        return $date !== null && now()->gte($date);
    }

    public function paymentWindowDaysLeft(): ?int
    {
        if (! $this->timer_started_at) return null;
        $deadline = $this->timer_started_at->copy()->addDays(self::PAYMENT_WINDOW_DAYS);
        return (int) now()->diffInDays($deadline, false);
    }

    public function paymentWindowExpired(): bool
    {
        $daysLeft = $this->paymentWindowDaysLeft();
        return $daysLeft !== null && $daysLeft < 0;
    }

    public function calculateMonthlyRent(): float
    {
        return round((float) $this->total_amount_paid * self::RENT_RATE, 2);
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->total_amount_paid >= self::FULL_PRICE;
    }
}
