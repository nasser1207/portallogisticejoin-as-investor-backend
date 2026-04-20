<?php

namespace App\Models;

use App\Contracts\Signable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InvestorRequest extends Model implements Signable
{
    public const TYPE_RENEW_CONTRACT = 'renew_contract';
    public const TYPE_SELL_BIKE      = 'sell_bike';
    public const TYPE_ADD_BIKE       = 'add_bike';

    public const STATUS_PENDING           = 'pending';
    public const STATUS_IN_REVIEW         = 'in_review';
    public const STATUS_APPROVED          = 'approved';
    public const STATUS_REJECTED          = 'rejected';
    public const STATUS_WHATSAPP_SENT     = 'whatsapp_sent';
    public const STATUS_INVOICE_SENT      = 'invoice_sent';      // admin deployed invoice, awaiting Nafath
    public const STATUS_NAFATH_PENDING    = 'nafath_pending';    // Nafath initiated
    public const STATUS_NAFATH_APPROVED   = 'nafath_approved';   // Nafath approved, signing in progress
    public const STATUS_INVOICE_SIGNED    = 'invoice_signed';    // invoice signed, done

    public const TYPE_LABELS = [
        'renew_contract' => 'طلب تجديد العقد',
        'sell_bike'      => 'طلب تصفية وبيع الدراجة',
        'add_bike'       => 'طلب إضافة دراجة',
    ];

    protected $table = 'investor_requests';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'full_name',
        'national_id',
        'phone',
        'contract_id',
        'admin_notes',
        'actioned_by',
        'actioned_at',
        'admin_invoice_path',
        'nafath_reference',     // ← already exists in your model; used for invoice signing
    ];

    protected function casts(): array
    {
        return ['actioned_at' => 'datetime'];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function actionedBy(): BelongsTo { return $this->belongsTo(User::class, 'actioned_by'); }

    // ── Signable interface ────────────────────────────────────────────────────

    public function getSignableId(): int
    {
        return $this->id;
    }

    public function getSignableType(): string
    {
        return 'investor_request';
    }

public function getSignableFilePath(): string
    {
        // Removed app/public/ to match the actual server directory structure
        $clean = trim((string) $this->admin_invoice_path);
        return "/home/portallogist/public_html/portallogistice/build/storage/{$clean}";
    }

    public function getSignableNationalId(): string
    {
        return (string) ($this->national_id ?? '');
    }

    public function onNafathApproved(): void
    {
        $this->update(['status' => self::STATUS_NAFATH_APPROVED]);
    }

    public function onSigningComplete(): void
    {
        $this->update(['status' => self::STATUS_INVOICE_SIGNED]);
    }

    public function onNafathRejected(): void
    {
        // Reset to invoice_sent so user can retry Nafath
        $this->update(['status' => self::STATUS_INVOICE_SENT]);
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

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * Whether this request has an invoice the user can sign via Nafath.
     */
    public function hasSignableInvoice(): bool
    {
        return ! empty($this->admin_invoice_path)
            && in_array($this->status, [
                self::STATUS_INVOICE_SENT,
                self::STATUS_NAFATH_PENDING,
            ], true);
    }

    public function toApiArray(): array
    {
        return [
            'id'                 => $this->id,
            'type'               => $this->type,
            'type_label'         => $this->typeLabel(),
            'status'             => $this->status,
            'full_name'          => $this->full_name,
            'national_id'        => $this->national_id,
            'phone'              => $this->phone,
            'contract_id'        => $this->contract_id,
            'admin_invoice_path' => $this->admin_invoice_path,
            'admin_invoice_url'  => $this->admin_invoice_path
                ? Storage::disk('public')->url($this->admin_invoice_path)
                : null,
            'nafath_reference'   => $this->nafath_reference,
            'can_sign_invoice'   => $this->hasSignableInvoice(),
            'admin_notes'        => $this->admin_notes,
            'actioned_at'        => $this->actioned_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
