<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Services\WebBrowserService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class WebBrowserServiceTest extends TestCase
{
    public function test_it_can_fetch_content_from_url(): void
    {
        Http::fake([
            'https://example.com' => Http::response('<h1>Hello World</h1><p>Test content</p>', 200, ['Content-Type' => 'text/html']),
        ]);

        $service = new WebBrowserService;
        $output = $service->fetch('https://example.com');

        $this->assertStringContainsString('Successfully fetched content from: https://example.com', $output);
        $this->assertStringContainsString('[H1]Hello World[/H1]', $output);
        $this->assertStringContainsString('Test content', $output);
    }

    public function test_it_handles_fetch_errors(): void
    {
        Http::fake([
            'https://example.com' => Http::response('Not Found', 404),
        ]);

        $service = new WebBrowserService;
        $output = $service->fetch('https://example.com');

        $this->assertStringContainsString('Error: Failed to fetch URL. HTTP Status: 404', $output);
    }

    public function test_it_validates_url(): void
    {
        $service = new WebBrowserService;

        $output = $service->fetch('invalid-url');
        $this->assertStringContainsString('Error: Invalid URL provided', $output);

        $output = $service->fetch('ftp://example.com');
        $this->assertStringContainsString('Error: Only HTTP and HTTPS protocols are allowed', $output);
    }

    public function test_it_limits_content_length(): void
    {
        $longContent = str_repeat('A', 60000);
        Http::fake([
            'https://example.com' => Http::response($longContent, 200, ['Content-Type' => 'text/plain']),
        ]);

        $service = new WebBrowserService;
        $output = $service->fetch('https://example.com');

        $this->assertStringContainsString('[Content truncated due to length...]', $output);

        // Extract content between markers
        if (preg_match('/--- Content Start ---\s+(.*?)\s+--- Content End ---/s', $output, $matches)) {
            $content = $matches[1];
            $this->assertLessThan(60000, strlen($content));
            $this->assertStringContainsString('[Content truncated due to length...]', $content);
        } else {
            $this->fail('Content markers not found in output');
        }
    }

    public function test_it_can_fetch_specific_elements(): void
    {
        $html = <<<'HTML'
<html>
    <head>
        <meta name="description" content="Test description">
        <title>Test Page</title>
    </head>
    <body>
        <h1>Title 1</h1>
        <p>Some random paragraph that should be ignored if specific elements are requested.</p>
        <h2>Subtitle</h2>
        <article>
            <p>Article content</p>
        </article>
        <div>Other content</div>
    </body>
</html>
HTML;

        Http::fake([
            'https://example.com' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $service = new WebBrowserService;

        // Fetch with specific elements
        $output = $service->fetch('https://example.com', 30, ['h1', 'h2', 'article']);

        $this->assertStringContainsString('[H1]Title 1[/H1]', $output);
        $this->assertStringContainsString('[H2]Subtitle[/H2]', $output);
        $this->assertStringContainsString('[ARTICLE]Article content[/ARTICLE]', $output);
        $this->assertStringNotContainsString('Some random paragraph', $output);
        $this->assertStringNotContainsString('Other content', $output);
    }

    public function test_it_can_fetch_meta_elements(): void
    {
        $html = <<<'HTML'
<html>
    <head>
        <meta name="description" content="Test description">
        <meta property="og:title" content="OG Title">
        <title>Test Page</title>
    </head>
    <body>
        <h1>Title 1</h1>
    </body>
</html>
HTML;

        Http::fake([
            'https://example.com' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $service = new WebBrowserService;

        // Fetch with meta elements
        $output = $service->fetch('https://example.com', 30, ['meta', 'h1']);

        $this->assertStringContainsString('[META]name="description" content="Test description"[/META]', $output);
        $this->assertStringContainsString('[META]property="og:title" content="OG Title"[/META]', $output);
        $this->assertStringContainsString('[H1]Title 1[/H1]', $output);
    }

    public function test_it_removes_scripts_and_styles_by_default(): void
    {
        $html = <<<'HTML'
<html>
    <head>
        <title>Page Title</title>
        <style>body { color: red; }</style>
    </head>
    <body>
        <script>console.log('hello');</script>
        <h1>Main Title</h1>
        <p>This is a paragraph.</p>
        <style>.hidden { display: none; }</style>
        <div class="footer">
            <p>Footer content</p>
        </div>
    </body>
</html>
HTML;

        Http::fake([
            'https://example.com' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $service = new WebBrowserService;
        $output = $service->fetch('https://example.com');

        $this->assertStringContainsString('[TITLE]Page Title[/TITLE]', $output);
        $this->assertStringContainsString('[H1]Main Title[/H1]', $output);
        $this->assertStringContainsString('This is a paragraph', $output);

        // These should NOT be in the output
        $this->assertStringNotContainsString('body { color: red; }', $output);
        $this->assertStringNotContainsString("console.log('hello')", $output);
        $this->assertStringNotContainsString('.hidden { display: none; }', $output);
    }

    public function test_it_does_not_destroy_nested_content_markers(): void
    {
        $html = <<<'HTML'
<html>
    <body>
        <article>
            <h1>Inside Article</h1>
            <p>Some text</p>
        </article>
    </body>
</html>
HTML;

        Http::fake([
            'https://example.com' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $service = new WebBrowserService;
        $output = $service->fetch('https://example.com');

        // If it works correctly, we should see both markers
        $this->assertStringContainsString('[ARTICLE]', $output);
        $this->assertStringContainsString('[H1]Inside Article[/H1]', $output);
        $this->assertStringContainsString('Some text', $output);
    }
}
