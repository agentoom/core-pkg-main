<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\IconPicker;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class IconPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin']);
        }
    }

    public function test_icon_picker_lists_icons(): void
    {
        Livewire::test(IconPicker::class)
            ->assertViewHas('icons', function ($icons) {
                return count($icons) > 0 && in_array('academic-cap', $icons);
            })
            ->assertViewHas('total', function ($total) {
                return $total > 100;
            });
    }

    public function test_icon_picker_can_search_icons(): void
    {
        Livewire::test(IconPicker::class)
            ->set('search', 'magnifying-glass')
            ->assertViewHas('icons', function ($icons) {
                foreach ($icons as $icon) {
                    if (! str_contains($icon, 'magnifying-glass')) {
                        return false;
                    }
                }

                return count($icons) > 0;
            });
    }

    public function test_icon_picker_dispatches_event_on_selection(): void
    {
        Livewire::test(IconPicker::class)
            ->call('selectIcon', 'cube')
            ->assertDispatched('icon-selected', icon: 'cube');
    }
}
