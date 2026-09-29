<?php

declare(strict_types=1);

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Guards the "Govern" section of the sidebar navigation.
 *
 * Trust Layer and EU AI Act Compliance ship as free packages: their sections
 * are always rendered when installed, never as an upgrade prompt. Only the
 * optional remote privacy engine (agentoom/privacy-pro) contributes a
 * conditional entry, nested inside Trust Layer.
 */
class SidebarGovernEntriesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'superadmin']);
        $this->admin->assignRole('superadmin');

        $this->actingAs($this->admin);
    }

    private function renderSidebar(): string
    {
        return view('agentoom-core::layouts.sidebar')->render();
    }

    /**
     * Distinct texts of the elements matching an XPath query.
     *
     * Flux renders every nav entry more than once (desktop and collapsed
     * variants), so entries are compared as sets rather than by occurrence.
     *
     * @return string[]
     */
    private function texts(string $html, string $query): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $texts = [];

        foreach ((new DOMXPath($document))->query($query) as $node) {
            $texts[] = trim((string) preg_replace('/\s+/', ' ', $node->textContent));
        }

        return array_values(array_unique($texts));
    }

    public function test_free_capabilities_render_as_sections_without_upgrade_prompts(): void
    {
        $html = $this->renderSidebar();
        $headings = $this->texts($html, '//*[@data-flux-menu-heading]');
        $entries = $this->texts($html, '//*[@data-content]');

        $this->assertContains('Trust Layer', $headings);
        $this->assertContains('EU AI Act Compliance', $headings);

        // No capability is advertised as premium any more.
        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('Premium', $entry, "unexpected upgrade prompt: {$entry}");
        }

        $this->assertStringNotContainsString('premium/contact', $html);
    }

    public function test_trust_layer_links_to_its_pages(): void
    {
        $html = $this->renderSidebar();
        $links = $this->texts($html, '//a/@href');
        $joined = implode("\n", $links);

        $this->assertStringContainsString('/admin/privacy-recognizers', $joined);
        $this->assertStringContainsString('/admin/pii-masking-rules', $joined);
    }

    public function test_remote_engine_entry_tracks_privacy_pro_availability(): void
    {
        $html = $this->renderSidebar();
        $entries = $this->texts($html, '//*[@data-content]');
        $links = implode("\n", $this->texts($html, '//a/@href'));

        // agentoom/privacy-pro is installed in this application, so its settings
        // page is reachable from Trust Layer; without the package neither the
        // route nor the entry exists.
        $this->assertSame(
            \Illuminate\Support\Facades\Route::has('admin.external-privacy-settings'),
            str_contains($links, '/admin/external-privacy-settings'),
        );
        $this->assertSame(
            \Illuminate\Support\Facades\Route::has('admin.external-privacy-settings'),
            in_array('External Privacy Service', $entries, true),
        );
    }

    public function test_tool_definitions_entry_is_labelled_sentinel(): void
    {
        $entries = $this->texts($this->renderSidebar(), '//*[@data-content]');

        $this->assertContains('Sentinel', $entries);
        $this->assertNotContains('Definitions', $entries);
    }
}
