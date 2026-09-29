<?php

namespace Agentoom\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response;
use Spatie\LaravelMarkdown\MarkdownRenderer;

class DocsExportController extends Controller
{
    /**
     * Export all documentation pages.
     */
    public function export(Request $request, string $locale, string $format)
    {
        App::setLocale($locale);
        $docs = trans('agentoom-core::docs');

        $markdown = $this->generateMarkdown($docs);

        if ($format === 'md') {
            return Response::make($markdown, 200, [
                'Content-Type' => 'text/markdown',
                'Content-Disposition' => 'attachment; filename="agentoom-docs-'.$locale.'.md"',
            ]);
        }

        if ($format === 'pdf') {
            $html = app(MarkdownRenderer::class)->toHtml($markdown);

            return view('agentoom-core::docs.export-pdf', [
                'html' => $html,
                'locale' => $locale,
                'title' => $docs['title'] ?? 'Agentoom Documentation',
            ]);
        }

        abort(404);
    }

    /**
     * Generate a single Markdown string from all documentation sections.
     */
    protected function generateMarkdown(array $docs): string
    {
        $md = '# '.($docs['title'] ?? 'Agentoom Documentation')."\n\n";

        // 1. Introduction
        if (isset($docs['what_is_agentoom'])) {
            $md .= '## '.($docs['sidebar']['what_is_agentoom'] ?? 'What is Agentoom?')."\n\n";
            $md .= ($docs['what_is_agentoom']['description'] ?? '')."\n\n";

            if (isset($docs['what_is_agentoom']['features'])) {
                $md .= '### '.($docs['what_is_agentoom']['features']['title'] ?? 'Key Features')."\n\n";
                foreach ($docs['what_is_agentoom']['features'] as $key => $feature) {
                    if ($key === 'title' || ! is_array($feature)) {
                        continue;
                    }
                    $md .= '- **'.($feature['title'] ?? '').'**: '.($feature['description'] ?? '')."\n";
                }
                $md .= "\n";
            }
        }

        // 2. Detailed Features
        if (isset($docs['features'])) {
            $md .= '## '.($docs['sidebar']['features']['title'] ?? 'Features')."\n\n";
            $skipKeys = ['how_to_use_title', 'how_it_works_title', 'configuration_title', 'options_title', 'hitl_title'];

            foreach ($docs['features'] as $key => $feature) {
                if (in_array($key, $skipKeys) || ! is_array($feature) || ! isset($feature['title'])) {
                    continue;
                }

                $md .= '### '.$feature['title']."\n\n";
                $md .= ($feature['description'] ?? '')."\n\n";

                foreach (['how_to_use', 'how_it_works', 'configuration'] as $field) {
                    if (isset($feature[$field])) {
                        $label = trans("agentoom-core::docs.features.{$field}_title", ['title' => $feature['title']]) ?: ucfirst(str_replace('_', ' ', $field));
                        $md .= '**'.$label."**\n\n".$feature[$field]."\n\n";
                    }
                }

                if (isset($feature['options']) && is_array($feature['options'])) {
                    $md .= '**'.trans('agentoom-core::docs.features.options_title')."**\n\n";
                    foreach ($feature['options'] as $option => $desc) {
                        $md .= '- **'.$option.'**: '.$desc."\n";
                    }
                    $md .= "\n";
                }

                if (isset($feature['tabs']) && is_array($feature['tabs'])) {
                    foreach ($feature['tabs'] as $tabKey => $tab) {
                        if (! is_array($tab)) {
                            continue;
                        }
                        $md .= '#### '.($tab['title'] ?? ucfirst($tabKey))."\n\n";
                        foreach ($tab as $field => $desc) {
                            if ($field === 'title') {
                                continue;
                            }
                            $md .= '- **'.ucfirst(str_replace('_', ' ', $field)).'**: '.$desc."\n";
                        }
                        $md .= "\n";
                    }
                }
            }
        }

        // 3. Examples
        if (isset($docs['sidebar']['real_life_examples'])) {
            $md .= '## '.($docs['sidebar']['real_life_examples']['title'] ?? 'Examples')."\n\n";
            foreach ($docs['sidebar']['real_life_examples'] as $key => $example) {
                if ($key === 'title' || ! is_array($example)) {
                    continue;
                }
                $md .= '### '.($example['title'] ?? '')."\n\n".($example['description'] ?? '')."\n\n";
                foreach ($example as $subKey => $subData) {
                    if (in_array($subKey, ['title', 'subtitle', 'description']) || ! is_array($subData)) {
                        continue;
                    }
                    $md .= '#### '.($subData['title'] ?? '')."\n\n";
                    $md .= '**How it works**: '.($subData['how_it_works'] ?? '')."\n\n";
                    $md .= '**Action**: '.($subData['action'] ?? '')."\n\n";
                }
            }
        }

        return $md;
    }
}
