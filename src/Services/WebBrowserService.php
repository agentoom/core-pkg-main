<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[GroupVisibility('agentoom-core')]
class WebBrowserService
{
    /**
     * Default HTML tags to mark with [TAG]...[/TAG] for structural clarity.
     */
    protected array $defaultMarkTags = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'article', 'section', 'main', 'nav', 'header', 'footer',
        'p', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'tr', 'th', 'td',
    ];

    /**
     * HTML tags considered as noise and removed before text extraction.
     */
    protected array $noiseTags = ['script', 'style', 'noscript', 'svg', 'canvas', 'head'];

    /**
     * Fetch content from a URL.
     *
     * @param  string  $url  The URL to fetch. Must be a valid HTTP or HTTPS URL.
     * @param  int  $timeout  Request timeout in seconds (default: 30)
     * @param  array  $elements  Specific HTML elements to retrieve (e.g., ['h1', 'meta'])
     * @param  bool  $stripHtml  Whether to strip HTML tags and extract text (default: true). Set to false to return raw HTML.
     * @return string The fetched content or an error message.
     */
    #[Actionable(name: 'Fetch URL', description: 'Fetch the text content of a web page.', category: 'Browsing')]
    public function fetch(string $url, int $timeout = 30, array $elements = [], bool $stripHtml = true): string
    {
        Log::debug('WebBrowserService fetch', [
            'url' => $url,
            'timeout' => $timeout,
            'elements' => $elements,
            'stripHtml' => $stripHtml,
        ]);

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Error: Invalid URL provided. Please provide a valid HTTP or HTTPS URL.';
        }

        // Only allow HTTP/HTTPS
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'])) {
            return 'Error: Only HTTP and HTTPS protocols are allowed.';
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; Agentoom-AI-Bot/1.0)',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::error('WebBrowserService fetch error', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return "Error: Failed to fetch URL. HTTP Status: {$response->status()}";
            }

            $contentType = $response->header('Content-Type');

            // Check if content is HTML or text
            if (! str_contains($contentType, 'text/') && ! str_contains($contentType, 'application/json')) {
                return "Error: Content type '{$contentType}' is not supported. Only text-based content can be fetched.";
            }

            $content = $response->body();

            // If HTML and stripping is requested, extract text; otherwise return raw content
            if (str_contains($contentType, 'text/html') && $stripHtml) {
                $content = $this->extractTextFromHtml($content, $elements);
            }

            // Limit response size to avoid overwhelming the LLM
            $maxLength = 50000; // ~50KB of text
            if (strlen($content) > $maxLength) {
                $content = substr($content, 0, $maxLength)."\n\n[Content truncated due to length...]";
            }

            Log::debug('WebBrowserService success', [
                'url' => $url,
                'content_type' => $contentType,
                'content_length' => strlen($content),
            ]);

            return "Successfully fetched content from: {$url}\n\n".
                "Content-Type: {$contentType}\n\n".
                "--- Content Start ---\n\n".
                $content."\n\n".
                '--- Content End ---';

        } catch (\Throwable $e) {
            Log::error('WebBrowserService error: '.$e->getMessage(), [
                'url' => $url,
                'exception' => $e,
            ]);

            return 'Error fetching URL: '.$e->getMessage();
        }
    }

    /**
     * Extract readable text from HTML content.
     */
    private function extractTextFromHtml(string $html, array $elements = []): string
    {
        $dom = new \DOMDocument;
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        if (! empty($elements)) {
            $extracted = [];
            foreach ($elements as $tagName) {
                $nodes = $dom->getElementsByTagName($tagName);
                foreach ($nodes as $node) {
                    $marker = strtoupper($tagName);
                    if ($tagName === 'meta') {
                        $attrs = [];
                        foreach ($node->attributes as $attr) {
                            $attrs[] = "{$attr->name}=\"{$attr->value}\"";
                        }
                        $content = implode(' ', $attrs);
                    } else {
                        $content = trim($node->textContent);
                    }

                    if (! empty($content)) {
                        $extracted[] = "[{$marker}]{$content}[/{$marker}]";
                    }
                }
            }

            return implode("\n\n", $extracted);
        }

        // 1. Extract title before removing noise
        $title = '';
        $titleNodes = $dom->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }

        // 2. Remove noise elements
        foreach ($this->noiseTags as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);
            while ($nodes->length > 0) {
                $node = $nodes->item(0);
                $node->parentNode->removeChild($node);
            }
        }

        // 2. Mark structural elements to help the AI understand the content
        foreach ($this->defaultMarkTags as $tagName) {
            $nodes = $dom->getElementsByTagName($tagName);
            // Process backwards to safely handle DOM additions
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                $marker = strtoupper($tagName);

                $startMarker = $dom->createTextNode("\n[{$marker}]");
                $endMarker = $dom->createTextNode("[/{$marker}]\n");

                if ($node->firstChild) {
                    $node->insertBefore($startMarker, $node->firstChild);
                } else {
                    $node->appendChild($startMarker);
                }
                $node->appendChild($endMarker);
            }
        }

        $cleanText = strip_tags($dom->saveHTML());

        // Decode entities and normalize whitespace
        $cleanText = html_entity_decode($cleanText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanText = preg_replace('/[ \t]+/', ' ', $cleanText);
        $cleanText = preg_replace("/\n\s*\n/", "\n\n", $cleanText);

        if (! empty($title)) {
            $cleanText = "[TITLE]{$title}[/TITLE]\n\n".$cleanText;
        }

        return trim($cleanText);
    }
}
