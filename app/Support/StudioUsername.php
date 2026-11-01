<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The studio username (the portfolio subdomain): one set of rules for every form
 * that sets it, and the live availability check those forms call while typing.
 */
class StudioUsername
{
    public static function rules(?User $ignore = null): array
    {
        return [
            'required',
            'string',
            'regex:/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/',
            $ignore ? Rule::unique('users', 'username')->ignore($ignore->id) : 'unique:users,username',
            Rule::notIn(config('reserved_usernames', [])),
        ];
    }

    public static function messages(): array
    {
        return [
            'username.regex' => 'Use 3–50 lowercase letters, numbers or hyphens (no hyphen at the start or end).',
            'username.unique' => 'That address is already taken. Please choose another.',
            'username.not_in' => 'That address is reserved. Please choose another.',
        ];
    }

    /**
     * @return array{available: bool, message: string|null, suggestion: string|null}
     */
    public static function check(string $username): array
    {
        $validator = Validator::make(['username' => $username], ['username' => self::rules()], self::messages());

        if ($validator->passes()) {
            return ['available' => true, 'message' => null, 'suggestion' => null];
        }

        // Only a well-formed name that is taken or reserved gets a suggestion; a bad format has to be fixed first
        $failed = array_keys($validator->failed()['username'] ?? []);
        $takenOrReserved = array_diff($failed, ['Unique', 'NotIn']) === [];

        return [
            'available' => false,
            'message' => $validator->errors()->first('username'),
            'suggestion' => $takenOrReserved ? self::suggest($username) : null,
        ];
    }

    /**
     * The first free username built from $base: "$base", then "{$base}2", "{$base}3", ...
     */
    public static function suggest(string $base): string
    {
        $base = substr($base, 0, 46);
        $reserved = config('reserved_usernames', []);
        $candidate = $base;
        for ($i = 2; in_array($candidate, $reserved, true) || User::withTrashed()->where('username', $candidate)->exists(); $i++) {
            $candidate = $base . $i;
        }

        return $candidate;
    }
}
