<?php

namespace Tests\Feature;

use App\Models\Design;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DesignElementorExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_writes_classic_export_to_storage(): void
    {
        Storage::fake('local');

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

        $this->artisan('design:export-elementor', [
            'designId' => $design->id,
            '--format' => 'classic',
        ])->assertExitCode(0);

        $expectedPath = 'elementor-exports/' . (Str::slug($design->name) ?: ('design-' . $design->id)) . '-elementor.json';

        Storage::disk('local')->assertExists($expectedPath);

        $payload = json_decode((string) Storage::disk('local')->get($expectedPath), true);

        $this->assertIsArray($payload);
        $this->assertSame('My Design', $payload['title']);
        $this->assertSame('page', $payload['type']);
        $this->assertSame('0.4', $payload['version']);
    }

    public function test_command_writes_container_export_to_storage(): void
    {
        Storage::fake('local');

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

        $this->artisan('design:export-elementor', [
            'designId' => $design->id,
            '--format' => 'container',
        ])->assertExitCode(0);

        $expectedPath = 'elementor-exports/' . (Str::slug($design->name) ?: ('design-' . $design->id)) . '-elementor-container.json';

        Storage::disk('local')->assertExists($expectedPath);

        $payload = json_decode((string) Storage::disk('local')->get($expectedPath), true);

        $this->assertIsArray($payload);
        $this->assertSame('My Design', $payload['title']);
        $this->assertSame('container', $payload['content'][0]['elType']);
    }

    public function test_command_writes_classic_simple_export_to_storage(): void
    {
        Storage::fake('local');

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

        $this->artisan('design:export-elementor', [
            'designId' => $design->id,
            '--format' => 'classic_simple',
        ])->assertExitCode(0);

        $expectedPath = 'elementor-exports/' . (Str::slug($design->name) ?: ('design-' . $design->id)) . '-elementor-simple.json';

        Storage::disk('local')->assertExists($expectedPath);

        $payload = json_decode((string) Storage::disk('local')->get($expectedPath), true);

        $this->assertIsArray($payload);
        $this->assertCount(2, $payload['content']);
    }
}
