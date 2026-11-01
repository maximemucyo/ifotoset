<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects addresses from temporary inbox services (mailinator.com, yopmail.com, ...),
 * including their subdomains.
 *
 * The list lives in resources/data/disposable-email-domains.txt, copied from
 * https://github.com/disposable-email-domains/disposable-email-domains. To refresh it:
 * curl -fsSL https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf -o resources/data/disposable-email-domains.txt
 */
class NotDisposableEmail implements ValidationRule
{
    /** @var array<string, true>|null */
    private static ?array $domains = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isDisposable($value)) {
            $fail('Please use a permanent email address. Temporary inboxes are not accepted.');
        }
    }

    public static function isDisposable(string $email): bool
    {
        $domain = strtolower(trim(substr(strrchr($email, '@') ?: '', 1), " \t\n\r\0\x0B."));
        if ($domain === '') {
            return false;
        }

        self::$domains ??= array_fill_keys(
            file(resource_path('data/disposable-email-domains.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
            true
        );

        // Check the domain and each parent domain, so "x.mailinator.com" matches "mailinator.com".
        $parts = explode('.', $domain);
        while (count($parts) >= 2) {
            if (isset(self::$domains[implode('.', $parts)])) {
                return true;
            }
            array_shift($parts);
        }

        return false;
    }
}
