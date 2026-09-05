<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Offer;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService
    ) {}

    public function store(StoreReservationRequest $request, Offer $offer): JsonResponse
    {
        $reservation = $this->reservationService->reserve($offer, $request->validated());

        return (new ReservationResource($reservation))
            ->response()
            ->setStatusCode(201);
    }
}
