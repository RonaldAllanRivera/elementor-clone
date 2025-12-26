<?php

namespace Tests\Unit;

use App\Services\LayoutToElementorService;
use PHPUnit\Framework\TestCase;

class LayoutToElementorServiceFixtureTest extends TestCase
{
    private function stripIds(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            if ($k === 'id') {
                continue;
            }

            $out[$k] = $this->stripIds($v);
        }

        return $out;
    }

    private function fixturePath(string $relative): string
    {
        return __DIR__ . '/../fixtures/' . $relative;
    }

    public function test_fixture_simple_classic(): void
    {
        $service = new LayoutToElementorService();

        $layout = json_decode((string) file_get_contents($this->fixturePath('layout-simple.json')), true);
        $expected = json_decode((string) file_get_contents($this->fixturePath('expected-elementor-classic-simple.json')), true);

        $actual = $service->export($layout, 'Fixture', LayoutToElementorService::FORMAT_CLASSIC);

        $this->assertSame($this->stripIds($expected), $this->stripIds($actual));
    }

    public function test_fixture_simple_container(): void
    {
        $service = new LayoutToElementorService();

        $layout = json_decode((string) file_get_contents($this->fixturePath('layout-simple.json')), true);
        $expected = json_decode((string) file_get_contents($this->fixturePath('expected-elementor-container-simple.json')), true);

        $actual = $service->export($layout, 'Fixture', LayoutToElementorService::FORMAT_CONTAINER);

        $this->assertSame($this->stripIds($expected), $this->stripIds($actual));
    }

    public function test_classic_export_maps_background_and_padding_settings(): void
    {
        $service = new LayoutToElementorService();

        $layout = [
            'type' => 'section',
            'children' => [
                [
                    'type' => 'columns',
                    'style' => [
                        'backgroundColor' => '#92245A',
                        'padding' => ['top' => 8, 'right' => 12, 'bottom' => 8, 'left' => 12],
                    ],
                    'columns' => [
                        [
                            'type' => 'container',
                            'style' => ['widthPx' => 600],
                            'children' => [
                                ['type' => 'text', 'text' => 'LIMITED TIME!', 'style' => ['textAlign' => 'center']],
                            ],
                        ],
                        [
                            'type' => 'container',
                            'style' => ['widthPx' => 200],
                            'children' => [
                                ['type' => 'button', 'label' => 'Ends in 48 hours', 'href' => '#'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $payload = $service->export($layout, 'Fixture', LayoutToElementorService::FORMAT_CLASSIC);

        $this->assertIsArray($payload);
        $this->assertIsArray($payload['content'] ?? null);
        $this->assertSame('section', $payload['content'][0]['elType'] ?? null);

        $settings = $payload['content'][0]['settings'] ?? null;
        $this->assertIsArray($settings);
        $this->assertSame('classic', $settings['background_background'] ?? null);
        $this->assertSame('#92245A', $settings['background_color'] ?? null);
        $this->assertIsArray($settings['padding'] ?? null);
        $this->assertSame('8', $settings['padding']['top'] ?? null);
        $this->assertSame('12', $settings['padding']['left'] ?? null);

        $col1 = $payload['content'][0]['elements'][0] ?? null;
        $this->assertIsArray($col1);
        $this->assertSame('column', $col1['elType'] ?? null);
        $this->assertIsArray($col1['elements'] ?? null);

        $widget = $col1['elements'][0] ?? null;
        $this->assertIsArray($widget);
        $this->assertSame('widget', $widget['elType'] ?? null);
        $this->assertSame('text-editor', $widget['widgetType'] ?? null);
        $this->assertSame('center', $widget['settings']['align'] ?? null);
    }
}
