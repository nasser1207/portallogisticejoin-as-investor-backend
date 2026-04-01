<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SadqNafathRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'request_id',
        'national_id',
        'contract_type',
        'status',
        'last_payload',
    ];

    protected function casts(): array
    {
        return [
            'last_payload' => 'array',
        ];
    }
}
