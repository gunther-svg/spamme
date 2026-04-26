<?php
namespace App;

class MailHelper
{
    /**
     * Heuristic to determine if provided body contains HTML.
     */
    public static function isHtml(string $body): bool
    {
        $body = trim($body);
        if ($body === '') return false;

        // Quick regex: any HTML tag
        if (preg_match('/<[^>]+>/', $body)) return true;

        $lower = strtolower($body);
        $signals = ['<br', '<p', '<div', '<span', '<table', '<!doctype', '<html', '<body', '&nbsp;'];
        foreach ($signals as $s) {
            if (strpos($lower, $s) !== false) return true;
        }

        return false;
    }

    /**
     * Create a reasonable plain-text alternative for an HTML body.
     */
    public static function altBody(string $body): string
    {
        $alt = strip_tags($body);
        $alt = html_entity_decode($alt, ENT_QUOTES | ENT_HTML5);
        // Normalize newlines
        $alt = preg_replace("/\r\n|\r/", "\n", $alt);
        // Collapse multiple blank lines
        $alt = preg_replace("/\n{3,}/", "\n\n", $alt);
        return trim($alt);
    }
}
