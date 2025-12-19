<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignElementorExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_elementor_requires_authentication(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'My Design',
            'description' => null,
            'layout_json' => [
                'type' => 'section',
                'children' => [
                    ['type' => 'heading', 'text' => 'Hello', 'level' => 2],
                ],
            ],
            'html' => null,
        ]);

        $response = $this->get(route('designs.exportElementor', $design, absolute: false));

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_export_elementor_is_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $project = Project::create([
            'user_id' => $owner->id,
            'name' => 'Owner Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'Owner Design',
            'description' => null,
            'layout_json' => ['type' => 'text', 'text' => 'Hello'],
            'html' => null,
        ]);

        $response = $this
            ->actingAs($other)
            ->get(route('designs.exportElementor', $design, absolute: false));

        $response->assertForbidden();
    }

    public function test_export_elementor_downloads_json_for_owner(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'My Design',
            'description' => null,
            'layout_json' => [
                'type' => 'section',
                'children' => [
                    ['type' => 'heading', 'text' => 'Hello World', 'level' => 1],
                ],
            ],
            'html' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('designs.exportElementor', $design, absolute: false));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('my-design-elementor.json', (string) $response->headers->get('content-disposition'));

        $json = $response->streamedContent();
        $payload = json_decode($json, true);

        $this->assertIsArray($payload);
        $this->assertSame('My Design', $payload['title']);
        $this->assertSame('page', $payload['type']);
        $this->assertSame('0.4', $payload['version']);
    }

    public function test_export_elementor_downloads_container_format_for_owner(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'My Design',
            'description' => null,
            'layout_json' => [
                'type' => 'section',
                'children' => [
                    ['type' => 'heading', 'text' => 'Hello World', 'level' => 1],
                ],
            ],
            'html' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('designs.exportElementor', $design, absolute: false) . '?format=container');

        $response->assertOk();
        $this->assertStringContainsString('my-design-elementor-container.json', (string) $response->headers->get('content-disposition'));

        $json = $response->streamedContent();
        $payload = json_decode($json, true);

        $this->assertIsArray($payload);
        $this->assertSame('container', $payload['content'][0]['elType']);
    }

    public function test_export_elementor_downloads_classic_simple_format_for_owner(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'My Design',
            'description' => null,
            'layout_json' => [
                'type' => 'section',
                'children' => [
                    [
                        'type' => 'section',
                        'children' => [
                            ['type' => 'heading', 'text' => 'Block A', 'level' => 2],
                        ],
                    ],
                    [
                        'type' => 'section',
                        'children' => [
                            ['type' => 'heading', 'text' => 'Block B', 'level' => 2],
                        ],
                    ],
                ],
            ],
            'html' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('designs.exportElementor', $design, absolute: false) . '?format=classic_simple');

        $response->assertOk();
        $this->assertStringContainsString('my-design-elementor-simple.json', (string) $response->headers->get('content-disposition'));

        $payload = json_decode($response->streamedContent(), true);
        $this->assertIsArray($payload);
        $this->assertCount(2, $payload['content']);
    }

    public function test_elementor_json_view_requires_authentication(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'My Design',
            'description' => null,
            'layout_json' => ['type' => 'text', 'text' => 'Hello'],
            'html' => null,
        ]);

        $response = $this->get(route('designs.elementorJson', $design, absolute: false));

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_elementor_json_view_renders_for_owner(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'My Design',
            'description' => null,
            'layout_json' => ['type' => 'text', 'text' => 'Hello'],
            'html' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('designs.elementorJson', $design, absolute: false));

        $response->assertOk();
        $response->assertSee('Elementor JSON');
        $response->assertSee('"title": "My Design"', false);
    }

    public function test_elementor_json_view_renders_container_format_for_owner(): void
    {
        $user = User::factory()->create();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'Test Project',
            'description' => null,
        ]);

        $design = Design::create([
            'project_id' => $project->id,
            'name' => 'My Design',
            'description' => null,
            'layout_json' => ['type' => 'text', 'text' => 'Hello'],
            'html' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('designs.elementorJson', $design, absolute: false) . '?format=container');

        $response->assertOk();
        $response->assertSee('"elType": "container"', false);
    }
}
