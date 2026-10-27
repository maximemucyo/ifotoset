<?php

namespace App\Http\Controllers\Web\Studio;

use App\Actions\Teams\SendTeamInvitation;
use App\Enums\AccountRole;
use App\Http\Controllers\Controller;
use App\Models\AccountMember;
use App\Models\User;
use App\Support\StudioAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Members, pending invitations and seat usage. Owners manage the team; managers can only view it.
     */
    public function index(Request $request): View
    {
        $account = $request->account();

        return view('studio.team.index', [
            'account' => $account,
            'members' => $account->teamMembers()->with('user:id,name,email,avatar_path')->orderBy('created_at')->get(),
            'invitations' => $account->teamInvitations()->pending()->with('inviter:id,name')->latest()->get(),
            'seatLimit' => $account->seatLimit(),
            'seatsUsed' => $account->seatsUsed(),
            'hasTeams' => $account->hasTeams(),
            'roles' => array_map(fn (string $role) => AccountRole::from($role), AccountRole::assignable()),
        ]);
    }

    /**
     * Invite someone to the studio by email.
     */
    public function invite(Request $request, SendTeamInvitation $sender): RedirectResponse
    {
        $account = $request->account();

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(AccountRole::assignable())],
        ]);
        $email = $validated['email'];

        $error = DB::transaction(function () use ($account, $email, $validated, $request, $sender) {
            // Serialise invites per studio so two at once can't both take the last seat
            User::whereKey($account->id)->lockForUpdate()->first();

            if ($error = $this->inviteProblem($account, $email)) {
                return $error;
            }

            $sender->execute($account, $request->user(), $email, AccountRole::from($validated['role']));

            return null;
        });

        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        return back()->with('success', "Invitation sent to {$email}.");
    }

    private function inviteProblem(User $account, string $email): ?string
    {
        if (!$account->hasTeams()) {
            return 'Your plan doesn\'t include team members. Upgrade to Business to invite your team.';
        }
        if ($account->seatsUsed() >= $account->seatLimit()) {
            return 'All your team seats are taken. Remove a member or cancel a pending invitation first.';
        }
        if ($email === Str::lower($account->email)) {
            return 'That\'s your own email. You already own this studio.';
        }

        $existing = User::where('email', $email)->first();
        if ($existing?->isAdmin()) {
            return 'Platform administrator accounts can\'t join a studio team.';
        }
        if ($existing && $account->teamMembers()->where('user_id', $existing->id)->exists()) {
            return "{$email} is already on your team.";
        }
        if ($account->teamInvitations()->pending()->where('email', $email)->exists()) {
            return "{$email} already has a pending invitation. Resend it instead.";
        }

        return null;
    }

    /**
     * Send a pending invitation again with a fresh link and a new 7-day expiry.
     */
    public function resend(Request $request, string $uuid, SendTeamInvitation $sender): RedirectResponse
    {
        $account = $request->account();
        $invitation = $account->teamInvitations()->where('uuid', $uuid)->whereNull('accepted_at')->whereNull('revoked_at')->firstOrFail();

        // An expired invitation no longer holds a seat, so it needs a free one again
        if (!$invitation->isPending() && $account->seatsUsed() >= $account->seatLimit()) {
            return back()->with('error', 'All your team seats are taken. Remove a member or cancel a pending invitation first.');
        }

        $sender->execute($account, $request->user(), $invitation->email, $invitation->role, $invitation);

        return back()->with('success', "Invitation resent to {$invitation->email}.");
    }

    /**
     * Cancel a pending invitation; its link stops working and the seat frees up.
     */
    public function revoke(Request $request, string $uuid): RedirectResponse
    {
        $invitation = $request->account()->teamInvitations()->where('uuid', $uuid)->whereNull('accepted_at')->firstOrFail();
        $invitation->update(['revoked_at' => now()]);

        return back()->with('success', "Invitation for {$invitation->email} cancelled.");
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        $membership = $this->membership($request, $member);
        $validated = $request->validate(['role' => ['required', Rule::in(AccountRole::assignable())]]);

        $membership->update(['role' => $validated['role']]);

        return back()->with('success', "{$membership->user->name} is now a " . strtolower($membership->role->label()) . '.');
    }

    /**
     * Remove a member. Only the membership goes: their login and any studio of their own stay.
     */
    public function remove(Request $request, int $member): RedirectResponse
    {
        $membership = $this->membership($request, $member);
        $name = $membership->user->name;
        $membership->delete();

        return back()->with('success', "{$name} was removed from the team.");
    }

    /**
     * A member leaves the team studio they're working in.
     */
    public function leave(Request $request): RedirectResponse
    {
        $context = StudioAccount::current();
        if (!$context->isTeamContext()) {
            return back()->with('error', 'You own this studio, so you can\'t leave it.');
        }

        AccountMember::where('account_id', $context->account()->id)->where('user_id', $request->user()->id)->delete();
        $request->session()->forget(StudioAccount::SESSION_KEY);

        return redirect()->route('studio.dashboard')->with('success', "You left {$context->account()->name}.");
    }

    private function membership(Request $request, int $id): AccountMember
    {
        return AccountMember::where('account_id', $request->account()->id)->with('user:id,name')->findOrFail($id);
    }
}
