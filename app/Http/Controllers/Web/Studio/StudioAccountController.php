<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Support\StudioAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudioAccountController extends Controller
{
    /**
     * Switch between the user's own studio and the team studios they belong to.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate(['account' => ['required', 'string']]);
        $actor = $request->user();

        if ($validated['account'] === (string) $actor->uuid) {
            if (!$actor->hasOwnStudio()) {
                return redirect()->route('studio.own.create');
            }

            $request->session()->put(StudioAccount::SESSION_KEY, $actor->id);

            return redirect()->route('studio.dashboard')->with('success', 'Switched to your studio.');
        }

        $owner = User::where('uuid', $validated['account'])->first();
        $membership = $owner ? $actor->activeMemberships()->firstWhere('account_id', $owner->id) : null;

        if (!$membership) {
            return back()->with('error', 'You don\'t have access to that studio.');
        }

        $request->session()->put(StudioAccount::SESSION_KEY, $owner->id);

        return redirect()->route('studio.dashboard')->with('success', "Switched to {$owner->name}.");
    }

    /**
     * A team member without a studio of their own picks a username to create one.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasOwnStudio()) {
            return redirect()->route('studio.dashboard');
        }

        return view('studio.own.create', [
            'hasTeam' => $request->user()->activeMemberships()->isNotEmpty(),
        ]);
    }

    /**
     * Give the member's existing login a username, which makes it a studio of its own.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasOwnStudio()) {
            return redirect()->route('studio.dashboard');
        }

        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/',
                'unique:users,username',
                Rule::notIn(config('reserved_usernames', [])),
            ],
        ], [
            'username.regex' => 'Use 3–50 lowercase letters, numbers or hyphens (no hyphen at the start or end).',
            'username.not_in' => 'That address is reserved. Please choose another.',
        ]);

        $user->forceFill([
            'username' => $validated['username'],
            'plan_id' => $user->plan_id ?? Plan::where('slug', 'free')->value('id'),
        ])->save();

        $request->session()->put(StudioAccount::SESSION_KEY, $user->id);

        return redirect()->route('studio.dashboard')->with('success', 'Your studio is ready. Team studios are still in the studio menu.');
    }
}
