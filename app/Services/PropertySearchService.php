<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PropertySearchService
{
    /**
     * @param  array{
     *     city?: string|null,
     *     check_in: string,
     *     check_out: string,
     *     guests: int,
     *     per_page?: int
     * }  $filters
     * @return LengthAwarePaginator<int, Property>
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        $rankedOffers = DB::table('offers')
            ->select([
                'offers.id',
                'offers.property_id',
                'offers.price',
                'offers.currency',
                'offers.available_units',
                'offers.expires_at',
                'suppliers.code as supplier_code',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY offers.property_id ORDER BY offers.price ASC, offers.id ASC) as price_rank'
            )
            ->join('suppliers', 'suppliers.id', '=', 'offers.supplier_id')
            ->whereDate('offers.check_in', $filters['check_in'])
            ->whereDate('offers.check_out', $filters['check_out'])
            ->where('offers.max_guests', '>=', $filters['guests'])
            ->where('offers.available_units', '>', 0)
            ->where('offers.expires_at', '>', now());

        $query = Property::query()
            ->select([
                'properties.id',
                'properties.code',
                'properties.name',
                'properties.city',
                'best_offers.id as best_offer_id',
                'best_offers.price as best_offer_price',
                'best_offers.currency as best_offer_currency',
                'best_offers.available_units as best_offer_available_units',
                'best_offers.expires_at as best_offer_expires_at',
                'best_offers.supplier_code as best_offer_supplier',
            ])
            ->joinSub($rankedOffers, 'best_offers', function ($join) {
                $join->on('best_offers.property_id', '=', 'properties.id')
                    ->where('best_offers.price_rank', '=', 1);
            })
            ->orderBy('best_offers.price')
            ->orderBy('properties.code');

        if (! empty($filters['city'])) {
            $query->where('properties.city', $filters['city']);
        }

        /** @var LengthAwarePaginator<int, Property> $paginator */
        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (Property $property) {
            $property->setRelation('bestOfferPayload', (object) [
                'id' => (int) $property->best_offer_id,
                'supplier' => $property->best_offer_supplier,
                'price' => (int) $property->best_offer_price,
                'currency' => $property->best_offer_currency,
                'available_units' => (int) $property->best_offer_available_units,
                'expires_at' => $property->best_offer_expires_at,
            ]);

            return $property;
        });

        return $paginator;
    }
}
