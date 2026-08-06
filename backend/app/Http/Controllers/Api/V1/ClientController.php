<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateClientAction;
use App\Actions\UpdateClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreClientRequest;
use App\Http\Requests\V1\UpdateClientRequest;
use App\Http\Resources\V1\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * List clients.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Client::where('user_id', $request->user()->id);

        if ($request->boolean('trashed')) {
            $query->withTrashed();
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('instagram', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'created_desc');
        switch ($sort) {
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'created_asc':
                $query->orderBy('created_at', 'asc');
                break;
            case 'created_desc':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $clients = $query->withCount('bookings')->paginate($request->integer('per_page', 20));

        return ClientResource::collection($clients)->response();
    }

    /**
     * Store client.
     */
    public function store(StoreClientRequest $request, CreateClientAction $action): JsonResponse
    {
        $client = $action->execute($request->user(), $request->validated());

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show client.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $client = Client::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $client);

        return (new ClientResource($client->loadCount('bookings')))->response();
    }

    /**
     * Update client.
     */
    public function update(UpdateClientRequest $request, string $uuid, UpdateClientAction $action): JsonResponse
    {
        $client = Client::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $client);

        $updatedClient = $action->execute($client, $request->validated());

        return (new ClientResource($updatedClient))->response();
    }

    /**
     * Destroy client.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $client = Client::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $client);

        $client->delete();

        return response()->json(null, 204);
    }
}
