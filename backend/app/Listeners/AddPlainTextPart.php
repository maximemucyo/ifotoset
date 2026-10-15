<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;

/**
 * Gives every HTML-only email a plain-text alternative (better deliverability
 * and readable in text-only clients). Templates can still set their own.
 */
class AddPlainTextPart
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        if (!$message instanceof Email || $message->getTextBody() !== null) {
            return;
        }

        $html = $message->getHtmlBody();
        if (!is_string($html) || trim($html) === '') {
            return;
        }

        $message->text(self::toText($html));
    }

    public static function toText(string $html): string
    {
        // Drop non-content blocks, including the hidden preheader
        $html = preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<[^>]+display:\s*none[^>]*>.*?</(div|span|td)>#is', '', $html);

        // Links become "label (url)"; bare or self-labelled links keep just the url
        $html = preg_replace_callback('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) {
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            if (str_starts_with($url, 'mailto:')) {
                return $label;
            }
            return ($label === '' || $label === $url) ? $url : "{$label} ({$url})";
        }, $html);

        // Block elements become line breaks
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|tr|h[1-6]|li|table)>#i', "\n\n", $html);
        $html = preg_replace('#</td>#i', '  ', $html);
        $html = preg_replace('#<li\b[^>]*>#i', '- ', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        // Tidy whitespace: trim lines, collapse blank runs
        $lines = array_map(fn ($line) => trim(preg_replace('/[ \t]+/', ' ', $line)), explode("\n", $text));
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));

        return trim($text) . "\n";
    }
}
