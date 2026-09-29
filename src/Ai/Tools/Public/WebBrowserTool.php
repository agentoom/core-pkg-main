<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Services\WebBrowserService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class WebBrowserTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return '
        Browse the web to fetch documentation, articles, or any useful information to help achieve your task.
        Use this tool to:
        1. Search for updated documentation for frameworks, libraries, or APIs
        2. Find solutions to errors or problems you encounter
        3. Get the latest information about technologies or best practices
        4. Access official documentation, Stack Overflow, GitHub, or any public web resource

        Guidelines:
        - Provide a clear, specific URL to fetch
        - The tool will return the text content of the page
        - Use this when you need real-time or updated information beyond your training data
        - Prefer official documentation sources when available
        ';
    }

    /**
     * Fetch content from a URL.
     */
    #[Sentinelable(
        name: 'Web Browser Tool Fetch',
        description: 'Fetch and extract content from a web page URL.',
        category: 'Web'
    )]
    protected function fetch(Request $request): Stringable|string
    {
        $url = $request->string('url');
        $timeout = $request->integer('timeout', 30);
        $elements = $request->array('elements', []);
        $stripHtml = $request->boolean('stripHtml', true);

        return app(WebBrowserService::class)->fetch($url, $timeout, $elements, $stripHtml);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['fetch'])
                ->required()
                ->description('The action to perform: fetch'),

            'url' => $schema->string()
                ->required()
                ->description('The URL to fetch. Must be a valid HTTP or HTTPS URL.'),
            'timeout' => $schema->integer()
                ->default(30)
                ->description('Request timeout in seconds (default: 30)'),

            'elements' => $schema->array()
                ->default([])
                ->description('Specific HTML elements to retrieve content from (e.g., ["meta", "h1", "h2", "article"]). If empty, the entire page text is returned.'),

            'stripHtml' => $schema->boolean()
                ->default(true)
                ->description('Whether to strip HTML tags and return only readable text (default: true). Set to false to retrieve the raw HTML content for structure evaluation.'),
        ];
    }
}
