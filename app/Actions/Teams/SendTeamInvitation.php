<?php

namespace App\Actions\Teams;

use App\Enums\AccountRole;
use App\Mail\TeamInvitationMail;
use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendTeamInvitation
{
    /**
     * Create an invitation, or refresh an existing one with a new token, and email the link.
     */
    public function execute(User $owner, User $inviter, string $email, AccountRole $role, ?AccountInvitation $invitation = null): AccountInvitation
    {
        $token = Str::random(64);

        $attributes = [
            'role' => $role,
            'token_hash' => AccountInvitation::hashToken($token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(AccountInvitation::EXPIRES_AFTER_DAYS),
            'revoked_at' => null,
        ];

        if ($invitation) {
            $invitation->update($attributes);
        } else {
            $invitation = AccountInvitation::create([...$attributes, 'account_id' => $owner->id, 'email' => $email]);
        }

        Mail::to($invitation->email)->queue(new TeamInvitationMail(
            $invitation,
            $owner,
            $inviter,
            route('team.invitation.show', $token),
        ));

        return $invitation;
    }
}
