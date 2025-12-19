<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_diagnostics_requires_authentication(): void
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
            'layout_json' => ['type' => 'section', 'children' => []],
            'html' => null,
        ]);

        $response = $this->get(route('designs.diagnostics', $design, absolute: false));

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_design_diagnostics_is_forbidden_for_non_owner(): void
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
            ->get(route('designs.diagnostics', $design, absolute: false));

        $response->assertForbidden();
    }

    public function test_design_diagnostics_renders_for_owner(): void
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
                    ['type' => 'nav', 'items' => [['label' => 'Shop', 'href' => '#']]],
                    ['type' => 'button', 'label' => 'Buy', 'href' => '#'],
                ],
            ],
            'html' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('designs.diagnostics', $design, absolute: false));

        $response->assertOk();
        $response->assertSee('Design Diagnostics');
        $response->assertSee('Node types');
        $response->assertSee('nav');
        $response->assertSee('button');
    }
}
