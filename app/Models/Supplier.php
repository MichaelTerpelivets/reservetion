<?php

declare (strict_types=1);

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    public function offerImports(): HasMany
    {
        return $this->hasMany(OfferImport::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
