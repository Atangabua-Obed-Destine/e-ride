<?php

if (!function_exists('sorting'))
{
    function sorting($sort = null)
    {
        return match ($sort)
        {
            'oldest' => ['created_at' => 'asc'],
            'popular' => ['click_count' => 'desc'],
            'a2z' => ['title' => 'asc'],
            'z2a' => ['title' => 'desc'],
            default => ['created_at' => 'desc'],
        };
    }
}

if (!function_exists('sortingBlogCategory'))
{
    function sortingBlogCategory($sort = null)
    {
        return match ($sort)
        {
            'oldest' => ['created_at' => 'asc'],
            'popular' => ['click_count' => 'desc'],
            'a2z' => ['name' => 'asc'],
            'z2a' => ['name' => 'desc'],
            default => ['created_at' => 'desc'],
        };
    }
}

if (!function_exists('processArticleH2'))
{
    function processArticleH2(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');

        // Convert encoding safely
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        // Wrap inside body to prevent auto-fixing issues
        $dom->loadHTML(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $h2Tags = $dom->getElementsByTagName('h2');

        $sections = [];
        $index = 0;

        foreach ($h2Tags as $h2) {
            $id = 'article-section-' . $index;
            $h2->setAttribute('id', $id);

            $sections[] = [
                'id'    => $id,
                'title' => trim($h2->textContent),
            ];

            $index++;
        }

        libxml_clear_errors();

        // Extract only body content back
        $body = $dom->getElementsByTagName('body')->item(0);
        $html = '';

        foreach ($body->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return [
            'html'     => $html,
            'sections' => $sections,
        ];
    }
}
