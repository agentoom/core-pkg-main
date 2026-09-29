<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\ManageUserInformationTool;
use Agentoom\Core\Models\UserInformation;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ManageUserInformationToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_user_information()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ManageUserInformationTool;
        $request = new Request([
            'action' => 'store',
            'title' => 'Hobbies',
            'content' => 'Loves reading and hiking.',
            'importance' => 4,
            'expires_at' => '2099-01-01',
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString("Successfully stored information about 'Hobbies'", (string) $response);
        $this->assertDatabaseHas('user_information', [
            'user_id' => $user->id,
            'title' => 'Hobbies',
            'content' => 'Loves reading and hiking.',
            'importance' => 4,
        ]);
    }

    public function test_it_retrieves_user_information()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        UserInformation::create([
            'user_id' => $user->id,
            'title' => 'Interests',
            'content' => 'Technology and space.',
        ]);

        $tool = new ManageUserInformationTool;
        $request = new Request([
            'action' => 'retrieve',
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString('### Interests', (string) $response);
        $this->assertStringContainsString('Technology and space.', (string) $response);
    }

    public function test_it_excludes_expired_user_information(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        UserInformation::create([
            'user_id' => $user->id,
            'title' => 'Expired',
            'content' => 'This should not be returned.',
            'importance' => 5,
            'expires_at' => now()->subMinute(),
        ]);

        $response = (new ManageUserInformationTool)->handle(new Request([
            'action' => 'retrieve',
        ]));

        $this->assertStringNotContainsString('Expired', (string) $response);
    }
}
