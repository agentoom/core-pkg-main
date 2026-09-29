<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CoreAssetsAndLoginLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_images_exist_inside_core_package(): void
    {
        $coreImagesPath = base_path('packages/agentoom/core-main/public/images');

        $this->assertDirectoryExists($coreImagesPath);

        $expectedFiles = [
            'agentoom_logo.svg',
            'android-chrome-192x192.png',
            'android-chrome-512x512.png',
            'apple-touch-icon.png',
            'favicon-16x16.png',
            'favicon-32x32.png',
            'favicon.ico',
            'site.webmanifest',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists($coreImagesPath.'/'.$file);
        }

        // Verify the logo contains the gradient definition
        $logoContent = file_get_contents($coreImagesPath.'/agentoom_logo.svg');
        $this->assertStringContainsString('agentoom_logo_gradient', $logoContent);
        $this->assertStringContainsString('#00fbff', $logoContent);
        $this->assertStringContainsString('#bc13fe', $logoContent);
    }

    public function test_images_route_serves_core_image(): void
    {
        $response = $this->get('/images/agentoom_logo.svg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertFileExists($response->getFile()->getPathname());
        $this->assertStringContainsString('agentoom_logo_gradient', file_get_contents($response->getFile()->getPathname()));
    }

    public function test_images_route_returns_404_for_missing_file(): void
    {
        $response = $this->get('/images/non_existent.svg');
        $response->assertNotFound();
    }

    public function test_images_route_prevents_directory_traversal(): void
    {
        $response = $this->get('/images/../../composer.json');
        $response->assertNotFound();
    }

    public function test_login_page_renders_logo_with_gradient(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertStringContainsString('images/agentoom_logo.svg', $response->getContent());
    }
}
