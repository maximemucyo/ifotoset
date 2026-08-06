<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreatePackageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StorePackageRequest;
use App\Http\Requests\V1\UpdatePackageRequest;
use App\Http\Resources\V1\PackageResource;
use App\Models\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * List packages.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Package::where('user_id', $request->user()->id);

        if ($request->has('active')) {
            if ($request->boolean('active')) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        $sort = $request->input('sort', 'sort_order');
        if ($sort === 'name') {
            $query->orderBy('name', 'asc');
        } else {
            $query->orderBy('sort_order', 'asc');
        }

        $packages = $query->paginate($request->integer('per_page', 20));

        return PackageResource::collection($packages)->response();
    }

    /**
     * Store package.
     */
    public function store(StorePackageRequest $request, CreatePackageAction $action): JsonResponse
    {
        $package = $action->execute($request->user(), $request->validated());

        return (new PackageResource($package))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show package.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $package = Package::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $package);

        return (new PackageResource($package))->response();
    }

    /**
     * Update package.
     */
    public function update(UpdatePackageRequest $request, string $uuid): JsonResponse
    {
        $package = Package::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $package);

        $package->update($request->validated());

        return (new PackageResource($package))->response();
    }

    /**
     * Destroy package.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $package = Package::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $package);

        $package->delete();

        return response()->json(null, 204);
    }
}
