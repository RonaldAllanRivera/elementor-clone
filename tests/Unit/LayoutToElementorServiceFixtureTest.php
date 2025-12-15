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
}
