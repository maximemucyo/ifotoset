<?php

namespace App\Services;

class AdminAuditIpFormatter
{
    /**
     * Deterministically masks an IP address for safe administration display.
     */
    public static function mask(?string $ip): string
    {
        if (!$ip) {
            return 'N/A';
        }

        // IPv4 format: 192.168.1.50 -> 192.168.***.***
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.***.***';
            }
        }

        // IPv6 format: 2001:db8::1 -> 2001:db8:***
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 2) {
                return $parts[0] . ':' . $parts[1] . ':***';
            }
        }

        return substr($ip, 0, 4) . '***';
    }
}
