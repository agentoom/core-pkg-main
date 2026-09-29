<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Livewire\WidgetChat;
use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class WidgetLanguageDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_livewire_property(): void
    {
        $agent = Agent::factory()->create(['is_active' => true, 'is_public' => true]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
            'slug' => 'test-widget-basic',
        ]);
        $widget->save();

        $test = Livewire::test(WidgetChat::class, ['widget' => $widget->id]);
        dd($test->instance()->selectedLanguage);

        Livewire::test(WidgetChat::class, ['widget' => $widget])
            ->assertSet('selectedLanguage', 'en');
    }

    public function test_widget_chat_sets_language_from_query_parameter(): void
    {
        // 1. Setup languages
        Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true, 'is_default' => true]);
        Language::create(['name' => 'Italian', 'code' => 'it', 'is_active' => true, 'is_default' => false]);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
            'slug' => 'test-widget',
        ]);
        $widget->save();

        // 2. Test with locale=it in query string
        Livewire::withQueryParams(['locale' => 'it'])
            ->test(WidgetChat::class, ['widget' => $widget])
            ->assertSet('selectedLanguage', 'it');

        $this->assertEquals('it', session('chat_locale'));
        $this->assertEquals('it', app()->getLocale());
    }

    public function test_widget_chat_defaults_to_default_language_if_locale_not_supported(): void
    {
        // 1. Setup languages
        Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true, 'is_default' => true]);
        Language::create(['name' => 'Italian', 'code' => 'it', 'is_active' => true, 'is_default' => false]);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
            'slug' => 'test-widget-2',
        ]);
        $widget->save();

        // 2. Test with locale=fr (not supported)
        Livewire::withQueryParams(['locale' => 'fr'])
            ->test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('selectedLanguage', 'en');

        $this->assertEquals('en', session('chat_locale'));
    }

    public function test_widget_chat_uses_session_if_no_query_param(): void
    {
        // 1. Setup languages
        Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true, 'is_default' => true]);
        Language::create(['name' => 'Italian', 'code' => 'it', 'is_active' => true, 'is_default' => false]);

        session(['chat_locale' => 'it']);

        $agent = Agent::factory()->create([
            'is_active' => true,
            'is_public' => true,
        ]);
        $widget = new AgentWidget([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'position' => 'bottom-right',
            'slug' => 'test-widget-3',
        ]);
        $widget->save();

        // 2. Test without query param
        Livewire::test('agentoom-chat-widget::widget-chat', ['widget' => $widget])
            ->assertSet('selectedLanguage', 'it');
    }
}
