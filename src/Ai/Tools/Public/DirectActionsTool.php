<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Services\ActionDiscoveryService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class DirectActionsTool implements Tool
{
    use HandlesToolActions;

    public function description(): Stringable|string
    {
        return 'List all available DirectActions (Actionable methods) that can be used as pipeline steps, '
            .'along with their parameters, output injection syntax, filter/condition operators, '
            .'and pipeline variable shortcodes. Use this tool whenever you need to build or suggest '
            .'a pipeline configuration so you know exactly what actions exist and how to wire them together.';
    }

    /**
     * List all available DirectActions with full parameter details and usage instructions.
     */
    #[Sentinelable(
        name: 'Direct Actions Tool List Actions',
        description: 'List all available DirectActions (Actionable methods) grouped by category, with parameter types and descriptions.',
        category: 'Pipelines'
    )]
    protected function listDirectActions(Request $request): string
    {
        $category = $request->string('category') ?: null;
        $search = $request->string('search') ?: null;

        /** @var ActionDiscoveryService $discovery */
        $discovery = app(ActionDiscoveryService::class);
        $all = $discovery->discover();

        // Filter to local Actionable methods only (exclude MCP remote actions), preserving string keys
        $actions = collect($all)
            ->filter(fn ($a) => ($a['source'] ?? 'local') === 'local');

        if ($category) {
            $actions = $actions->filter(fn ($a) => stripos($a['category'] ?? '', $category) !== false);
        }

        if ($search) {
            $actions = $actions->filter(
                fn ($a) => stripos($a['name'] ?? '', $search) !== false
                    || stripos($a['description'] ?? '', $search) !== false
            );
        }

        // Embed the original Class@method key inside each entry so groupBy doesn't lose it
        $actions = $actions->map(fn ($a, $k) => array_merge($a, ['_key' => $k]));

        if ($actions->isEmpty()) {
            return 'No DirectActions found'
                .($category ? " in category \"{$category}\"" : '')
                .($search ? " matching \"{$search}\"" : '')
                .'.';
        }

        $grouped = $actions->groupBy('category');
        $output = "# Available DirectActions\n\n";
        $output .= 'DirectActions are methods you can assign to a pipeline step (class + method). ';
        $output .= "Each step's parameters are stored as a JSON object in `instructions_override`.\n\n";

        foreach ($grouped->sortKeys() as $cat => $items) {
            $output .= "## {$cat}\n\n";

            foreach ($items as $action) {
                $actionKey = $action['_key'] ?? '';
                $output .= "### {$action['name']}\n";
                $output .= "**Key:** `{$actionKey}`\n";
                $output .= "**Class:** `{$action['display_class']}` | **Method:** `{$action['display_method']}`\n";

                if (! empty($action['description'])) {
                    $output .= "**Description:** {$action['description']}\n";
                }

                if (! empty($action['parameters'])) {
                    $output .= "**Parameters:**\n";
                    foreach ($action['parameters'] as $paramName => $paramInfo) {
                        $type = $paramInfo['type'] ?? 'any';
                        $desc = $paramInfo['description'] ?? '';
                        $output .= "  - `{$paramName}` *($type)*";
                        if ($desc) {
                            $output .= ": {$desc}";
                        }
                        $output .= "\n";
                    }
                } else {
                    $output .= "**Parameters:** *(none)*\n";
                }

                $output .= "\n";
            }
        }

        $output .= $this->buildUsageGuide();

        return $output;
    }

    /**
     * Get the full usage guide for DirectActions in pipelines (output injection, filters, variables).
     */
    #[Sentinelable(
        name: 'Direct Actions Tool Get Usage Guide',
        description: 'Get the complete guide on how to use DirectActions in pipelines: output injection with JSON dot-notation, condition/filter operators, and pipeline variable shortcodes.',
        category: 'Pipelines'
    )]
    protected function getUsageGuide(Request $request): string
    {
        return $this->buildUsageGuide(detailed: true);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list_direct_actions', 'get_usage_guide'])
                ->required()
                ->description('The action to perform. Use "list_direct_actions" to browse available actions, "get_usage_guide" for the full wiring guide.'),
            'category' => $schema->string()
                ->description('(list_direct_actions only) Filter actions by category name (partial match, case-insensitive). E.g. "Data", "Pipelines", "Utilities".'),
            'search' => $schema->string()
                ->description('(list_direct_actions only) Filter actions by keyword in name or description (partial match, case-insensitive).'),
        ];
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function buildUsageGuide(bool $detailed = false): string
    {
        $guide = "\n---\n\n";
        $guide .= "# DirectActions Usage Guide\n\n";

        // ── Parameters / instructions_override ──────────────────────────────
        $guide .= "## 1. Defining Parameters for a DirectAction Step\n\n";
        $guide .= "Store parameters as a JSON object in `instructions_override`. Two formats are accepted:\n\n";
        $guide .= "**Simple key-value object** (recommended):\n";
        $guide .= "```json\n{\"name\": \"My Resource\", \"description\": \"Created by pipeline\", \"resource_type_id\": 3}\n```\n\n";
        $guide .= "**JsonStructureHelper array format** (used by the UI):\n";
        $guide .= "```json\n[\n  {\"name\": \"name\", \"type\": \"string\", \"value\": \"My Resource\"},\n  {\"name\": \"resource_type_id\", \"type\": \"integer\", \"value\": 3}\n]\n```\n\n";

        // ── Output injection ─────────────────────────────────────────────────
        $guide .= "## 2. Injecting Output from Previous Steps\n\n";
        $guide .= 'Use `{placeholder}` syntax inside any parameter value string. ';
        $guide .= "Dot-notation accesses nested JSON fields.\n\n";
        $guide .= "| Placeholder | Resolves to |\n";
        $guide .= "|---|---|\n";
        $guide .= "| `{output}` | The full output of the immediately preceding step |\n";
        $guide .= "| `{previous_output}` | Alias for `{output}` |\n";
        $guide .= "| `{field}` | Top-level field `field` from the previous step's JSON output |\n";
        $guide .= "| `{field.nested}` | Nested field using dot-notation (e.g. `{user.email}`) |\n";
        $guide .= "| `{items.0.id}` | First item in a list output, field `id` |\n";
        $guide .= "| `{2}` | Full output of step number 2 (1-based) |\n";
        $guide .= "| `{2.field}` | Field `field` from step 2's JSON output |\n";
        $guide .= "| `{Step Name}` | Full output of the step named \"Step Name\" |\n";
        $guide .= "| `{Step Name.field}` | Field from a named step's JSON output |\n";
        $guide .= "| `{input}` | The original pipeline input |\n\n";
        $guide .= "**Examples:**\n";
        $guide .= "```json\n{\"resource_id\": \"{id}\", \"name\": \"Report for {user.name}\"}\n```\n";
        $guide .= "```json\n{\"data\": \"{2.results}\"}\n```\n\n";
        $guide .= "> **Tip:** If the previous step outputs a JSON list, the first item's fields are also available directly: `{id}`, `{name}`, etc. The full list is at `{items}`.\n\n";

        // ── Pipeline variables ───────────────────────────────────────────────
        $guide .= "## 3. Pipeline Variables (Shortcodes)\n\n";
        $guide .= "Pipeline-level variables defined in the pipeline config are injected with double-brace syntax **before** output variables are resolved:\n\n";
        $guide .= "```\n{{variable_key}}\n```\n\n";
        $guide .= "Example: if the pipeline has a variable `env` = `production`, use `{{env}}` in any parameter value.\n\n";

        // ── Conditions / filters ─────────────────────────────────────────────
        $guide .= "## 4. Step Conditions (Filters)\n\n";
        $guide .= "Set `condition_if` on a step to control whether it runs. Two formats:\n\n";
        $guide .= "**Plain text** — step runs only if the previous output/input contains the text (case-insensitive):\n";
        $guide .= "```\nsuccess\n```\n\n";
        $guide .= "**Structured JSON** — precise field comparison:\n";
        $guide .= "```json\n{\"field\": \"status\", \"operator\": \"equals\", \"value\": \"active\"}\n```\n\n";
        $guide .= "Supported operators:\n\n";
        $guide .= "| Operator | Aliases | Meaning |\n";
        $guide .= "|---|---|---|\n";
        $guide .= "| `equals` | `=`, `==` | Exact match |\n";
        $guide .= "| `not_equals` | `!=`, `<>` | Not equal |\n";
        $guide .= "| `greater_than` | `>`, `is_greater` | Numeric greater |\n";
        $guide .= "| `less_than` | `<`, `is_smaller` | Numeric less |\n";
        $guide .= "| `>=` | | Greater or equal |\n";
        $guide .= "| `<=` | | Less or equal |\n";
        $guide .= "| `contains` | | String contains |\n";
        $guide .= "| `not_contains` | `does_not_contain` | String does not contain |\n";
        $guide .= "| `starts_with` | | String starts with |\n";
        $guide .= "| `ends_with` | | String ends with |\n";
        $guide .= "| `is_empty` | | Value is empty/null |\n";
        $guide .= "| `is_not_empty` | | Value is not empty |\n";
        $guide .= "| `exists` | | Field is present (not null) |\n";
        $guide .= "| `in` | | Value is in a comma-separated list or array |\n";
        $guide .= "| `not_in` | | Value is not in list |\n";
        $guide .= "| `matches` | | Value matches a regex pattern |\n\n";
        $guide .= 'The `field` key uses dot-notation to address nested JSON fields (same as output injection). ';
        $guide .= "Leave `field` empty to match against the entire output string.\n\n";
        $guide .= "Use `condition_on_previous`, `condition_on_input`, `condition_on_current` booleans to control which context is evaluated.\n\n";

        if ($detailed) {
            // ── Branching ────────────────────────────────────────────────────
            $guide .= "## 5. Branching (next_step_on_true / next_step_on_false)\n\n";
            $guide .= 'When a step has a `condition_if`, set `next_step_on_true` and/or `next_step_on_false` to the 1-based step order number to jump to on each outcome. ';
            $guide .= "If not set, the pipeline continues sequentially.\n\n";

            // ── Error handling ───────────────────────────────────────────────
            $guide .= "## 6. Error Handling\n\n";
            $guide .= "| Field | Values | Meaning |\n";
            $guide .= "|---|---|---|\n";
            $guide .= "| `on_error` | `continue` (default), `stop`, `jump` | What to do when the step fails |\n";
            $guide .= "| `next_step_on_error` | step order number | Jump target when `on_error` = `jump` |\n";
            $guide .= "| `retry_attempts` | integer | How many times to retry before failing |\n";
            $guide .= "| `retry_delay` | seconds | Delay between retries |\n";
            $guide .= "| `is_failure_handler` | boolean | Mark this step as the global failure handler |\n\n";

            // ── Output passing ───────────────────────────────────────────────
            $guide .= "## 7. Output Passing Between Steps\n\n";
            $guide .= "| Field | Meaning |\n";
            $guide .= "|---|---|\n";
            $guide .= "| `pass_output_as_input` | Pass the previous step's output as the agent prompt input |\n";
            $guide .= "| `pass_all_previous_outputs` | Inject all previous step outputs into the agent context |\n";
            $guide .= "| `selected_outputs` | Array of step order numbers whose outputs to inject selectively |\n\n";

            // ── Full example ─────────────────────────────────────────────────
            $guide .= "## 8. Full Example: Two-Step DirectAction Pipeline\n\n";
            $guide .= "**Step 1** — Create a resource (no dependencies):\n";
            $guide .= "```json\n";
            $guide .= "{\n";
            $guide .= "  \"class\": \"Agentoom\\\\Core\\\\Services\\\\ResourceManagementService\",\n";
            $guide .= "  \"method\": \"createGlobalResource\",\n";
            $guide .= "  \"instructions_override\": \"{\\\"name\\\": \\\"{{env}} Server\\\", \\\"resource_type_id\\\": 3, \\\"data\\\": {\\\"host\\\": \\\"{{host}}\\\"}}\"\n";
            $guide .= "}\n";
            $guide .= "```\n\n";
            $guide .= "**Step 2** — Update the resource created in step 1 (inject `{id}` from step 1's output):\n";
            $guide .= "```json\n";
            $guide .= "{\n";
            $guide .= "  \"class\": \"Agentoom\\\\Core\\\\Services\\\\ResourceManagementService\",\n";
            $guide .= "  \"method\": \"updateGlobalResource\",\n";
            $guide .= "  \"instructions_override\": \"{\\\"resource_id\\\": \\\"{id}\\\", \\\"data\\\": {\\\"status\\\": \\\"ready\\\"}}\",\n";
            $guide .= "  \"condition_if\": \"{\\\"field\\\": \\\"id\\\", \\\"operator\\\": \\\"exists\\\", \\\"value\\\": null}\"\n";
            $guide .= "}\n";
            $guide .= "```\n";
        }

        return $guide;
    }
}
