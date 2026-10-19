<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class RecipientEmailImportService
{
    /**
     * Parse and extract valid emails from plain text input.
     *
     * @param string|null $text
     * @return array<string>
     */
    public function parseFromText(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        // Split on commas, semicolons, whitespace, or newlines
        $parts = preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $emails = [];

        foreach ($parts as $part) {
            $cleaned = strtolower(trim($part));
            if ($this->isValidEmail($cleaned)) {
                $emails[] = $cleaned;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Parse and extract emails from an uploaded CSV or TSV file.
     *
     * @param UploadedFile $file
     * @return array<string>
     */
    public function parseFromFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (!$path || !is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        // Read sample to detect delimiter
        $sample = fread($handle, 2048);
        rewind($handle);

        $delimiter = $this->detectDelimiter($sample);
        $emails = [];
        $emailColIndex = null;
        $isFirstRow = true;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty($row) || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }

            if ($isFirstRow) {
                $isFirstRow = false;

                // Check for header row like "email" or "Email" or "E-mail"
                foreach ($row as $idx => $header) {
                    $normHeader = strtolower(trim((string) $header));
                    if (in_array($normHeader, ['email', 'e-mail', 'mail', 'email address', 'recipient email'], true)) {
                        $emailColIndex = $idx;
                        break;
                    }
                }

                // If this row was a header, skip to next row
                if ($emailColIndex !== null) {
                    continue;
                }
            }

            // Extract email from identified column or inspect row items
            if ($emailColIndex !== null && isset($row[$emailColIndex])) {
                $candidate = strtolower(trim((string) $row[$emailColIndex]));
                if ($this->isValidEmail($candidate)) {
                    $emails[] = $candidate;
                }
            } else {
                foreach ($row as $cell) {
                    $candidate = strtolower(trim((string) $cell));
                    if ($this->isValidEmail($candidate)) {
                        $emails[] = $candidate;
                        break; // one email per row
                    }
                }
            }
        }

        fclose($handle);

        return array_values(array_unique($emails));
    }

    /**
     * Generate standard CSV template for photographers.
     */
    public function generateCsvTemplate(): string
    {
        return "email\nclient1@example.com\nclient2@example.com\n";
    }

    /**
     * Detect delimiter from sample string (, ; \t).
     */
    private function detectDelimiter(string $sample): string
    {
        $delimiters = [',', ';', "\t"];
        $counts = [];

        foreach ($delimiters as $delim) {
            $counts[$delim] = substr_count($sample, $delim);
        }

        arsort($counts);
        $best = array_key_first($counts);

        return ($counts[$best] > 0) ? $best : ',';
    }

    /**
     * Validate email format with standard PHP filter.
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
