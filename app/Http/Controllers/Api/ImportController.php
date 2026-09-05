<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportCreatedResource;
use App\Http\Resources\OfferImportResource;
use App\Models\OfferImport;
use App\Services\ImportOffersService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportOffersService $importOffersService
    ) {}

    /**
     * @throws Throwable
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        $import = $this->importOffersService->create($request->validated());

        return (new ImportCreatedResource($import))
            ->response()
            ->setStatusCode(202);
    }

    public function show(OfferImport $import): OfferImportResource
    {
        $import->load('supplier');

        return new OfferImportResource($import);
    }
}
