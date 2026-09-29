<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Carbon;

class PipelineRunDurationTest extends TestCase
{
    public function test_it_calculates_duration_correctly(): void
    {
        $startedAt = Carbon::now()->subSeconds(10);
        $completedAt = Carbon::now();

        $run = new PipelineRun([
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        // Accessing the properties to ensure they are set (even if not saved to DB)
        $run->started_at = $startedAt;
        $run->completed_at = $completedAt;

        $this->assertEquals(10, $run->durationInSeconds());
    }

    public function test_it_returns_zero_if_dates_are_missing(): void
    {
        $run = new PipelineRun;
        $this->assertEquals(0, $run->durationInSeconds());

        $run->started_at = Carbon::now();
        $this->assertEquals(0, $run->durationInSeconds());

        $run->started_at = null;
        $run->completed_at = Carbon::now();
        $this->assertEquals(0, $run->durationInSeconds());
    }
}
