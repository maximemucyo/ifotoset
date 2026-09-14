<?php

namespace App\Http\Controllers\Web\Studio;

use App\Actions\Studio\CreateClient;
use App\Actions\Studio\DeleteClient;
use App\Actions\Studio\UpdateClient;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Queries\Studio\StudioClientsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    /**
     * List clients with search and statistics.
     */
    public function index(Request $request, StudioClientsQuery $query): View
    {
        $user = $request->user();
        $search = $request->input('search');

        $clients = $query->paginate($user, $search, 15);
        $stats = $query->stats($user);

        return view('studio.clients.index', [
            'clients' => $clients,
            'search'  => $search,
            'stats'   => $stats,
        ]);
    }

    /**
     * Store a new client.
     */
    public function store(Request $request, CreateClient $action): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'location'     => ['nullable', 'string', 'max:255'],
            'instagram'    => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
        ]);

        $client = $action->execute($user, $validated);

        return back()->with('success', "Client '{$client->name}' added successfully.");
    }

    /**
     * Update client details.
     */
    public function update(Request $request, string $uuid, UpdateClient $action): RedirectResponse
    {
        $user = $request->user();

        $client = Client::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'location'     => ['nullable', 'string', 'max:255'],
            'instagram'    => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
        ]);

        $action->execute($user, $client, $validated);

        return back()->with('success', "Client '{$client->name}' updated successfully.");
    }

    /**
     * Delete a client.
     */
    public function destroy(Request $request, string $uuid, DeleteClient $action): RedirectResponse
    {
        $user = $request->user();

        $client = Client::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $name = $client->name;
        $action->execute($user, $client);

        return back()->with('success', "Client '{$name}' has been deleted.");
    }
}
