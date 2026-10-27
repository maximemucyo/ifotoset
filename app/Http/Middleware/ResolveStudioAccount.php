<?php

namespace App\Http\Middleware;

use App\Models\AccountInvitation;
use App\Models\User;
use App\Support\StudioAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which studio account a studio request acts on: the logged-in user's
 * own, or a team studio they picked with the switcher. Membership, the owner's
 * status and the owner's plan are re-checked on every request, so removing a
 * member or downgrading the plan ends access straight away.
 */
class ResolveStudioAccount
{
    /**
     * Routes a member without a studio of their own can still open.
     */
    private const WITHOUT_STUDIO_ROUTES = [
        'studio.own.*',
        'studio.account.switch',
        'studio.settings.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $context = $this->resolve($request, $actor);

        $request->attributes->set(StudioAccount::REQUEST_ATTRIBUTE, $context);
        View::share('studioAccount', $context);

        // A member whose team access ended has no studio to show until they create one
        if (!$context->isTeamContext() && !$actor->hasOwnStudio() && !$request->routeIs(...self::WITHOUT_STUDIO_ROUTES)
            && $this->joinedByInvitation($actor)) {
            return redirect()->route('studio.own.create');
        }

        return $next($request);
    }

    /**
     * Older accounts can lack a username too; only people who signed up through
     * a team invitation are sent to create a studio.
     */
    private function joinedByInvitation(User $actor): bool
    {
        return !$actor->isAdmin()
            && AccountInvitation::where('email', $actor->email)->whereNotNull('accepted_at')->exists();
    }

    private function resolve(Request $request, User $actor): StudioAccount
    {
        $session = $request->session();
        $accountId = (int) $session->get(StudioAccount::SESSION_KEY);

        if ($accountId && $accountId !== $actor->id) {
            $membership = $actor->activeMemberships()->firstWhere('account_id', $accountId);
            if ($membership) {
                return new StudioAccount($membership->account, $actor, $membership->role);
            }

            $session->forget(StudioAccount::SESSION_KEY);
            $session->now('error', 'You no longer have access to that studio.');
        }

        // Members without a studio of their own always work in a team studio
        if (!$actor->hasOwnStudio() && ($membership = $actor->activeMemberships()->first())) {
            $session->put(StudioAccount::SESSION_KEY, $membership->account_id);

            return new StudioAccount($membership->account, $actor, $membership->role);
        }

        return StudioAccount::own($actor);
    }
}
