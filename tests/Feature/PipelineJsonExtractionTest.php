<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Helpers\Helpers;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineJsonExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_method_receives_parsed_json_from_markdown_output(): void
    {
        $pipeline = Pipeline::create(['name' => 'JSON Extraction Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => JsonExtractionTestStub::class,
            'method' => 'stepOne',
            'order' => 1,
            'pass_output_as_input' => true,
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => JsonExtractionTestStub::class,
            'method' => 'stepTwo',
            'order' => 2,
            'pass_output_as_input' => true,
            'instructions_override' => json_encode([
                'title' => '{title}',
                'count' => '{count}',
            ]),
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'initial input');

        $run->refresh();
        $this->assertEquals('completed', $run->status, 'Run failed: '.$run->error);

        $this->assertEquals('Found Product', JsonExtractionTestStub::$lastTitle);
        $this->assertEquals(42, JsonExtractionTestStub::$lastCount);
    }

    public function test_custom_method_uses_get_previous_step_output_helper(): void
    {
        $pipeline = Pipeline::create(['name' => 'Helper Access Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => OutputAccessTestStub::class,
            'method' => 'stepOne',
            'order' => 1,
            'pass_output_as_input' => true,
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => OutputAccessTestStub::class,
            'method' => 'stepTwo',
            'order' => 2,
            'pass_output_as_input' => true,
            'instructions_override' => '[]',
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'initial input');

        $run->refresh();
        $this->assertEquals('completed', $run->status, 'Run failed: '.$run->error);

        $this->assertTrue(OutputAccessTestStub::$accessedViaHelper);
        $this->assertEquals('product_xyz', OutputAccessTestStub::$lastSku);
    }

    public function test_extracts_json_from_all_previous_outputs(): void
    {
        $pipeline = Pipeline::create(['name' => 'All Outputs Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => JsonExtractionAllOutputsStub::class,
            'method' => 'generateData',
            'order' => 1,
            'pass_output_as_input' => true,
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => JsonExtractionAllOutputsStub::class,
            'method' => 'generateMoreData',
            'order' => 2,
            'pass_output_as_input' => true,
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => JsonExtractionAllOutputsStub::class,
            'method' => 'consolidateData',
            'order' => 3,
            'pass_all_previous_outputs' => true,
            'instructions_override' => json_encode([
                'first_value' => '{1.value}',
                'second_value' => '{2.value}',
            ]),
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'initial input');

        $run->refresh();
        $this->assertEquals('completed', $run->status, 'Run failed: '.$run->error);

        $this->assertEquals('data_one', JsonExtractionAllOutputsStub::$lastFirstValue);
        $this->assertEquals('data_two', JsonExtractionAllOutputsStub::$lastSecondValue);
    }
}

class JsonExtractionTestStub
{
    public static $lastTitle;

    public static $lastCount;

    public function stepOne(): string
    {
        return 'After analyzing the product catalog, here is the result:
```json
{
    "title": "Found Product",
    "count": 42,
    "tags": ["new", "featured"]
}
```
Please use this data in the next step.';
    }

    public function stepTwo(string $title, int $count): string
    {
        self::$lastTitle = $title;
        self::$lastCount = $count;

        return "Received: {$title} ({$count})";
    }
}

class OutputAccessTestStub
{
    public static $accessedViaHelper = false;

    public static $lastSku;

    public function stepOne(): string
    {
        return 'Here is the product data:

```json
{
    "sku": "product_xyz",
    "name": "Super Widget",
    "price": 29.99
}
```';
    }

    public function stepTwo(): string
    {
        $data = Helpers::getPreviousStepOutput(stepOrder: 1);
        if ($data && isset($data['sku'])) {
            self::$accessedViaHelper = true;
            self::$lastSku = $data['sku'];
        }

        return 'ok';
    }
}

class JsonExtractionAllOutputsStub
{
    public static $lastFirstValue;

    public static $lastSecondValue;

    public function generateData(): string
    {
        return 'First result:
```json
{"value": "data_one"}
```';
    }

    public function generateMoreData(): string
    {
        return 'Second result:
```json
{"value": "data_two"}
```';
    }

    public function consolidateData(string $first_value, string $second_value): string
    {
        self::$lastFirstValue = $first_value;
        self::$lastSecondValue = $second_value;

        return "Consolidated: {$first_value}, {$second_value}";
    }
}
