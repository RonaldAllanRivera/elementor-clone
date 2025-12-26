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

    public function test_import_from_figma_vertical_auto_layout_fill_child_sets_align_self_stretch(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'counterAxisAlignItems' => 'CENTER',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 120],
                            'children' => [
                                [
                                    'id' => 'txt:1',
                                    'type' => 'TEXT',
                                    'characters' => 'Full width text',
                                    'layoutSizingHorizontal' => 'FILL',
                                    'style' => ['fontSize' => 12],
                                    'absoluteBoundingBox' => ['x' => 100, 'y' => 0, 'width' => 200, 'height' => 16],
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

        $child = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($child);
        $this->assertSame('text', $child['type'] ?? null);

        $style = $child['style'] ?? null;
        $this->assertIsArray($style);
        $this->assertSame('STRETCH', $style['alignSelf'] ?? null);
    }

    public function test_import_from_figma_vertical_auto_layout_respects_layout_sizing_vertical_fill_and_hug(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 240],
                            'children' => [
                                [
                                    'id' => 'row:fill',
                                    'type' => 'FRAME',
                                    'name' => 'Fill Row',
                                    'layoutMode' => 'NONE',
                                    'layoutSizingVertical' => 'FILL',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 100],
                                    'children' => [
                                        [
                                            'id' => 'row:fill:text',
                                            'type' => 'TEXT',
                                            'characters' => 'Fill',
                                            'style' => ['fontSize' => 12],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 40, 'height' => 16],
                                        ],
                                    ],
                                ],
                                [
                                    'id' => 'row:hug',
                                    'type' => 'FRAME',
                                    'name' => 'Hug Row',
                                    'layoutMode' => 'NONE',
                                    'layoutSizingVertical' => 'HUG',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 120, 'width' => 800, 'height' => 40],
                                    'children' => [
                                        [
                                            'id' => 'row:hug:text',
                                            'type' => 'TEXT',
                                            'characters' => 'Hug',
                                            'style' => ['fontSize' => 12],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 120, 'width' => 35, 'height' => 16],
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
        $this->assertCount(2, $design->layout_json['children'] ?? []);

        $fill = $design->layout_json['children'][0] ?? null;
        $hug = $design->layout_json['children'][1] ?? null;
        $this->assertIsArray($fill);
        $this->assertIsArray($hug);

        $fillStyle = $fill['style'] ?? null;
        $hugStyle = $hug['style'] ?? null;
        $this->assertIsArray($fillStyle);
        $this->assertIsArray($hugStyle);

        $this->assertSame(1.0, (float) ($fillStyle['flexGrow'] ?? 0));
        $this->assertSame(0.0, (float) ($fillStyle['flexBasis'] ?? 0));

        $this->assertSame(true, $hugStyle['flexBasisAuto'] ?? null);
        $this->assertSame(0.0, (float) ($hugStyle['flexGrow'] ?? 0));
    }

    public function test_import_from_figma_vertical_auto_layout_fixed_height_child_sets_height_px(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 240],
                            'children' => [
                                [
                                    'id' => 'row:fixed',
                                    'type' => 'FRAME',
                                    'name' => 'Fixed Height Row',
                                    'layoutMode' => 'NONE',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 60],
                                    'children' => [
                                        [
                                            'id' => 'row:fixed:text',
                                            'type' => 'TEXT',
                                            'characters' => 'Fixed Height',
                                            'style' => ['fontSize' => 12],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 90, 'height' => 16],
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

        $fixed = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($fixed);
        $this->assertSame('container', $fixed['type'] ?? null);

        $fixedStyle = $fixed['style'] ?? null;
        $this->assertIsArray($fixedStyle);
        $this->assertSame(60.0, (float) ($fixedStyle['heightPx'] ?? 0));

        $this->assertNotNull($design->html);
        $this->assertStringContainsString('height:60px', (string) $design->html);
    }

    public function test_import_from_figma_infers_input_field_widget_with_background_rectangle_child(): void
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
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 44],
                                    'children' => [
                                        [
                                            'id' => 'search:bg',
                                            'type' => 'RECTANGLE',
                                            'name' => 'BG',
                                            'cornerRadius' => 8,
                                            'fills' => [[
                                                'type' => 'SOLID',
                                                'visible' => true,
                                                'color' => ['r' => 1, 'g' => 1, 'b' => 1, 'a' => 1],
                                            ]],
                                            'strokes' => [[
                                                'type' => 'SOLID',
                                                'visible' => true,
                                                'color' => ['r' => 0.8, 'g' => 0.82, 'b' => 0.86, 'a' => 1],
                                            ]],
                                            'strokeWeight' => 1,
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 44],
                                        ],
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

        $style = $design->layout_json['children'][0]['style'] ?? null;
        $this->assertIsArray($style);
        $this->assertArrayHasKey('backgroundColor', $style);
        $this->assertArrayHasKey('border', $style);

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

    public function test_import_from_figma_merges_background_rectangle_for_horizontal_announcement_bar(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 80],
                            'children' => [
                                [
                                    'id' => 'bar:1',
                                    'type' => 'FRAME',
                                    'name' => 'Announcement Bar',
                                    'layoutMode' => 'HORIZONTAL',
                                    'primaryAxisAlignItems' => 'SPACE_BETWEEN',
                                    'counterAxisAlignItems' => 'CENTER',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 32],
                                    'children' => [
                                        [
                                            'id' => 'bar:bg',
                                            'type' => 'RECTANGLE',
                                            'name' => 'BG',
                                            'fills' => [[
                                                'type' => 'SOLID',
                                                'visible' => true,
                                                'color' => ['r' => 0.57, 'g' => 0.14, 'b' => 0.35, 'a' => 1],
                                            ]],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 32],
                                        ],
                                        [
                                            'id' => 'bar:text',
                                            'type' => 'TEXT',
                                            'characters' => 'LIMITED TIME!',
                                            'style' => ['fontSize' => 12],
                                            'absoluteBoundingBox' => ['x' => 280, 'y' => 8, 'width' => 100, 'height' => 16],
                                        ],
                                        [
                                            'id' => 'bar:cta',
                                            'type' => 'FRAME',
                                            'name' => 'Ends in 48 hours',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 650, 'y' => 4, 'width' => 120, 'height' => 24],
                                            'children' => [
                                                [
                                                    'id' => 'bar:cta:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Ends in 48 hours',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 660, 'y' => 8, 'width' => 110, 'height' => 16],
                                                ],
                                            ],
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

        $bar = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($bar);
        $this->assertSame('columns', $bar['type'] ?? null);

        $barStyle = $bar['style'] ?? null;
        $this->assertIsArray($barStyle);
        $this->assertArrayHasKey('backgroundColor', $barStyle);
        $this->assertSame('SPACE_BETWEEN', $barStyle['justify'] ?? null);

        $cols = $bar['columns'] ?? null;
        $this->assertIsArray($cols);
        $this->assertCount(2, $cols);

        $this->assertSame('LIMITED TIME!', $cols[0]['children'][0]['text'] ?? null);
        $this->assertSame('Ends in 48 hours', $cols[1]['children'][0]['children'][0]['text'] ?? null);
    }

    public function test_import_from_figma_space_between_centered_text_makes_left_column_flex_fill_and_centered(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 80],
                            'children' => [
                                [
                                    'id' => 'bar:1',
                                    'type' => 'FRAME',
                                    'name' => 'Announcement Bar',
                                    'layoutMode' => 'HORIZONTAL',
                                    'primaryAxisAlignItems' => 'SPACE_BETWEEN',
                                    'counterAxisAlignItems' => 'CENTER',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 32],
                                    'children' => [
                                        [
                                            'id' => 'bar:bg',
                                            'type' => 'RECTANGLE',
                                            'name' => 'BG',
                                            'fills' => [[
                                                'type' => 'SOLID',
                                                'visible' => true,
                                                'color' => ['r' => 0.57, 'g' => 0.14, 'b' => 0.35, 'a' => 1],
                                            ]],
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 32],
                                        ],
                                        [
                                            'id' => 'bar:text',
                                            'type' => 'TEXT',
                                            'characters' => 'LIMITED TIME!',
                                            'style' => ['fontSize' => 12],
                                            'absoluteBoundingBox' => ['x' => 350, 'y' => 8, 'width' => 100, 'height' => 16],
                                        ],
                                        [
                                            'id' => 'bar:cta',
                                            'type' => 'FRAME',
                                            'name' => 'Ends in 48 hours',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 650, 'y' => 4, 'width' => 120, 'height' => 24],
                                            'children' => [
                                                [
                                                    'id' => 'bar:cta:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Ends in 48 hours',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 660, 'y' => 8, 'width' => 110, 'height' => 16],
                                                ],
                                            ],
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

        $bar = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($bar);
        $this->assertSame('columns', $bar['type'] ?? null);

        $barStyle = $bar['style'] ?? null;
        $this->assertIsArray($barStyle);
        $this->assertSame('MIN', $barStyle['justify'] ?? null);

        $cols = $bar['columns'] ?? null;
        $this->assertIsArray($cols);
        $this->assertCount(2, $cols);

        $leftStyle = $cols[0]['style'] ?? null;
        $this->assertIsArray($leftStyle);
        $this->assertSame(1.0, (float) ($leftStyle['flexGrow'] ?? 0));
        $this->assertSame('center', $leftStyle['textAlign'] ?? null);
        $this->assertArrayNotHasKey('widthPercent', $leftStyle);

        $this->assertSame('LIMITED TIME!', $cols[0]['children'][0]['text'] ?? null);
        $this->assertSame('Ends in 48 hours', $cols[1]['children'][0]['children'][0]['text'] ?? null);
    }

    public function test_import_from_figma_horizontal_auto_layout_fixed_width_children_get_width_percent_sizing_hints(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 200],
                            'children' => [
                                [
                                    'id' => 'row:1',
                                    'type' => 'FRAME',
                                    'name' => 'Row',
                                    'layoutMode' => 'HORIZONTAL',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 40],
                                    'children' => [
                                        [
                                            'id' => 'row:left',
                                            'type' => 'FRAME',
                                            'name' => 'Left',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 40],
                                            'children' => [
                                                [
                                                    'id' => 'row:left:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Left',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 40, 'height' => 16],
                                                ],
                                            ],
                                        ],
                                        [
                                            'id' => 'row:right',
                                            'type' => 'FRAME',
                                            'name' => 'Right',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 200, 'y' => 0, 'width' => 600, 'height' => 40],
                                            'children' => [
                                                [
                                                    'id' => 'row:right:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Right',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 200, 'y' => 0, 'width' => 50, 'height' => 16],
                                                ],
                                            ],
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

        $row = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($row);
        $this->assertSame('columns', $row['type'] ?? null);

        $cols = $row['columns'] ?? null;
        $this->assertIsArray($cols);
        $this->assertCount(2, $cols);

        $leftStyle = $cols[0]['style'] ?? null;
        $rightStyle = $cols[1]['style'] ?? null;
        $this->assertIsArray($leftStyle);
        $this->assertIsArray($rightStyle);

        $this->assertArrayHasKey('widthPercent', $leftStyle);
        $this->assertArrayHasKey('widthPercent', $rightStyle);

        $this->assertEqualsWithDelta(25.0, (float) $leftStyle['widthPercent'], 0.01);
        $this->assertEqualsWithDelta(75.0, (float) $rightStyle['widthPercent'], 0.01);
    }

    public function test_import_from_figma_horizontal_auto_layout_fill_vertical_sets_align_self_stretch(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 200],
                            'children' => [
                                [
                                    'id' => 'row:1',
                                    'type' => 'FRAME',
                                    'name' => 'Row',
                                    'layoutMode' => 'HORIZONTAL',
                                    'counterAxisAlignItems' => 'CENTER',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 120],
                                    'children' => [
                                        [
                                            'id' => 'row:fill',
                                            'type' => 'FRAME',
                                            'name' => 'Fill Height',
                                            'layoutMode' => 'NONE',
                                            'layoutSizingVertical' => 'FILL',
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 20, 'width' => 200, 'height' => 80],
                                            'children' => [
                                                [
                                                    'id' => 'row:fill:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Fill',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 20, 'width' => 40, 'height' => 16],
                                                ],
                                            ],
                                        ],
                                        [
                                            'id' => 'row:fixed',
                                            'type' => 'FRAME',
                                            'name' => 'Fixed',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 200, 'y' => 40, 'width' => 200, 'height' => 40],
                                            'children' => [
                                                [
                                                    'id' => 'row:fixed:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Fixed',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 200, 'y' => 40, 'width' => 50, 'height' => 16],
                                                ],
                                            ],
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

        $row = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($row);
        $this->assertSame('columns', $row['type'] ?? null);

        $cols = $row['columns'] ?? null;
        $this->assertIsArray($cols);
        $this->assertCount(2, $cols);

        $fillStyle = $cols[0]['style'] ?? null;
        $this->assertIsArray($fillStyle);
        $this->assertSame('STRETCH', $fillStyle['alignSelf'] ?? null);
    }

    public function test_import_from_figma_infers_gap_and_padding_when_missing_from_auto_layout(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 244, 'height' => 80],
                            'children' => [
                                [
                                    'id' => 'row:1',
                                    'type' => 'FRAME',
                                    'name' => 'Row',
                                    'layoutMode' => 'HORIZONTAL',
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 244, 'height' => 40],
                                    'children' => [
                                        [
                                            'id' => 'row:left',
                                            'type' => 'FRAME',
                                            'name' => 'Left',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 16, 'y' => 10, 'width' => 100, 'height' => 20],
                                            'children' => [
                                                [
                                                    'id' => 'row:left:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Left',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 16, 'y' => 10, 'width' => 40, 'height' => 16],
                                                ],
                                            ],
                                        ],
                                        [
                                            'id' => 'row:right',
                                            'type' => 'FRAME',
                                            'name' => 'Right',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 128, 'y' => 10, 'width' => 100, 'height' => 20],
                                            'children' => [
                                                [
                                                    'id' => 'row:right:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Right',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 128, 'y' => 10, 'width' => 50, 'height' => 16],
                                                ],
                                            ],
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

        $row = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($row);
        $this->assertSame('columns', $row['type'] ?? null);

        $rowStyle = $row['style'] ?? null;
        $this->assertIsArray($rowStyle);

        $padding = $rowStyle['padding'] ?? null;
        $this->assertIsArray($padding);
        $this->assertSame(16.0, (float) ($padding['left'] ?? 0));
        $this->assertSame(16.0, (float) ($padding['right'] ?? 0));
        $this->assertSame(10.0, (float) ($padding['top'] ?? 0));
        $this->assertSame(10.0, (float) ($padding['bottom'] ?? 0));

        $this->assertSame(12.0, (float) ($rowStyle['gap'] ?? 0));

        $cols = $row['columns'] ?? null;
        $this->assertIsArray($cols);
        $this->assertCount(2, $cols);

        $leftStyle = $cols[0]['style'] ?? null;
        $rightStyle = $cols[1]['style'] ?? null;
        $this->assertIsArray($leftStyle);
        $this->assertIsArray($rightStyle);
        $this->assertEqualsWithDelta(50.0, (float) ($leftStyle['widthPercent'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(50.0, (float) ($rightStyle['widthPercent'] ?? 0), 0.01);
    }

    public function test_import_from_figma_horizontal_auto_layout_wrap_sets_wrap_and_html_emits_flex_wrap(): void
    {
        config()->set('services.figma.token', 'test-token');

        Http::fake([
            'https://api.figma.com/v1/files/*/nodes*' => Http::response([
                'nodes' => [
                    '1:2' => [
                        'document' => [
                            'id' => '1:2',
                            'type' => 'FRAME',
                            'name' => 'Top',
                            'layoutMode' => 'VERTICAL',
                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 320, 'height' => 200],
                            'children' => [
                                [
                                    'id' => 'wrap:row',
                                    'type' => 'FRAME',
                                    'name' => 'Wrap Row',
                                    'layoutMode' => 'HORIZONTAL',
                                    'layoutWrap' => 'WRAP',
                                    'counterAxisAlignContent' => 'CENTER',
                                    'itemSpacing' => 8,
                                    'counterAxisSpacing' => 14,
                                    'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 320, 'height' => 80],
                                    'children' => [
                                        [
                                            'id' => 'chip:1',
                                            'type' => 'FRAME',
                                            'name' => 'Chip 1',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 140, 'height' => 32],
                                            'children' => [
                                                [
                                                    'id' => 'chip:1:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Chip 1',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 8, 'y' => 8, 'width' => 40, 'height' => 16],
                                                ],
                                            ],
                                        ],
                                        [
                                            'id' => 'chip:2',
                                            'type' => 'FRAME',
                                            'name' => 'Chip 2',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 148, 'y' => 0, 'width' => 140, 'height' => 32],
                                            'children' => [
                                                [
                                                    'id' => 'chip:2:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Chip 2',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 156, 'y' => 8, 'width' => 40, 'height' => 16],
                                                ],
                                            ],
                                        ],
                                        [
                                            'id' => 'chip:3',
                                            'type' => 'FRAME',
                                            'name' => 'Chip 3',
                                            'layoutMode' => 'NONE',
                                            'absoluteBoundingBox' => ['x' => 0, 'y' => 40, 'width' => 140, 'height' => 32],
                                            'children' => [
                                                [
                                                    'id' => 'chip:3:text',
                                                    'type' => 'TEXT',
                                                    'characters' => 'Chip 3',
                                                    'style' => ['fontSize' => 12],
                                                    'absoluteBoundingBox' => ['x' => 8, 'y' => 48, 'width' => 40, 'height' => 16],
                                                ],
                                            ],
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

        $row = $design->layout_json['children'][0] ?? null;
        $this->assertIsArray($row);
        $this->assertSame('columns', $row['type'] ?? null);

        $style = $row['style'] ?? null;
        $this->assertIsArray($style);
        $this->assertTrue((bool) ($style['wrap'] ?? false));
        $this->assertArrayNotHasKey('gap', $style);
        $this->assertSame(8.0, (float) ($style['columnGap'] ?? 0));
        $this->assertSame(14.0, (float) ($style['rowGap'] ?? 0));
        $this->assertSame('CENTER', $style['alignContent'] ?? null);

        $this->assertIsString($design->html);
        $this->assertStringContainsString('flex-wrap:wrap', $design->html);
        $this->assertStringContainsString('column-gap:8px', $design->html);
        $this->assertStringContainsString('row-gap:14px', $design->html);
        $this->assertStringContainsString('align-content:center', $design->html);
    }
}
