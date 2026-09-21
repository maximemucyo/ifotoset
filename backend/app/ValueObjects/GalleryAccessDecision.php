<?php

namespace App\ValueObjects;

class GalleryAccessDecision
{
    public function __construct(
        public readonly bool $granted,
        public readonly string $reason,
        public readonly ?string $message = null,
        public readonly ?string $passwordHint = null,
        public readonly ?string $errorCode = null
    ) {}

    public static function granted(): self
    {
        return new self(
            granted: true,
            reason: 'granted'
        );
    }

    public static function passwordRequired(?string $hint = null, ?string $message = null): self
    {
        return new self(
            granted: false,
            reason: 'requires_password',
            message: $message ?? 'This gallery is PIN protected.',
            passwordHint: $hint,
            errorCode: 'PASSWORD_REQUIRED'
        );
    }

    public static function invitationRequired(?string $message = null): self
    {
        return new self(
            granted: false,
            reason: 'requires_invitation',
            message: $message ?? 'This gallery is private and available to invited guests only. Please use the personalized invitation link sent to your email.',
            errorCode: 'INVITATION_REQUIRED'
        );
    }

    public static function invitationInvalid(?string $message = null): self
    {
        return new self(
            granted: false,
            reason: 'invitation_invalid',
            message: $message ?? 'This invitation link is no longer valid or has been revoked. Please contact the photographer for a new invitation.',
            errorCode: 'INVITATION_INVALID'
        );
    }

    public static function expired(?string $message = null): self
    {
        return new self(
            granted: false,
            reason: 'expired',
            message: $message ?? 'This gallery has expired and is no longer accessible.',
            errorCode: 'GALLERY_EXPIRED'
        );
    }

    public static function denied(?string $message = null, string $errorCode = 'ACCESS_DENIED'): self
    {
        return new self(
            granted: false,
            reason: 'denied',
            message: $message ?? 'Access to this gallery is denied.',
            errorCode: $errorCode
        );
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function isDenied(): bool
    {
        return $this->reason === 'denied';
    }

    public function requiresPassword(): bool
    {
        return $this->reason === 'requires_password';
    }

    public function requiresInvitation(): bool
    {
        return $this->reason === 'requires_invitation';
    }

    public function isInvitationInvalid(): bool
    {
        return $this->reason === 'invitation_invalid';
    }

    public function isExpired(): bool
    {
        return $this->reason === 'expired';
    }
}
