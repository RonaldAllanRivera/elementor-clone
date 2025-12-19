<?php

namespace Tests\Unit;

use App\Services\LayoutToElementorService;
use PHPUnit\Framework\TestCase;

class LayoutToElementorServiceTest extends TestCase
{
    public function test_export_empty_layout_returns_empty_content(): void
    {
        $service = new LayoutToElementorService();

        $payload = $service->export(null, 'Empty');

        $this->assertSame('Empty', $payload['title']);
        $this->assertSame('page', $payload['type']);
        $this->assertSame('0.4', $payload['version']);
        $this->assertSame([], $payload['content']);
    }

    public function test_export_maps_section_with_heading(): void
    {
        $service = new LayoutToElementorService();

        $layout = [
            'type' => 'section',
            'children' => [
                [
                    'type' => 'heading',
                    'text' => 'Hello World',
                    'level' => 1,
                ],
            ],
        ];

        $payload = $service->export($layout, 'My Design');

        $this->assertSame('My Design', $payload['title']);
        $this->assertIsArray($payload['content']);
        $this->assertCount(1, $payload['content']);

        $section = $payload['content'][0];
        $this->assertSame('section', $section['elType']);
        $this->assertIsString($section['id']);
        $this->assertSame(8, strlen($section['id']));
        $this->assertIsArray($section['elements']);
        $this->assertCount(1, $section['elements']);

        $column = $section['elements'][0];
        $this->assertSame('column', $column['elType']);
        $this->assertIsString($column['id']);
        $this->assertSame(8, strlen($column['id']));
        $this->assertFalse($column['isInner']);
        $this->assertCount(1, $column['elements']);

        $heading = $column['elements'][0];
        $this->assertSame('widget', $heading['elType']);
        $this->assertSame('heading', $heading['widgetType']);
        $this->assertSame('Hello World', $heading['settings']['title']);
        $this->assertSame('h1', $heading['settings']['header_size']);
        $this->assertIsString($heading['id']);
        $this->assertSame(8, strlen($heading['id']));
    }

    public function test_export_maps_plain_string_to_text_editor_widget(): void
    {
        $service = new LayoutToElementorService();

        $payload = $service->export('Hello', 'Text');

        $this->assertCount(1, $payload['content']);
        $section = $payload['content'][0];
        $this->assertSame('section', $section['elType']);
        $this->assertCount(1, $section['elements']);
        $this->assertSame('column', $section['elements'][0]['elType']);
        $this->assertCount(1, $section['elements'][0]['elements']);

        $widget = $section['elements'][0]['elements'][0];
        $this->assertSame('widget', $widget['elType']);
        $this->assertSame('text-editor', $widget['widgetType']);
        $this->assertSame('<p>Hello</p>', $widget['settings']['editor']);
    }

    public function test_export_maps_columns_with_image_and_button(): void
    {
        $service = new LayoutToElementorService();

        $layout = [
            'type' => 'columns',
            'columns' => [
                [
                    ['type' => 'heading', 'text' => 'Col 1', 'level' => 2],
                    ['type' => 'button', 'label' => 'Click', 'href' => 'https://example.com'],
                ],
                [
                    ['type' => 'image', 'src' => 'https://example.com/a.png', 'alt' => 'Alt'],
                    ['type' => 'text', 'text' => 'Body'],
                ],
            ],
        ];

        $payload = $service->export($layout, 'Columns');

        $this->assertCount(1, $payload['content']);
        $section = $payload['content'][0];
        $this->assertSame('section', $section['elType']);
        $this->assertCount(2, $section['elements']);

        $firstColumn = $section['elements'][0];
        $this->assertSame('column', $firstColumn['elType']);
        $this->assertCount(2, $firstColumn['elements']);
        $this->assertSame('heading', $firstColumn['elements'][0]['widgetType']);
        $this->assertSame('button', $firstColumn['elements'][1]['widgetType']);

        $secondColumn = $section['elements'][1];
        $this->assertSame('column', $secondColumn['elType']);
        $this->assertCount(2, $secondColumn['elements']);
        $this->assertSame('image', $secondColumn['elements'][0]['widgetType']);
        $this->assertSame('text-editor', $secondColumn['elements'][1]['widgetType']);
    }

    public function test_export_container_format_maps_columns_to_containers(): void
    {
        $service = new LayoutToElementorService();

        $layout = [
            'type' => 'columns',
            'columns' => [
                [
                    ['type' => 'heading', 'text' => 'Col 1', 'level' => 2],
                ],
                [
                    ['type' => 'text', 'text' => 'Body'],
                ],
            ],
        ];

        $payload = $service->export($layout, 'Container', LayoutToElementorService::FORMAT_CONTAINER);

        $this->assertCount(1, $payload['content']);
        $root = $payload['content'][0];
        $this->assertSame('container', $root['elType']);
        $this->assertCount(2, $root['elements']);
        $this->assertSame('container', $root['elements'][0]['elType']);
        $this->assertSame('container', $root['elements'][1]['elType']);
    }
}
