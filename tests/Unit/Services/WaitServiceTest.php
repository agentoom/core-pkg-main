<?php

namespace Agentoom\Core\Tests\Unit\Services;

use Agentoom\Core\Services\WaitService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Carbon;

class WaitServiceTest extends TestCase
{
    public function test_it_parses_various_durations(): void
    {
        $service = new class extends WaitService
        {
            public function exposeParseDuration(string $duration): Carbon
            {
                return $this->parseDuration($duration);
            }
        };

        Carbon::setTestNow('2026-05-11 15:00:00');

        $this->assertEquals('2026-05-11 15:00:10', $service->exposeParseDuration('10 seconds')->toDateTimeString());
        $this->assertEquals('2026-05-11 15:05:00', $service->exposeParseDuration('5 minutes')->toDateTimeString());
        $this->assertEquals('2026-05-11 16:00:00', $service->exposeParseDuration('1 hour')->toDateTimeString());
        $this->assertEquals('2026-05-12 15:00:00', $service->exposeParseDuration('1 day')->toDateTimeString());

        // Test plural
        $this->assertEquals('2026-05-11 15:02:00', $service->exposeParseDuration('2 minutes')->toDateTimeString());

        // Test fallback for unknown strings (should default to 1 minute)
        $this->assertEquals('2026-05-11 15:01:00', $service->exposeParseDuration('something invalid')->toDateTimeString());

        // Test numerical string (Carbon::add handles some strings, but our fallback also handles it if it fits regex)
        $result = $service->exposeParseDuration('120');
        $this->assertTrue($result->isAfter(Carbon::now()));
    }
}
