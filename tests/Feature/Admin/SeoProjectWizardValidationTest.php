<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\SeoProjectWizard;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class SeoProjectWizardValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin']);
        }
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    public function test_it_fails_validation_when_domain_is_too_long(): void
    {
        Livewire::test(SeoProjectWizard::class)
            ->set('step', 1)
            ->set('domain', str_repeat('a', 256))
            ->call('next')
            ->assertHasErrors(['domain' => 'max']);
    }

    public function test_it_fails_validation_when_business_model_is_too_long(): void
    {
        Livewire::test(SeoProjectWizard::class)
            ->set('step', 1)
            ->set('business_model', str_repeat('a', 256))
            ->call('next')
            ->assertHasErrors(['business_model' => 'max']);
    }

    public function test_it_validates_all_fields_on_submit(): void
    {
        // This test should fail if submit() only validates the current step (10)
        Livewire::test(SeoProjectWizard::class)
            ->set('step', 10)
            ->call('submit')
            ->assertHasErrors(['domain', 'business_model', 'primary_goal', 'description', 'ideal_customer', 'pricing_level']);
    }

    public function test_it_can_submit_valid_data(): void
    {
        Livewire::test(SeoProjectWizard::class)
            ->set('domain', 'example-valid.com')
            ->set('business_model', 'saas')
            ->set('primary_goal', 'leads')
            ->set('description', 'A valid description')
            ->set('ideal_customer', 'Ideal customer')
            ->set('pricing_level', 'medium')
            ->set('site_type', 'new')
            ->set('content_capacity', 'medium')
            ->set('step', 10)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('seo_projects', [
            'domain' => 'example-valid.com',
            'business_model' => 'saas',
        ]);
    }
}
