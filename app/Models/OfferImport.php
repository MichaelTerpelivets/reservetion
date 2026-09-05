<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\OfferImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferImport extends Model
{
    /** @use HasFactory<OfferImportFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'external_import_id',
        'sent_at',
        'status',
        'total_offers',
        'processed_offers',
        'error',
        'payload',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => ImportStatus::class,
            'payload' => 'array',
            'total_offers' => 'integer',
            'processed_offers' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
