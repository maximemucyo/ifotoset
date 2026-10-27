<?php

namespace App\Http\Controllers\Web;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Auth\GoogleAuthController;
use App\Models\AccountInvitation;
use App\Models\Plan;
use App\Models\User;
use App\Support\StudioAccount;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

/**
 * The link in a team invitation email. Logged-in invitees confirm, existing
 * users log in first, and new people create a login without a studio of their own.
 */
class TeamInvitationController extends Controller
{
    /**
     * Session key holding an invite token across the "Continue with Google" round trip.
     */
    public const GOOGLE_SESSION_KEY = 'team_invite_token';

    public function __construct(private readonly AcceptTeamInvitation $acceptor) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = AccountInvitation::findByToken($token);
        if ($problem = $this->acceptor->problem($invitation)) {
            return view('team.invitation', ['state' => 'invalid', 'message' => $problem, 'invitation' => null, 'token' => $token]);
        }

        $user = $request->user();
        if ($user) {
            return view('team.invitation', [
                'state' => $this->acceptor->isFor($invitation, $user) && !$user->isAdmin() ? 'confirm' : 'wrong_user',
                'invitation' => $invitation,
                'token' => $token,
            ]);
        }

        // Existing login: sign in, then come back here to confirm
        if (User::withTrashed()->where('email', $invitation->email)->exists()) {
            return redirect()->guest(route('login'))
                ->with('info', "Log in as {$invitation->email} to join {$invitation->account->name}.");
        }

        return view('team.invitation', [
            'state' => 'register',
            'invitation' => $invitation,
            'token' => $token,
            'googleEnabled' => GoogleAuthController::enabled(),
        ]);
    }

    /**
     * A logged-in user with the invited email joins the studio.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = AccountInvitation::findByToken($token);
        $user = $request->user();

        if ($problem = $this->acceptor->problem($invitation)) {
            return redirect()->route('team.invitation.show', $token)->with('error', $problem);
        }

        try {
            $this->acceptor->execute($invitation, $user);
        } catch (\RuntimeException $e) {
            return redirect()->route('team.invitation.show', $token)->with('error', $e->getMessage());
        }

        return $this->enterStudio($request, $invitation);
    }

    /**
     * Someone new creates a login (no username, so no public studio) and joins.
     */
    public function register(Request $request, string $token): RedirectResponse
    {
        $invitation = AccountInvitation::findByToken($token);
        if ($problem = $this->acceptor->problem($invitation)) {
            return redirect()->route('team.invitation.show', $token)->with('error', $problem);
        }
        if (User::withTrashed()->where('email', $invitation->email)->exists()) {
            return redirect()->route('team.invitation.show', $token);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = self::createInvitedUser($invitation, $validated['name'], Hash::make($validated['password']));

        try {
            $this->acceptor->execute($invitation, $user);
        } catch (\RuntimeException $e) {
            return redirect()->route('team.invitation.show', $token)->with('error', $e->getMessage());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $this->enterStudio($request, $invitation);
    }

    /**
     * "Continue with Google" from the invitation page. The token rides along in the
     * session; GoogleAuthController finishes the job when Google sends them back.
     */
    public function google(Request $request, string $token): RedirectResponse
    {
        $request->session()->put(self::GOOGLE_SESSION_KEY, $token);
        $request->session()->put('url.intended', route('team.invitation.show', $token));

        return redirect()->route('auth.google');
    }

    /**
     * A login for an invitee. Following the emailed link proved the address, so it starts verified.
     */
    public static function createInvitedUser(AccountInvitation $invitation, string $name, string $passwordHash, ?string $googleId = null): User
    {
        $user = User::forceCreate([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => Plan::where('slug', 'free')->value('id'),
            'name' => $name,
            'username' => null,
            'email' => $invitation->email,
            'google_id' => $googleId,
            'email_verified_at' => now(),
            'password' => $passwordHash,
        ]);

        event(new Registered($user));

        return $user;
    }

    private function enterStudio(Request $request, AccountInvitation $invitation): RedirectResponse
    {
        $request->session()->put(StudioAccount::SESSION_KEY, $invitation->account_id);

        return redirect()->route('studio.dashboard')->with('success', "Welcome to {$invitation->account->name}! You're now part of the team.");
    }
}
