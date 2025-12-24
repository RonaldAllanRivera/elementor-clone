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

    public function test_import_from_figma_does_not_set_gap_when_space_between_is_used(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Header Row',
                            'layoutMode' => 'HORIZONTAL',
                            'primaryAxisAlignItems' => 'SPACE_BETWEEN',
                            'itemSpacing' => 24,
                            'paddingLeft' => 16,
                            'paddingRight' => 16,
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 60],
                            'children' => [
                                [
                                    'id' => 'left:1',
                                    'type' => 'TEXT',
                                    'characters' => 'Left',
                                    'style' => ['fontSize' => 20],
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 60, 'height' => 24],
                                ],
                                [
                                    'id' => 'right:1',
                                    'type' => 'TEXT',
                                    'characters' => 'Right',
                                    'style' => ['fontSize' => 20],
                                    'absoluteBoundingBox' => ['x' => 740, 'y' => 0, 'width' => 60, 'height' => 24],
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

        $this
            ->actingAs($user)
            ->post(route('designs.importFromFigma', $design, absolute: false))
            ->assertRedirect(route('designs.show', $design, absolute: false));

        $design->refresh();

        $style = $design->layout_json['children'][0]['style'] ?? null;
        $this->assertIsArray($style);
        $this->assertSame('SPACE_BETWEEN', $style['justify'] ?? null);
        $this->assertArrayNotHasKey('gap', $style);
    }

    public function test_import_from_figma_infers_input_field_widget(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Search Section',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 120],
                            'children' => [
                                [
                                    'id' => 'search:1',
                                    'type' => 'FRAME',
                                    'name' => 'Search Input',
                                    'layoutMode' => 'HORIZONTAL',
                                    'itemSpacing' => 8,
                                    'paddingTop' => 10,
                                    'paddingRight' => 12,
                                    'paddingBottom' => 10,
                                    'paddingLeft' => 12,
                                    'strokes' => [[
                                        'type' => 'SOLID',
                                        'visible' => true,
                                        'color' => ['r' => 0.8, 'g' => 0.82, 'b' => 0.86, 'a' => 1],
                                    ]],
                                    'strokeWeight' => 1,
                                    'cornerRadius' => 8,
                                    'fills' => [[
                                        'type' => 'SOLID',
                                        'visible' => true,
                                        'color' => ['r' => 1, 'g' => 1, 'b' => 1, 'a' => 1],
                                    ]],
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 44],
                                    'children' => [
                                        [
                                            'id' => 'search:icon',
                                            'type' => 'VECTOR',
                                            'name' => 'Icon',
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 20, 'height' => 20],
                                        ],
                                        [
                                            'id' => 'search:placeholder',
                                            'type' => 'TEXT',
                                            'characters' => 'Search products',
                                            'style' => ['fontSize' => 14],
                                            'fills' => [[
                                                'type' => 'SOLID',
                                                'color' => ['r' => 0.4, 'g' => 0.45, 'b' => 0.55, 'a' => 1],
                                            ]],
                                            'absoluteBoundingBox' => ['x' => 28, 'y' => 0, 'width' => 140, 'height' => 20],
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

        $this
            ->actingAs($user)
            ->post(route('designs.importFromFigma', $design, absolute: false))
            ->assertRedirect(route('designs.show', $design, absolute: false));

        $design->refresh();

        $this->assertSame('section', $design->layout_json['type'] ?? null);
        $this->assertSame('input', $design->layout_json['children'][0]['type'] ?? null);
        $this->assertSame('Search products', $design->layout_json['children'][0]['placeholder'] ?? null);

        $this->assertNotNull($design->html);
        $this->assertStringContainsString('placeholder="Search products"', (string) $design->html);
    }

    public function test_import_from_figma_infers_card_pattern_with_background_rectangle(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Cards Section',
                            'layoutMode' => 'VERTICAL',
                            'itemSpacing' => 16,
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 400],
                            'children' => [
                                [
                                    'id' => 'card:1',
                                    'type' => 'FRAME',
                                    'name' => 'Product Card',
                                    'layoutMode' => 'NONE',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 320, 'height' => 200],
                                    'children' => [
                                        [
                                            'id' => 'card:bg',
                                            'type' => 'RECTANGLE',
                                            'name' => 'Card BG',
                                            'cornerRadius' => 12,
                                            'fills' => [[
                                                'type' => 'SOLID',
                                                'visible' => true,
                                                'color' => ['r' => 1, 'g' => 1, 'b' => 1, 'a' => 1],
                                            ]],
                                            'effects' => [[
                                                'type' => 'DROP_SHADOW',
                                                'visible' => true,
                                                'color' => ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0.15],
                                                'offset' => ['x' => 0, 'y' => 6],
                                                'radius' => 18,
                                                'spread' => 0,
                                            ]],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 320, 'height' => 200],
                                        ],
                                        [
                                            'id' => 'card:title',
                                            'type' => 'TEXT',
                                            'characters' => 'Product Name',
                                            'style' => ['fontSize' => 20],
                                            'absoluteBoundingBox' => ['x' => 16, 'y' => 16, 'width' => 200, 'height' => 28],
                                        ],
                                        [
                                            'id' => 'card:price',
                                            'type' => 'TEXT',
                                            'characters' => '$49.00',
                                            'style' => ['fontSize' => 16],
                                            'absoluteBoundingBox' => ['x' => 16, 'y' => 52, 'width' => 80, 'height' => 22],
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

        $this
            ->actingAs($user)
            ->post(route('designs.importFromFigma', $design, absolute: false))
            ->assertRedirect(route('designs.show', $design, absolute: false));

        $design->refresh();

        $this->assertSame('section', $design->layout_json['type'] ?? null);
        $this->assertSame('container', $design->layout_json['children'][0]['type'] ?? null);

        $style = $design->layout_json['children'][0]['style'] ?? null;
        $this->assertIsArray($style);
        $this->assertArrayHasKey('backgroundColor', $style);
        $this->assertArrayHasKey('borderRadius', $style);
        $this->assertArrayHasKey('boxShadow', $style);

        $children = $design->layout_json['children'][0]['children'] ?? null;
        $this->assertIsArray($children);
        $this->assertCount(2, $children);
        $this->assertSame('heading', $children[0]['type'] ?? null);
        $this->assertSame('text', $children[1]['type'] ?? null);

        $this->assertNotNull($design->html);
        $this->assertStringContainsString('Product Name', (string) $design->html);
        $this->assertStringContainsString('$49.00', (string) $design->html);
    }
}
