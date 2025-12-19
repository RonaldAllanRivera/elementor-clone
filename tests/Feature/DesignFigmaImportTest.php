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
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Frame A',
                            'layoutMode' => 'HORIZONTAL',
                            'itemSpacing' => 24,
                            'paddingTop' => 12,
                            'paddingRight' => 12,
                            'paddingBottom' => 12,
                            'paddingLeft' => 12,
                            'children' => [
                                [
                                    'id' => 'nav:1',
                                    'type' => 'FRAME',
                                    'name' => 'Nav',
                                    'layoutMode' => 'HORIZONTAL',
                                    'itemSpacing' => 16,
                                    'children' => [
                                        [
                                            'id' => 'nav:1:1',
                                            'type' => 'TEXT',
                                            'characters' => 'Shop',
                                            'style' => ['fontSize' => 14],
                                            'fills' => [['type' => 'SOLID', 'color' => ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1]]],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 40, 'height' => 20],
                                        ],
                                        [
                                            'id' => 'nav:1:2',
                                            'type' => 'TEXT',
                                            'characters' => 'On Sale',
                                            'style' => ['fontSize' => 14],
                                            'fills' => [['type' => 'SOLID', 'color' => ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1]]],
                                            'absoluteBoundingBox' => ['x' => 60, 'y' => 0, 'width' => 60, 'height' => 20],
                                        ],
                                    ],
                                ],
                                [
                                    'id' => '10:1',
                                    'type' => 'TEXT',
                                    'characters' => 'Hello World',
                                    'style' => ['fontSize' => 32],
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 40],
                                ],
                                [
                                    'id' => '10:2',
                                    'type' => 'TEXT',
                                    'characters' => 'Second Column',
                                    'style' => ['fontSize' => 20],
                                    'absoluteBoundingBox' => ['x' => 240, 'y' => 0, 'width' => 200, 'height' => 30],
                                ],
                                [
                                    'id' => 'btn:1',
                                    'type' => 'FRAME',
                                    'name' => 'Button',
                                    'layoutMode' => 'HORIZONTAL',
                                    'paddingTop' => 12,
                                    'paddingRight' => 18,
                                    'paddingBottom' => 12,
                                    'paddingLeft' => 18,
                                    'fills' => [['type' => 'SOLID', 'color' => ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1]]],
                                    'children' => [
                                        [
                                            'id' => 'btn:1:1',
                                            'type' => 'TEXT',
                                            'characters' => 'Shop Now',
                                            'style' => ['fontSize' => 14],
                                            'fills' => [['type' => 'SOLID', 'color' => ['r' => 1, 'g' => 1, 'b' => 1, 'a' => 1]]],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 80, 'height' => 20],
                                        ],
                                    ],
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
        $this->assertSame('columns', $design->layout_json['children'][0]['type'] ?? null);
        $this->assertNotNull($design->html);
        $this->assertStringContainsString('Hello World', (string) $design->html);
        $this->assertStringContainsString('Second Column', (string) $design->html);
        $this->assertStringContainsString('Shop Now', (string) $design->html);
        $this->assertStringContainsString('On Sale', (string) $design->html);
    }
}
