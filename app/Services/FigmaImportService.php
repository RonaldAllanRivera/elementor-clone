<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FigmaImportService
{
    public function importFromUrl(string $figmaUrl): array
    {
        $token = (string) config('services.figma.token');
        $baseUrl = rtrim((string) config('services.figma.base_url', 'https://api.figma.com/v1'), '/');

        if ($token === '') {
            throw new RuntimeException('FIGMA_TOKEN is not configured.');
        }

        [$fileKey, $nodeId] = $this->parseFigmaUrl($figmaUrl);

        $response = Http::withHeaders([
                'X-Figma-Token' => $token,
            ])
            ->acceptJson()
            ->timeout(20)
            ->get($baseUrl . '/files/' . $fileKey . '/nodes', [
                'ids' => $nodeId,
                'depth' => 10,
            ]);

        if (! $response->ok()) {
            $status = $response->status();
            $data = $response->json();

            $message = '';
            if (is_array($data)) {
                $message = (string) ($data['err'] ?? $data['message'] ?? '');
            }

            if ($message === '') {
                $message = Str::limit((string) $response->body(), 300, '...');
            }

            $suffix = $message !== '' ? (': ' . $message) : '';

            throw new RuntimeException('Failed to fetch Figma node (HTTP ' . $status . ')' . $suffix);
        }

        $payload = $response->json();
        $node = $payload['nodes'][$nodeId]['document'] ?? null;

        if (! is_array($node)) {
            throw new RuntimeException('Unexpected Figma response.');
        }

        $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : '';
        if ($type !== 'FRAME') {
            throw new RuntimeException('Figma node is not a Frame.');
        }

        $children = $this->mapFrameToLayout($node);

        return [
            'type' => 'section',
            'children' => $children,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseFigmaUrl(string $url): array
    {
        $parts = parse_url($url);
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        $query = is_array($parts) ? ($parts['query'] ?? '') : '';

        $fileKey = '';
        if (is_string($path) && $path !== '') {
            if (preg_match('#/(design|file)/([^/]+)/#', $path, $m)) {
                $fileKey = (string) ($m[2] ?? '');
            }
        }

        parse_str(is_string($query) ? $query : '', $q);
        $nodeIdRaw = is_array($q) ? (string) ($q['node-id'] ?? '') : '';

        if ($fileKey === '' || $nodeIdRaw === '') {
            throw new RuntimeException('Invalid Figma URL.');
        }

        $nodeId = str_replace('-', ':', $nodeIdRaw);

        if (! preg_match('/^\d+[:]\d+$/', $nodeId)) {
            throw new RuntimeException('Invalid node-id in Figma URL.');
        }

        return [$fileKey, $nodeId];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapFrameToLayout(array $frame): array
    {
        $layout = $this->mapLayoutNode($frame);

        if ($layout === []) {
            $name = is_string($frame['name'] ?? null) ? trim((string) $frame['name']) : '';
            $name = $name !== '' ? $name : 'Imported frame';
            return [
                ['type' => 'text', 'text' => $name],
            ];
        }

        if (isset($layout['type']) && ($layout['type'] === 'container' || $layout['type'] === 'section')) {
            $children = $layout['children'] ?? [];

            return is_array($children) ? $children : [];
        }

        return [$layout];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLayoutNode(array $node): array
    {
        $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : '';

        if ($type === 'TEXT') {
            $text = is_string($node['characters'] ?? null) ? trim((string) $node['characters']) : '';
            if ($text === '') {
                return [];
            }

            $style = $node['style'] ?? null;
            $fontSize = is_array($style) ? (float) ($style['fontSize'] ?? 0) : 0;

            $layoutStyle = $this->extractTextStyle($node);

            if ($fontSize >= 32) {
                return ['type' => 'heading', 'text' => $text, 'level' => 1, 'style' => $layoutStyle];
            }

            if ($fontSize >= 24) {
                return ['type' => 'heading', 'text' => $text, 'level' => 2, 'style' => $layoutStyle];
            }

            if ($fontSize >= 20) {
                return ['type' => 'heading', 'text' => $text, 'level' => 3, 'style' => $layoutStyle];
            }

            return ['type' => 'text', 'text' => $text, 'style' => $layoutStyle];
        }

        $children = $node['children'] ?? [];
        if (! is_array($children) || $children === []) {
            $image = $this->tryMapImageNode($node);
            if ($image !== []) {
                return $image;
            }

            return [];
        }

        $layoutMode = isset($node['layoutMode']) && is_string($node['layoutMode']) ? $node['layoutMode'] : 'NONE';
        $layoutMode = strtoupper($layoutMode);

        if ($layoutMode === 'HORIZONTAL') {
            return $this->mapHorizontalAutoLayout($node);
        }

        if ($layoutMode === 'VERTICAL') {
            return $this->mapVerticalAutoLayout($node);
        }

        return $this->mapNonAutoLayout($node);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapVerticalAutoLayout(array $node): array
    {
        $children = $this->sortedChildren($node);
        $outChildren = [];
        foreach ($children as $i => $child) {
            $mapped = $this->mapLayoutNode($child);
            if ($mapped !== []) {
                $outChildren[] = $mapped;
            }
        }

        if ($outChildren === []) {
            return [];
        }

        $style = array_merge(
            $this->extractLayoutStyle($node, 'column'),
            $this->extractVisualStyle($node)
        );

        return [
            'type' => 'container',
            'style' => $style,
            'children' => $outChildren,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapHorizontalAutoLayout(array $node): array
    {
        $children = $this->sortedChildren($node);
        $columns = [];
        foreach ($children as $i => $child) {
            $mapped = $this->mapLayoutNode($child);
            if ($mapped === []) {
                continue;
            }

            $columns[] = [
                'type' => 'container',
                'children' => [$mapped],
            ];
        }

        if ($columns === []) {
            return [];
        }

        $style = array_merge(
            $this->extractLayoutStyle($node, 'row'),
            $this->extractVisualStyle($node)
        );

        return [
            'type' => 'columns',
            'style' => $style,
            'columns' => $columns,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNonAutoLayout(array $node): array
    {
        $children = $this->sortedChildren($node);
        if ($children === []) {
            return [];
        }

        $rows = $this->groupChildrenIntoRows($children);
        $outChildren = [];
        foreach ($rows as $rowIndex => $row) {
            if (count($row) === 1) {
                $mapped = $this->mapLayoutNode($row[0]);
                if ($mapped !== []) {
                    $outChildren[] = $mapped;
                }
                continue;
            }

            usort($row, function (array $a, array $b): int {
                return (float) ($this->getAbsBox($a)['x'] ?? 0) <=> (float) ($this->getAbsBox($b)['x'] ?? 0);
            });

            $cols = [];
            foreach ($row as $child) {
                $mapped = $this->mapLayoutNode($child);
                if ($mapped === []) {
                    continue;
                }

                $cols[] = [
                    'type' => 'container',
                    'children' => [$mapped],
                ];
            }

            if ($cols !== []) {
                $outChildren[] = [
                    'type' => 'columns',
                    'columns' => $cols,
                ];
            }
        }

        if ($outChildren === []) {
            return [];
        }

        $style = $this->extractVisualStyle($node);

        return array_filter([
            'type' => 'container',
            'style' => $style !== [] ? $style : null,
            'children' => $outChildren,
        ], fn ($v) => $v !== null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sortedChildren(array $node): array
    {
        $children = $node['children'] ?? [];
        $children = is_array($children) ? $children : [];

        $filtered = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $filtered[] = $child;
            }
        }

        usort($filtered, function (array $a, array $b): int {
            $ab = $this->getAbsBox($a);
            $bb = $this->getAbsBox($b);

            $ay = (float) ($ab['y'] ?? 0);
            $by = (float) ($bb['y'] ?? 0);
            if ($ay === $by) {
                return (float) ($ab['x'] ?? 0) <=> (float) ($bb['x'] ?? 0);
            }

            return $ay <=> $by;
        });

        return $filtered;
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupChildrenIntoRows(array $children): array
    {
        $rows = [];
        foreach ($children as $child) {
            $box = $this->getAbsBox($child);
            $cy = (float) ($box['y'] ?? 0) + ((float) ($box['height'] ?? 0) / 2.0);
            $h = (float) ($box['height'] ?? 0);
            $threshold = max(8.0, $h * 0.35);

            $placed = false;
            foreach ($rows as &$row) {
                $rowCy = (float) ($row['cy'] ?? 0);
                if (abs($rowCy - $cy) <= $threshold) {
                    $row['items'][] = $child;
                    $row['cy'] = ($rowCy + $cy) / 2.0;
                    $placed = true;
                    break;
                }
            }
            unset($row);

            if (! $placed) {
                $rows[] = [
                    'cy' => $cy,
                    'items' => [$child],
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            return (float) ($a['cy'] ?? 0) <=> (float) ($b['cy'] ?? 0);
        });

        $out = [];
        foreach ($rows as $row) {
            $items = $row['items'] ?? [];
            $out[] = is_array($items) ? $items : [];
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private function getAbsBox(array $node): array
    {
        $abs = $node['absoluteBoundingBox'] ?? null;
        if (! is_array($abs)) {
            return ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 0];
        }

        return [
            'x' => (float) ($abs['x'] ?? 0),
            'y' => (float) ($abs['y'] ?? 0),
            'width' => (float) ($abs['width'] ?? 0),
            'height' => (float) ($abs['height'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractLayoutStyle(array $node, string $direction): array
    {
        $style = [
            'direction' => $direction,
        ];

        $itemSpacing = $node['itemSpacing'] ?? null;
        if (is_numeric($itemSpacing)) {
            $style['gap'] = (float) $itemSpacing;
        }

        $pad = [
            'top' => $node['paddingTop'] ?? null,
            'right' => $node['paddingRight'] ?? null,
            'bottom' => $node['paddingBottom'] ?? null,
            'left' => $node['paddingLeft'] ?? null,
        ];

        $hasPad = false;
        foreach ($pad as $v) {
            if (is_numeric($v) && (float) $v !== 0.0) {
                $hasPad = true;
                break;
            }
        }

        if ($hasPad) {
            $style['padding'] = [
                'top' => is_numeric($pad['top']) ? (float) $pad['top'] : 0,
                'right' => is_numeric($pad['right']) ? (float) $pad['right'] : 0,
                'bottom' => is_numeric($pad['bottom']) ? (float) $pad['bottom'] : 0,
                'left' => is_numeric($pad['left']) ? (float) $pad['left'] : 0,
            ];
        }

        $primary = isset($node['primaryAxisAlignItems']) && is_string($node['primaryAxisAlignItems']) ? strtoupper($node['primaryAxisAlignItems']) : '';
        $counter = isset($node['counterAxisAlignItems']) && is_string($node['counterAxisAlignItems']) ? strtoupper($node['counterAxisAlignItems']) : '';

        if ($primary !== '') {
            $style['justify'] = $primary;
        }

        if ($counter !== '') {
            $style['align'] = $counter;
        }

        return $style;
    }

    /**
     * Extract visual styles common to containers/shapes: SOLID fills, borders, radius, drop shadow.
     *
     * @return array<string, mixed>
     */
    private function extractVisualStyle(array $node): array
    {
        $style = [];

        $bg = $this->extractSolidFillCss($node['fills'] ?? null);
        if ($bg !== null) {
            $style['backgroundColor'] = $bg;
        }

        $border = $this->extractBorderCss($node);
        if ($border !== null) {
            $style['border'] = $border;
        }

        $radius = $this->extractBorderRadiusCss($node);
        if ($radius !== null) {
            $style['borderRadius'] = $radius;
        }

        $shadow = $this->extractBoxShadowCss($node['effects'] ?? null);
        if ($shadow !== null) {
            $style['boxShadow'] = $shadow;
        }

        return $style;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractTextStyle(array $node): array
    {
        $out = [];

        $style = $node['style'] ?? null;
        if (is_array($style)) {
            if (is_string($style['fontFamily'] ?? null) && $style['fontFamily'] !== '') {
                $out['fontFamily'] = (string) $style['fontFamily'];
            }

            if (is_numeric($style['fontSize'] ?? null)) {
                $out['fontSize'] = (float) $style['fontSize'];
            }

            if (is_numeric($style['fontWeight'] ?? null)) {
                $out['fontWeight'] = (int) $style['fontWeight'];
            }

            if (is_numeric($style['letterSpacing'] ?? null)) {
                $out['letterSpacing'] = (float) $style['letterSpacing'];
            }

            if (is_numeric($style['lineHeightPx'] ?? null)) {
                $out['lineHeightPx'] = (float) $style['lineHeightPx'];
            }

            if (is_string($style['textAlignHorizontal'] ?? null) && $style['textAlignHorizontal'] !== '') {
                $out['textAlign'] = strtolower((string) $style['textAlignHorizontal']);
            }

            if (is_string($style['textCase'] ?? null) && $style['textCase'] !== '') {
                $case = strtoupper((string) $style['textCase']);
                if ($case === 'UPPER') {
                    $out['textTransform'] = 'uppercase';
                }
                if ($case === 'LOWER') {
                    $out['textTransform'] = 'lowercase';
                }
                if ($case === 'TITLE') {
                    $out['textTransform'] = 'capitalize';
                }
            }
        }

        $color = $this->extractSolidFillCss($node['fills'] ?? null);
        if ($color !== null) {
            $out['color'] = $color;
        }

        return $out;
    }

    private function extractSolidFillCss(mixed $fills): ?string
    {
        if (! is_array($fills)) {
            return null;
        }

        foreach ($fills as $fill) {
            if (! is_array($fill)) {
                continue;
            }

            if (($fill['visible'] ?? true) === false) {
                continue;
            }

            $type = is_string($fill['type'] ?? null) ? strtoupper((string) $fill['type']) : '';
            if ($type !== 'SOLID') {
                continue;
            }

            $color = $fill['color'] ?? null;
            if (! is_array($color)) {
                continue;
            }

            $opacity = is_numeric($fill['opacity'] ?? null) ? (float) $fill['opacity'] : 1.0;

            return $this->figmaColorToCss($color, $opacity);
        }

        return null;
    }

    private function extractBorderCss(array $node): ?string
    {
        $strokes = $node['strokes'] ?? null;
        if (! is_array($strokes)) {
            return null;
        }

        $strokeWeight = $node['strokeWeight'] ?? null;
        $w = is_numeric($strokeWeight) ? (float) $strokeWeight : null;
        if ($w === null || $w <= 0) {
            return null;
        }

        foreach ($strokes as $stroke) {
            if (! is_array($stroke)) {
                continue;
            }

            if (($stroke['visible'] ?? true) === false) {
                continue;
            }

            $type = is_string($stroke['type'] ?? null) ? strtoupper((string) $stroke['type']) : '';
            if ($type !== 'SOLID') {
                continue;
            }

            $color = $stroke['color'] ?? null;
            if (! is_array($color)) {
                continue;
            }

            $opacity = is_numeric($stroke['opacity'] ?? null) ? (float) $stroke['opacity'] : 1.0;
            $cssColor = $this->figmaColorToCss($color, $opacity);

            $w = (int) round($w);

            return $w . 'px solid ' . $cssColor;
        }

        return null;
    }

    private function extractBorderRadiusCss(array $node): ?string
    {
        $r = $node['cornerRadius'] ?? null;
        if (is_numeric($r)) {
            $val = (int) round((float) $r);
            if ($val > 0) {
                return $val . 'px';
            }
        }

        $rr = $node['rectangleCornerRadii'] ?? null;
        if (is_array($rr) && count($rr) === 4) {
            $vals = [];
            foreach ($rr as $v) {
                $vals[] = (int) round(is_numeric($v) ? (float) $v : 0);
            }

            if (max($vals) > 0) {
                return $vals[0] . 'px ' . $vals[1] . 'px ' . $vals[2] . 'px ' . $vals[3] . 'px';
            }
        }

        return null;
    }

    private function extractBoxShadowCss(mixed $effects): ?string
    {
        if (! is_array($effects)) {
            return null;
        }

        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            if (($effect['visible'] ?? true) === false) {
                continue;
            }

            $type = is_string($effect['type'] ?? null) ? strtoupper((string) $effect['type']) : '';
            if ($type !== 'DROP_SHADOW') {
                continue;
            }

            $color = $effect['color'] ?? null;
            if (! is_array($color)) {
                continue;
            }

            $offset = $effect['offset'] ?? null;
            $x = is_array($offset) && is_numeric($offset['x'] ?? null) ? (float) $offset['x'] : 0.0;
            $y = is_array($offset) && is_numeric($offset['y'] ?? null) ? (float) $offset['y'] : 0.0;
            $radius = is_numeric($effect['radius'] ?? null) ? (float) $effect['radius'] : 0.0;
            $spread = is_numeric($effect['spread'] ?? null) ? (float) $effect['spread'] : 0.0;

            $cssColor = $this->figmaColorToCss($color, 1.0);

            return (int) round($x) . 'px ' . (int) round($y) . 'px ' . (int) round($radius) . 'px ' . (int) round($spread) . 'px ' . $cssColor;
        }

        return null;
    }

    private function figmaColorToCss(array $color, float $opacity): string
    {
        $r = is_numeric($color['r'] ?? null) ? (float) $color['r'] : 0.0;
        $g = is_numeric($color['g'] ?? null) ? (float) $color['g'] : 0.0;
        $b = is_numeric($color['b'] ?? null) ? (float) $color['b'] : 0.0;
        $a = is_numeric($color['a'] ?? null) ? (float) $color['a'] : 1.0;

        $alpha = max(0.0, min(1.0, $opacity * $a));
        $ri = (int) round(max(0.0, min(1.0, $r)) * 255);
        $gi = (int) round(max(0.0, min(1.0, $g)) * 255);
        $bi = (int) round(max(0.0, min(1.0, $b)) * 255);

        return 'rgba(' . $ri . ',' . $gi . ',' . $bi . ',' . rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.') . ')';
    }

    /**
     * @return array<string, mixed>
     */
    private function tryMapImageNode(array $node): array
    {
        $type = isset($node['type']) && is_string($node['type']) ? strtoupper($node['type']) : '';
        if (! in_array($type, ['RECTANGLE', 'VECTOR', 'ELLIPSE', 'POLYGON', 'STAR', 'LINE', 'BOOLEAN_OPERATION'], true)) {
            return [];
        }

        $fills = $node['fills'] ?? null;
        if (! is_array($fills)) {
            return [];
        }

        $hasImageFill = false;
        foreach ($fills as $fill) {
            if (is_array($fill) && (string) ($fill['type'] ?? '') === 'IMAGE') {
                $hasImageFill = true;
                break;
            }
        }

        if (! $hasImageFill) {
            return [];
        }

        $name = is_string($node['name'] ?? null) ? trim((string) $node['name']) : '';
        $box = $this->getAbsBox($node);
        $w = (int) round((float) ($box['width'] ?? 0));
        $h = (int) round((float) ($box['height'] ?? 0));
        $w = max(80, min(1200, $w));
        $h = max(80, min(1200, $h));

        $label = $name !== '' ? $name : 'Image';

        $style = [];
        $radius = $this->extractBorderRadiusCss($node);
        if ($radius !== null) {
            $style['borderRadius'] = $radius;
        }

        return [
            'type' => 'image',
            'src' => 'https://placehold.co/' . $w . 'x' . $h . '?text=' . rawurlencode($label),
            'alt' => $label,
            'style' => $style,
        ];
    }
}
