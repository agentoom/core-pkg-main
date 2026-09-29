<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\Api\PrestaShopService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScheduleResolveModelErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_model_handles_non_eloquent_model_gracefully()
    {
        $user = User::factory()->create();

        $schedule = Schedule::create([
            'name' => 'Sync PrestaShop',
            'minute' => '*',
            'hour' => '*',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'model' => PrestaShopService::class,
            'method' => 'sync',
            'user_id' => $user->id,
            'active' => true,
        ]);

        // This should not throw "Call to undefined method Agentoom\Core\Services\Api\PrestaShopService::findOrFail()"
        $target = $schedule->resolveModel();

        $this->assertNull($target);
    }
}
