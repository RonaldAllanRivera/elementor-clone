<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DesignFigmaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_from_figma_requires_authentication(): void
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
            'figma_url' => 'https://www.figma.com/design/abc123/Test?node-id=1-2',
            'layout_json' => null,
            'html' => null,
        ]);

        $response = $this->post(route('designs.importFromFigma', $design, absolute: false));

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_import_from_figma_updates_layout_and_html_for_owner(): void
    {
        Http::fake([
            'api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Frame A',
                            'children' => [
                                [
                                    'id' => '10:1',
                                    'type' => 'TEXT',
                                    'characters' => 'Hello World',
                                    'style' => ['fontSize' => 32],
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

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
            'figma_url' => 'https://www.figma.com/design/abc123/Test?node-id=1-2',
            'layout_json' => null,
            'html' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('designs.importFromFigma', $design, absolute: false));

        $response->assertRedirect(route('designs.show', $design, absolute: false));

        $design->refresh();

        $this->assertIsArray($design->layout_json);
        $this->assertSame('section', $design->layout_json['type'] ?? null);
        $this->assertNotNull($design->html);
        $this->assertStringContainsString('Hello World', (string) $design->html);
    }
}
