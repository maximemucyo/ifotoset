<?php

namespace App\Actions\Teams;

use App\Models\AccountInvitation;
use App\Models\AccountMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcceptTeamInvitation
{
    /**
     * Why an invitation can't be used, or null when it can.
     */
    public function problem(?AccountInvitation $invitation): ?string
    {
        if (!$invitation) {
            return 'This invitation link is not valid. Ask the studio owner to send a new one.';
        }
        if ($invitation->accepted_at) {
            return 'This invitation has already been used.';
        }
        if ($invitation->revoked_at) {
            return 'This invitation was cancelled by the studio owner.';
        }
        if ($invitation->expires_at->isPast()) {
            return 'This invitation has expired. Ask the studio owner to send a new one.';
        }

        $owner = $invitation->account;
        if (!$owner || !$owner->is_active || !$owner->hasTeams()) {
            return 'This studio is not accepting team members right now.';
        }

        return null;
    }

    /**
     * Whether the invitation was sent to this user's email.
     */
    public function isFor(AccountInvitation $invitation, User $user): bool
    {
        return Str::lower($invitation->email) === Str::lower($user->email);
    }

    /**
     * Turn the invitation into a membership for $user. The seat was already
     * counted while the invitation was pending, so no seat check is needed.
     */
    public function execute(AccountInvitation $invitation, User $user): AccountMember
    {
        return DB::transaction(function () use ($invitation, $user) {
            $invitation = AccountInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($problem = $this->problem($invitation)) {
                throw new \RuntimeException($problem);
            }
            if (!$this->isFor($invitation, $user) || $user->isAdmin() || $user->id === $invitation->account_id) {
                throw new \RuntimeException('This invitation is for a different account.');
            }

            $invitation->update(['accepted_at' => now()]);

            $membership = AccountMember::firstOrNew(['account_id' => $invitation->account_id, 'user_id' => $user->id]);
            $membership->fill([
                'role' => $invitation->role,
                'invited_by' => $invitation->invited_by,
                'suspended_at' => null,
            ])->save();

            return $membership;
        });
    }
}
