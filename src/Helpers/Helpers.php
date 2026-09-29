<?php

namespace Agentoom\Core\Helpers;

use Agentoom\Core\Models\PipelineRunStep;
use Agentoom\Core\Services\PipelineExecutionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Spatie\LaravelMarkdown\MarkdownRenderer;

class Helpers
{
    public static function encryptString($plainText)
    {
        $encryption_method = 'AES-256-CBC';
        $key = hash('sha256', 'ninjaencryption', true);
        $ivLength = openssl_cipher_iv_length($encryption_method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt(
            $plainText,
            $encryption_method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        $encryptedWithIV = $iv.$encrypted;
        $urlSafeEncrypted = rtrim(strtr(base64_encode($encryptedWithIV), '+/', '-_'), '=');

        return $urlSafeEncrypted;
    }

    public static function decryptString($cipherText)
    {
        $encryption_method = 'AES-256-CBC';
        $base64 = strtr($cipherText, '-_', '+/');
        $key = hash('sha256', 'ninjaencryption', true);

        $padding = strlen($base64) % 4;
        if ($padding) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $encryptedWithIV = base64_decode($base64, true);

        $ivLength = openssl_cipher_iv_length($encryption_method);

        $iv = substr($encryptedWithIV, 0, $ivLength);

        $encrypted = substr($encryptedWithIV, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            $encryption_method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return json_decode($decrypted, true);
    }

    public static function convertHTML($string)
    {
        $string = preg_replace('/\s*【.*?】\s*/', ' ', $string);

        return app(MarkdownRenderer::class)->toHtml($string);
    }

    /**
     * Maximum bytes to download from a URL (5 MB) to prevent OOM on huge resources.
     */
    public const SCRAPE_MAX_BYTES = 5 * 1024 * 1024;

    public static function scrapeUrl(string $url): string
    {
        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'scrape_');

            try {
                $response = Http::withOptions([
                    'sink' => $tmpFile,
                    'curl' => [
                        CURLOPT_MAXFILESIZE => self::SCRAPE_MAX_BYTES,
                    ],
                ])->timeout(30)->get($url);

                if (! $response->successful()) {
                    return '';
                }

                $fileSize = filesize($tmpFile);

                if ($fileSize === false || $fileSize === 0) {
                    return '';
                }

                // Read only up to the cap even if curl did not enforce it
                $handle = fopen($tmpFile, 'r');
                if ($handle === false) {
                    return '';
                }

                $raw = fread($handle, self::SCRAPE_MAX_BYTES);
                fclose($handle);

                if ($raw === false || $raw === '') {
                    return '';
                }
            } finally {
                if (file_exists($tmpFile)) {
                    unlink($tmpFile);
                }
            }

            // Remove unuseful HTML tags
            $raw = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $raw);
            $raw = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $raw);
            $raw = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $raw);
            $raw = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $raw);
            $raw = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $raw);

            return self::cleanHtmlTags($raw);
        } catch (\Exception $e) {
            Log::error("Failed to scrape URL {$url}: ".$e->getMessage());
        }

        return '';
    }

    public static function cleanHtmlTags(string $instruction): string
    {
        $instruction = strip_tags($instruction);
        $instruction = html_entity_decode($instruction, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $instruction = preg_replace('/【[^】]*】/', '', $instruction);
        $instruction = preg_replace('/\[source[^\]]*\]/', '', $instruction);
        $instruction = preg_replace('/[ \t]+/', ' ', $instruction);
        $instruction = trim($instruction);

        return $instruction;
    }

    protected static ?\Closure $streamer = null;

    public static function setStreamer(?\Closure $callback): void
    {
        self::$streamer = $callback;
    }

    public static function stream(string $to, string $content, bool $replace = false): void
    {
        if (self::$streamer) {
            (self::$streamer)($to, $content, $replace);
        }
    }

    public static function pulse(): void
    {
        // No-op to prevent breaking changes while we investigate safer keep-alive methods.
    }

    public static function getToolInfo(string $toolClass): array
    {
        if (! class_exists($toolClass)) {
            return [
                'name' => class_basename($toolClass),
                'description' => $toolClass,
            ];
        }

        try {
            $instance = null;
            try {
                $instance = app($toolClass);
            } catch (\Throwable $e) {
                try {
                    $reflection = new \ReflectionClass($toolClass);
                    if ($reflection->getConstructor() === null || $reflection->getConstructor()->getNumberOfRequiredParameters() === 0) {
                        $instance = new $toolClass;
                    }
                } catch (\Throwable $e2) {
                    // Ignore
                }
            }

            $name = class_basename($toolClass);
            $description = $toolClass;

            if ($instance) {
                if (method_exists($instance, 'name')) {
                    $name = $instance->name();
                }
                if (method_exists($instance, 'description')) {
                    $description = $instance->description();
                }
            }

            return [
                'name' => (string) $name,
                'description' => (string) $description,
            ];
        } catch (\Throwable $e) {
            return [
                'name' => class_basename($toolClass),
                'description' => $toolClass,
            ];
        }
    }

    public static function semanticChunk(string $text, int $chunkSize = 800, int $overlap = 400): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z])/i', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentChunk = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) {
                continue;
            }

            // If adding this sentence exceeds our limit, push the current chunk and start a new one
            if (strlen($currentChunk) + strlen($sentence) > $chunkSize && ! empty($currentChunk)) {
                $chunks[] = trim($currentChunk);

                // Create overlap by keeping the last portion of the previous chunk
                $overlapStart = max(0, strlen($currentChunk) - $overlap);
                $overlapText = substr($currentChunk, $overlapStart);

                // Try to make the overlap start cleanly at a word boundary
                $cleanOverlapStart = strpos($overlapText, ' ');
                if ($cleanOverlapStart !== false) {
                    $overlapText = substr($overlapText, $cleanOverlapStart + 1);
                }

                $currentChunk = $overlapText.' '.$sentence;
            } else {
                $currentChunk .= ($currentChunk === '' ? '' : ' ').$sentence;
            }
        }

        if (! empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    public static function translate($value, ?string $locale = null): string
    {
        if (empty($value)) {
            return '';
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        $locale = $locale ?: app()->getLocale();

        return $value[$locale] ?? $value[array_key_first($value)] ?? '';
    }

    public static function convertStringToViewableFormat(string $string): string
    {
        // 1. Strip citation markers like 【4†source】
        $string = preg_replace('/\s*【[^】]*】\s*/', ' ', $string);

        // 2. Strip inline [source ...] tags
        $string = preg_replace('/\[source[^\]]*\]/', '', $string);

        // 3. Remove empty list items or lone markers that agents sometimes output
        $string = preg_replace('/^\s*([\*\-]|--|\*\*)\s*$/m', '', $string);

        // 4. Fix common agent formatting issues
        // Convert lone -- to --- for proper horizontal rules
        $string = preg_replace('/^\s*\-\-\s*$/m', '---', $string);

        // Pre-processing to help Markdown parser handle agent output that lacks newlines
        // Ensure headers (#) have a blank line before them, but not if inside a table
        $string = preg_replace('/(?<=[^\|\s])\s*(#{1,6}\s+)/', "\n\n$1", $string);

        // Ensure list items (*, -, or numbered) have a newline before them if preceded by text on the same line
        $string = preg_replace('/([^\n\|])\s+([\*\-]\s+)/', "$1\n$2", $string);
        $string = preg_replace('/([^\n\|])\s+(\d+\.\s+)/', "$1\n$2", $string);

        // If a markdown table starts on the same line as text (e.g. "Table: | A | B |"),
        // move the entire pipe segment onto its own line without splitting table cells.
        $string = preg_replace('/^(.*\S)\s+(\|[^\n]*\|[^\n]*)$/m', "$1\n$2", $string);

        // 5. Strip whitespace around content but preserve internal spacing for code blocks
        $string = trim($string);

        // 6. Parse Markdown → HTML using spatie/laravel-markdown
        $string = app(MarkdownRenderer::class)->toHtml($string);

        // 5. Wrap every <table> the Markdown parser emitted with a
        //    responsive, Tailwind-styled shell.
        $string = preg_replace_callback(
            '/<table>(.*?)<\/table>/s',
            function (array $matches): string {
                // Re-inject Tailwind classes into the raw table tags
                $inner = $matches[1];

                // thead / th
                $inner = preg_replace(
                    '/<thead>/',
                    '<thead class="bg-zinc-50 dark:bg-zinc-900">',
                    $inner
                );
                $inner = preg_replace(
                    '/<th>/',
                    '<th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-200 border-b border-zinc-200/80 dark:border-zinc-800/80 whitespace-nowrap">',
                    $inner
                );

                // tbody / tr / td
                $inner = preg_replace(
                    '/<tbody>/',
                    '<tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-800/70 bg-white dark:bg-zinc-950">',
                    $inner
                );
                $inner = preg_replace(
                    '/<tr>/',
                    '<tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors">',
                    $inner
                );
                $inner = preg_replace(
                    '/<td>/',
                    '<td class="px-4 py-2 text-sm text-zinc-950 dark:text-zinc-50 align-top whitespace-normal break-words">',
                    $inner
                );

                return <<<HTML
<div class="not-prose my-4 overflow-x-auto rounded-xl border border-zinc-200/80 dark:border-zinc-800/80 shadow-sm">
<table class="min-w-full w-full table-auto text-sm">
{$inner}
</table>
</div>
HTML;
            },
            $string
        );

        return trim($string);
    }

    /**
     * Parse Markdown to HTML using spatie/laravel-markdown.
     * Useful for emails and other non-chat contexts.
     */
    public static function parseMarkdown(string $string): string
    {
        // 1. Strip citation markers like 【4†source】
        $string = preg_replace('/\s*【[^】]*】\s*/', ' ', $string);

        // 2. Strip inline [source ...] tags
        $string = preg_replace('/\[source[^\]]*\]/', '', $string);

        // 3. Parse Markdown → HTML using spatie/laravel-markdown
        return trim(app(MarkdownRenderer::class)->toHtml($string));
    }

    public static function extractTextFromFile($file)
    {
        if (is_string($file)) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $realPath = $file;
        } else {
            $extension = strtolower($file->getClientOriginalExtension());
            $realPath = $file->getRealPath();
        }

        switch ($extension) {
            case 'txt':
                return file_get_contents($realPath);

            case 'pdf':
                try {
                    $parser = new PdfParser;
                    $pdf = $parser->parseFile($realPath);

                    return $pdf->getText();
                } catch (\Throwable $e) {
                    return '';
                }

            case 'doc':
            case 'docx':
                try {
                    $phpWord = WordIOFactory::load($realPath);
                    $text = '';

                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            // Handle TextRun (formatted text container)
                            if ($element instanceof TextRun) {
                                foreach ($element->getElements() as $textElement) {
                                    if (method_exists($textElement, 'getText')) {
                                        $text .= $textElement->getText().' ';
                                    }
                                }
                            }
                            // Handle Text elements directly
                            elseif ($element instanceof Text) {
                                $text .= $element->getText().' ';
                            }
                            // Handle Table elements
                            elseif ($element instanceof Table) {
                                foreach ($element->getRows() as $row) {
                                    foreach ($row->getCells() as $cell) {
                                        foreach ($cell->getElements() as $cellElement) {
                                            if ($cellElement instanceof TextRun) {
                                                foreach ($cellElement->getElements() as $textElement) {
                                                    if (method_exists($textElement, 'getText')) {
                                                        $text .= $textElement->getText().' ';
                                                    }
                                                }
                                            } elseif (method_exists($cellElement, 'getText')) {
                                                $text .= $cellElement->getText().' ';
                                            }
                                        }
                                    }
                                }
                            }
                            // Handle ListItem elements
                            elseif ($element instanceof ListItem) {
                                foreach ($element->getElements() as $textElement) {
                                    if (method_exists($textElement, 'getText')) {
                                        $text .= $textElement->getText().' ';
                                    }
                                }
                            }
                        }
                    }

                    return trim($text);
                } catch (\Throwable $e) {
                    return '';
                }

            default:
                return '';
        }
    }

    /**
     * Extract JSON from a string that may contain markdown, AI commentary,
     * code fences, or other non-JSON content.
     *
     * Strategy:
     * 1. Try direct json_decode() first (fast path for clean JSON).
     * 2. Look for ```json ... ``` code blocks.
     * 3. Look for ``` ... ``` code blocks and try to parse content as JSON.
     * 4. Find the first { or [ and extract balanced content.
     *
     * @return array|null The parsed JSON data, or null if no valid JSON found.
     */
    public static function extractJsonFromText(?string $text): ?array
    {
        if (empty($text)) {
            return null;
        }

        $text = trim($text);

        // 1. Fast path: try direct json_decode
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // 2. Look for ```json ... ``` blocks
        if (preg_match('/```json\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Look for ``` ... ``` blocks (any language)
        if (preg_match('/```(?:\w+)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // 4. Find first { or [ and extract balanced content
        $start = null;
        $openChar = null;
        $closeChar = null;
        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            if ($char === '{') {
                $start = $i;
                $openChar = '{';
                $closeChar = '}';
                break;
            }
            if ($char === '[') {
                $start = $i;
                $openChar = '[';
                $closeChar = ']';
                break;
            }
        }

        if ($start !== null) {
            $depth = 0;
            $inString = false;
            $escape = false;
            for ($i = $start; $i < strlen($text); $i++) {
                $char = $text[$i];

                if ($escape) {
                    $escape = false;

                    continue;
                }

                if ($char === '\\') {
                    $escape = true;

                    continue;
                }

                if ($char === '"' && ! $escape) {
                    $inString = ! $inString;

                    continue;
                }

                if ($inString) {
                    continue;
                }

                if ($char === $openChar) {
                    $depth++;
                } elseif ($char === $closeChar) {
                    $depth--;
                    if ($depth === 0) {
                        $candidate = substr($text, $start, $i - $start + 1);
                        $decoded = json_decode($candidate, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            return $decoded;
                        }

                        // If this didn't work but there might be more JSON, continue scanning
                        // but break for now — we found the top-level structure
                        break;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the output of a previous pipeline step, automatically
     * stripping markdown/code fences/AI commentary and extracting JSON.
     *
     * Use this inside #[Actionable] Direct Actions to easily access
     * data produced by earlier steps in the pipeline.
     *
     * @param  int|null  $stepOrder  The step order to retrieve (null = most recent completed step).
     * @param  string|null  $key  Optional dot-notation key to extract from the JSON data.
     * @param  mixed  $default  Default value if the step or key is not found.
     * @return array|mixed|null The parsed JSON data, or the specific value at $key, or $default.
     */
    public static function getPreviousStepOutput(?int $stepOrder = null, ?string $key = null, mixed $default = null): mixed
    {
        $executionService = app(PipelineExecutionService::class);
        $currentRun = $executionService->getCurrentRun();

        if (! $currentRun) {
            return $default;
        }

        $query = PipelineRunStep::where('pipeline_run_id', $currentRun->id)
            ->where('status', 'completed')
            ->orderBy('order');

        if ($stepOrder !== null) {
            $stepRun = $query->where('order', $stepOrder)->first();
        } else {
            $stepRun = $query->latest('id')->first();
        }

        if (! $stepRun || empty($stepRun->output)) {
            return $default;
        }

        $data = self::extractJsonFromText($stepRun->output);

        if ($data === null) {
            return $default;
        }

        if ($key !== null) {
            return data_get($data, $key, $default);
        }

        return $data;
    }
}
