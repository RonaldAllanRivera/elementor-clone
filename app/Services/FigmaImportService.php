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

            $fontSize = $this->extractTextFontSize($node);

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

        if ($this->isCardLike($node)) {
            return $this->mapCardLike($node);
        }

        if ($this->isInputLike($node)) {
            return $this->mapInputLike($node);
        }

        if ($this->isButtonLike($node)) {
            return $this->mapButtonLike($node);
        }

        if ($this->isNavLike($node)) {
            return $this->mapNavLike($node);
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

    private function isCardLike(array $node): bool
    {
        $type = isset($node['type']) && is_string($node['type']) ? strtoupper((string) $node['type']) : '';
        if (! in_array($type, ['FRAME', 'COMPONENT', 'INSTANCE', 'GROUP'], true)) {
            return false;
        }

        $layoutMode = isset($node['layoutMode']) && is_string($node['layoutMode']) ? strtoupper((string) $node['layoutMode']) : 'NONE';
        if ($layoutMode === 'HORIZONTAL') {
            return false;
        }

        $children = $this->sortedChildren($node);
        if (count($children) < 2) {
            return false;
        }

        $bgIndex = $this->findBackgroundLikeChildIndex($node, $children);
        if ($bgIndex === null) {
            return false;
        }

        $contentCount = 0;
        foreach ($children as $i => $child) {
            if ($i === $bgIndex) {
                continue;
            }

            $mapped = $this->mapLayoutNode($child);
            if ($mapped !== []) {
                $contentCount++;
            }
        }

        return $contentCount >= 2;
    }

    private function mapCardLike(array $node): array
    {
        $children = $this->sortedChildren($node);
        $bgIndex = $this->findBackgroundLikeChildIndex($node, $children);
        if ($bgIndex === null) {
            return [];
        }

        $bg = $children[$bgIndex];
        if (! is_array($bg)) {
            return [];
        }

        $outChildren = [];
        foreach ($children as $i => $child) {
            if ($i === $bgIndex) {
                continue;
            }

            $mapped = $this->mapLayoutNode($child);
            if ($mapped !== []) {
                $outChildren[] = $mapped;
            }
        }

        if ($outChildren === []) {
            return [];
        }

        $style = array_merge(
            $this->extractVisualStyle($bg),
            $this->extractVisualStyle($node)
        );

        return [
            'type' => 'container',
            'style' => $style,
            'children' => $outChildren,
        ];
    }

    private function extractVerticalChildStyle(array $node): array
    {
        $style = [];

        $layoutAlign = isset($node['layoutAlign']) && is_string($node['layoutAlign']) ? strtoupper((string) $node['layoutAlign']) : '';
        if ($layoutAlign !== '') {
            $style['alignSelf'] = $layoutAlign;
        } else {
            $sizing = isset($node['layoutSizingHorizontal']) && is_string($node['layoutSizingHorizontal'])
                ? strtoupper((string) $node['layoutSizingHorizontal'])
                : '';
            if ($sizing === 'FILL') {
                $style['alignSelf'] = 'STRETCH';
            }
        }

        $grow = $node['layoutGrow'] ?? null;
        $hasGrow = is_numeric($grow) && (float) $grow > 0;
        if ($hasGrow) {
            $style['flexGrow'] = (float) $grow;
            $style['flexShrink'] = 1;
            $style['flexBasis'] = 0;
        } else {
            $sizingV = isset($node['layoutSizingVertical']) && is_string($node['layoutSizingVertical'])
                ? strtoupper((string) $node['layoutSizingVertical'])
                : '';
            if ($sizingV === 'FILL') {
                $style['flexGrow'] = 1.0;
                $style['flexShrink'] = 1;
                $style['flexBasis'] = 0;
            }
            if ($sizingV === 'HUG') {
                $style['flexGrow'] = 0;
                $style['flexShrink'] = 1;
                $style['flexBasisAuto'] = true;
            }
        }

        return $style;
    }

    private function findBackgroundLikeChildIndex(array $parent, array $children): ?int
    {
        $pb = $this->getAbsBox($parent);
        $px = (float) ($pb['x'] ?? 0);
        $py = (float) ($pb['y'] ?? 0);
        $pw = (float) ($pb['width'] ?? 0);
        $ph = (float) ($pb['height'] ?? 0);
        if ($pw <= 0 || $ph <= 0) {
            return null;
        }

        foreach ($children as $i => $child) {
            if (! is_array($child)) {
                continue;
            }

            $ct = isset($child['type']) && is_string($child['type']) ? strtoupper((string) $child['type']) : '';
            if (! in_array($ct, ['RECTANGLE', 'VECTOR'], true)) {
                continue;
            }

            $visual = $this->extractVisualStyle($child);
            $hasVisual = ($visual['backgroundColor'] ?? null) !== null
                || ($visual['border'] ?? null) !== null
                || ($visual['borderRadius'] ?? null) !== null
                || ($visual['boxShadow'] ?? null) !== null;
            if (! $hasVisual) {
                continue;
            }

            $cb = $this->getAbsBox($child);
            $cx = (float) ($cb['x'] ?? 0);
            $cy = (float) ($cb['y'] ?? 0);
            $cw = (float) ($cb['width'] ?? 0);
            $ch = (float) ($cb['height'] ?? 0);

            if ($cw <= 0 || $ch <= 0) {
                continue;
            }

            $sizeOk = ($cw / $pw) >= 0.92 && ($ch / $ph) >= 0.92;
            if (! $sizeOk) {
                continue;
            }

            $posOk = abs($cx - $px) <= 8.0 && abs($cy - $py) <= 8.0;
            if (! $posOk) {
                continue;
            }

            return $i;
        }

        return null;
    }

    private function isButtonLike(array $node): bool
    {
        $type = isset($node['type']) && is_string($node['type']) ? strtoupper((string) $node['type']) : '';
        if (! in_array($type, ['FRAME', 'COMPONENT', 'INSTANCE', 'GROUP'], true)) {
            return false;
        }

        $children = $this->sortedChildren($node);
        if ($children === []) {
            return false;
        }

        $visual = $this->extractVisualStyle($node);
        $bgIndex = $this->findBackgroundLikeChildIndex($node, $children);
        if ($bgIndex !== null) {
            $bg = $children[$bgIndex] ?? null;
            if (is_array($bg)) {
                $visual = array_merge($visual, $this->extractVisualStyle($bg));
            }
            array_splice($children, $bgIndex, 1);
        }

        $textChild = null;
        $iconCount = 0;
        foreach ($children as $child) {
            $ct = isset($child['type']) && is_string($child['type']) ? strtoupper((string) $child['type']) : '';
            if ($ct === 'TEXT') {
                $textChild = $child;
                continue;
            }

            if (in_array($ct, ['VECTOR', 'BOOLEAN_OPERATION', 'STAR', 'ELLIPSE', 'LINE', 'POLYGON'], true)) {
                $iconCount++;
                continue;
            }

            return false;
        }

        if (! is_array($textChild)) {
            return false;
        }

        if ($iconCount > 1) {
            return false;
        }

        $text = is_string($textChild['characters'] ?? null) ? trim((string) $textChild['characters']) : '';
        if ($text === '') {
            return false;
        }

        $hasVisual = ($visual['backgroundColor'] ?? null) !== null
            || ($visual['border'] ?? null) !== null
            || ($visual['borderRadius'] ?? null) !== null
            || ($visual['boxShadow'] ?? null) !== null;

        $hasPadding = is_numeric($node['paddingTop'] ?? null)
            || is_numeric($node['paddingRight'] ?? null)
            || is_numeric($node['paddingBottom'] ?? null)
            || is_numeric($node['paddingLeft'] ?? null);

        return $hasVisual || $hasPadding;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapButtonLike(array $node): array
    {
        $children = $this->sortedChildren($node);

        $visual = $this->extractVisualStyle($node);
        $bgIndex = $this->findBackgroundLikeChildIndex($node, $children);
        if ($bgIndex !== null) {
            $bg = $children[$bgIndex] ?? null;
            if (is_array($bg)) {
                $visual = array_merge($visual, $this->extractVisualStyle($bg));
            }
            array_splice($children, $bgIndex, 1);
        }

        $textChild = null;
        foreach ($children as $child) {
            $ct = isset($child['type']) && is_string($child['type']) ? strtoupper((string) $child['type']) : '';
            if ($ct === 'TEXT') {
                $textChild = $child;
                break;
            }
        }

        if (! is_array($textChild)) {
            return [];
        }

        $label = is_string($textChild['characters'] ?? null) ? trim((string) $textChild['characters']) : '';
        if ($label === '') {
            return [];
        }

        $layoutMode = isset($node['layoutMode']) && is_string($node['layoutMode']) ? strtoupper((string) $node['layoutMode']) : 'NONE';
        $direction = $layoutMode === 'VERTICAL' ? 'column' : 'row';

        $style = array_merge(
            $this->extractLayoutStyle($node, $direction),
            $visual,
            $this->extractTextStyle($textChild)
        );

        return [
            'type' => 'button',
            'label' => $label,
            'href' => '#',
            'style' => $style,
        ];
    }

    private function isNavLike(array $node): bool
    {
        $layoutMode = isset($node['layoutMode']) && is_string($node['layoutMode']) ? strtoupper((string) $node['layoutMode']) : 'NONE';
        if ($layoutMode !== 'HORIZONTAL') {
            return false;
        }

        $children = $this->sortedChildren($node);
        if (count($children) < 2) {
            return false;
        }

        foreach ($children as $child) {
            $ct = isset($child['type']) && is_string($child['type']) ? strtoupper((string) $child['type']) : '';
            if ($ct !== 'TEXT') {
                return false;
            }
            $text = is_string($child['characters'] ?? null) ? trim((string) $child['characters']) : '';
            if ($text === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNavLike(array $node): array
    {
        $children = $this->sortedChildren($node);
        $items = [];
        foreach ($children as $child) {
            $label = is_string($child['characters'] ?? null) ? trim((string) $child['characters']) : '';
            if ($label === '') {
                continue;
            }

            $items[] = [
                'label' => $label,
                'href' => '#',
                'style' => $this->extractTextStyle($child),
            ];
        }

        if ($items === []) {
            return [];
        }

        $style = array_merge(
            $this->extractLayoutStyle($node, 'row'),
            $this->extractVisualStyle($node)
        );

        return [
            'type' => 'nav',
            'style' => $style,
            'items' => $items,
        ];
    }

    private function mergeBackgroundLikeChildVisualStyle(array $node, array &$children): array
    {
        $nodeVisual = $this->extractVisualStyle($node);
        $hasNodeVisual = ($nodeVisual['backgroundColor'] ?? null) !== null
            || ($nodeVisual['border'] ?? null) !== null
            || ($nodeVisual['borderRadius'] ?? null) !== null
            || ($nodeVisual['boxShadow'] ?? null) !== null;
        if ($hasNodeVisual) {
            return $nodeVisual;
        }

        $bgIndex = $this->findBackgroundLikeChildIndex($node, $children);
        if ($bgIndex === null) {
            return $nodeVisual;
        }

        $bg = $children[$bgIndex] ?? null;
        if (! is_array($bg)) {
            return $nodeVisual;
        }

        $bgVisual = $this->extractVisualStyle($bg);
        $hasBgVisual = ($bgVisual['backgroundColor'] ?? null) !== null
            || ($bgVisual['border'] ?? null) !== null
            || ($bgVisual['borderRadius'] ?? null) !== null
            || ($bgVisual['boxShadow'] ?? null) !== null;
        if (! $hasBgVisual) {
            return $nodeVisual;
        }

        array_splice($children, $bgIndex, 1);

        return array_merge($nodeVisual, $bgVisual);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapVerticalAutoLayout(array $node): array
    {
        $children = $this->sortedChildren($node);
        $visualStyle = $this->mergeBackgroundLikeChildVisualStyle($node, $children);
        $outChildren = [];
        foreach ($children as $i => $child) {
            $childStyle = $this->extractVerticalChildStyle($child);
            $mapped = $this->mapLayoutNode($child);
            if ($mapped !== []) {
                if ($childStyle !== []) {
                    $existingStyle = $mapped['style'] ?? null;
                    if (is_array($existingStyle)) {
                        $mapped['style'] = array_merge($childStyle, $existingStyle);
                    } else {
                        $mapped['style'] = $childStyle;
                    }
                }
                $outChildren[] = $mapped;
            }
        }

        if ($outChildren === []) {
            return [];
        }

        $style = array_merge(
            $this->extractLayoutStyle($node, 'column'),
            $visualStyle
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
        $parentBox = $this->getAbsBox($node);
        $children = $this->sortedChildren($node);
        $visualStyle = $this->mergeBackgroundLikeChildVisualStyle($node, $children);
        $layoutStyle = $this->extractLayoutStyle($node, 'row');
        $columns = [];
        $mappedChildPairs = [];
        foreach ($children as $i => $child) {
            $mapped = $this->mapLayoutNode($child);
            if ($mapped === []) {
                continue;
            }

            $colStyle = $this->extractSizingStyle($child, $node, $parentBox, count($children), $layoutStyle);

            $col = [
                'type' => 'container',
                'style' => $colStyle !== [] ? $colStyle : null,
                'children' => [$mapped],
            ];

            $columns[] = $col;
            $mappedChildPairs[] = ['child' => $child, 'colIndex' => count($columns) - 1];
        }

        if ($columns === []) {
            return [];
        }

        $justify = is_string($layoutStyle['justify'] ?? null) ? strtoupper((string) $layoutStyle['justify']) : '';
        if ($justify === 'SPACE_BETWEEN' && count($columns) === 2 && count($mappedChildPairs) === 2) {
            $leftMapped = $columns[$mappedChildPairs[0]['colIndex']]['children'][0] ?? null;
            $leftType = is_array($leftMapped) && is_string($leftMapped['type'] ?? null) ? (string) $leftMapped['type'] : '';

            $leftChild = $mappedChildPairs[0]['child'];
            $leftBox = $this->getAbsBox($leftChild);
            $leftCenterX = (float) ($leftBox['x'] ?? 0) + ((float) ($leftBox['width'] ?? 0) / 2.0);
            $parentCenterX = (float) ($parentBox['x'] ?? 0) + ((float) ($parentBox['width'] ?? 0) / 2.0);

            if (($leftType === 'text' || $leftType === 'heading') && abs($leftCenterX - $parentCenterX) <= 48.0) {
                $layoutStyle['justify'] = 'MIN';

                $leftColIndex = (int) $mappedChildPairs[0]['colIndex'];
                $leftColStyle = $columns[$leftColIndex]['style'] ?? null;
                $leftColStyle = is_array($leftColStyle) ? $leftColStyle : [];
                $leftColStyle['flexGrow'] = 1.0;
                $leftColStyle['flexShrink'] = 1;
                $leftColStyle['flexBasis'] = 0;
                unset($leftColStyle['widthPx'], $leftColStyle['widthPercent']);
                if (! isset($leftColStyle['textAlign'])) {
                    $leftColStyle['textAlign'] = 'center';
                }

                $columns[$leftColIndex]['style'] = $leftColStyle;
            }
        }

        $style = array_merge(
            $layoutStyle,
            $visualStyle
        );

        return [
            'type' => 'columns',
            'style' => $style,
            'columns' => array_map(function (array $col): array {
                return array_filter($col, fn ($v) => $v !== null);
            }, $columns),
        ];
    }

    /**
     * Extract sizing hints for a child inside a flex row.
     *
     * @return array<string, mixed>
     */
    private function extractSizingStyle(array $node, array $parent, array $parentBox, int $childCount, array $parentLayoutStyle): array
    {
        $style = [];

        $box = $this->getAbsBox($node);
        $parentW = (float) ($parentBox['width'] ?? 0);

        $padL = is_numeric($parent['paddingLeft'] ?? null) ? (float) $parent['paddingLeft'] : 0.0;
        $padR = is_numeric($parent['paddingRight'] ?? null) ? (float) $parent['paddingRight'] : 0.0;
        $gap = is_numeric($parentLayoutStyle['gap'] ?? null) ? (float) $parentLayoutStyle['gap'] : 0.0;

        $availableW = $parentW;
        if ($availableW > 0) {
            $availableW = max(0.0, $availableW - $padL - $padR);
            if ($gap > 0 && $childCount > 1) {
                $availableW = max(0.0, $availableW - ($gap * ($childCount - 1)));
            }
        }

        $sizing = isset($node['layoutSizingHorizontal']) && is_string($node['layoutSizingHorizontal'])
            ? strtoupper((string) $node['layoutSizingHorizontal'])
            : '';

        $grow = $node['layoutGrow'] ?? null;
        $isFill = $sizing === 'FILL' || (is_numeric($grow) && (float) $grow > 0);
        $isHug = $sizing === 'HUG';

        if ($isFill) {
            $style['flexGrow'] = is_numeric($grow) && (float) $grow > 0 ? (float) $grow : 1.0;
            $style['flexShrink'] = 1;
            $style['flexBasis'] = 0;
        } elseif ($isHug) {
            $style['flexGrow'] = 0;
            $style['flexShrink'] = 1;
            $style['flexBasisAuto'] = true;
        } else {
            $w = (float) ($box['width'] ?? 0);
            if ($w > 0) {
                $style['flexGrow'] = 0;
                $style['flexShrink'] = 0;
                $style['widthPx'] = $w;
                if ($availableW > 0) {
                    $pct = ($w / $availableW) * 100.0;
                    if ($pct > 0.0 && $pct <= 100.0) {
                        $style['widthPercent'] = $pct;
                    }
                }
            } elseif ($availableW > 0) {
                $style['flexGrow'] = 1;
                $style['flexBasis'] = 0;
            }
        }

        $h = (float) ($box['height'] ?? 0);
        if ($h > 0) {
            $style['minHeightPx'] = $h;
        }

        $layoutAlign = isset($node['layoutAlign']) && is_string($node['layoutAlign']) ? strtoupper((string) $node['layoutAlign']) : '';
        if ($layoutAlign !== '') {
            $style['alignSelf'] = $layoutAlign;
        } else {
            $sizingV = isset($node['layoutSizingVertical']) && is_string($node['layoutSizingVertical'])
                ? strtoupper((string) $node['layoutSizingVertical'])
                : '';
            if ($sizingV === 'FILL') {
                $style['alignSelf'] = 'STRETCH';
            }
        }

        return $style;
    }

    private function isInputLike(array $node): bool
    {
        $type = isset($node['type']) && is_string($node['type']) ? strtoupper((string) $node['type']) : '';
        if (! in_array($type, ['FRAME', 'COMPONENT', 'INSTANCE', 'GROUP'], true)) {
            return false;
        }

        $layoutMode = isset($node['layoutMode']) && is_string($node['layoutMode']) ? strtoupper((string) $node['layoutMode']) : 'NONE';
        if (! in_array($layoutMode, ['HORIZONTAL', 'VERTICAL'], true)) {
            return false;
        }

        $children = $this->sortedChildren($node);
        if ($children === []) {
            return false;
        }

        $visual = $this->extractVisualStyle($node);
        $bgIndex = $this->findBackgroundLikeChildIndex($node, $children);
        if ($bgIndex !== null) {
            $bg = $children[$bgIndex] ?? null;
            if (is_array($bg)) {
                $visual = array_merge($visual, $this->extractVisualStyle($bg));
            }
            array_splice($children, $bgIndex, 1);
        }

        if ($children === [] || count($children) > 4) {
            return false;
        }

        $textChild = null;
        $iconCount = 0;
        foreach ($children as $child) {
            $ct = isset($child['type']) && is_string($child['type']) ? strtoupper((string) $child['type']) : '';

            if ($ct === 'TEXT') {
                if ($textChild !== null) {
                    return false;
                }
                $textChild = $child;
                continue;
            }

            if (in_array($ct, ['VECTOR', 'BOOLEAN_OPERATION', 'STAR', 'ELLIPSE', 'LINE', 'POLYGON'], true)) {
                $iconCount++;
                continue;
            }

            return false;
        }

        if (! is_array($textChild)) {
            return false;
        }

        $placeholder = is_string($textChild['characters'] ?? null) ? trim((string) $textChild['characters']) : '';
        if ($placeholder === '') {
            return false;
        }

        $name = is_string($node['name'] ?? null) ? strtolower(trim((string) $node['name'])) : '';
        $placeholderLower = strtolower($placeholder);
        $looksLikeInput = str_contains($name, 'search')
            || str_contains($name, 'input')
            || str_contains($name, 'field')
            || str_contains($placeholderLower, 'search')
            || str_contains($placeholderLower, 'enter');
        if (! $looksLikeInput && $iconCount === 0) {
            return false;
        }

        $hasVisual = ($visual['backgroundColor'] ?? null) !== null
            || ($visual['border'] ?? null) !== null
            || ($visual['borderRadius'] ?? null) !== null
            || ($visual['boxShadow'] ?? null) !== null;

        $hasPadding = is_numeric($node['paddingTop'] ?? null)
            || is_numeric($node['paddingRight'] ?? null)
            || is_numeric($node['paddingBottom'] ?? null)
            || is_numeric($node['paddingLeft'] ?? null);

        return $hasVisual || $hasPadding;
    }

    private function mapInputLike(array $node): array
    {
        $children = $this->sortedChildren($node);

        $visual = $this->extractVisualStyle($node);
        $bgIndex = $this->findBackgroundLikeChildIndex($node, $children);
        if ($bgIndex !== null) {
            $bg = $children[$bgIndex] ?? null;
            if (is_array($bg)) {
                $visual = array_merge($visual, $this->extractVisualStyle($bg));
            }
            array_splice($children, $bgIndex, 1);
        }

        $textChild = null;
        foreach ($children as $child) {
            $ct = isset($child['type']) && is_string($child['type']) ? strtoupper((string) $child['type']) : '';
            if ($ct === 'TEXT') {
                $textChild = $child;
                break;
            }
        }

        if (! is_array($textChild)) {
            return [];
        }

        $placeholder = is_string($textChild['characters'] ?? null) ? trim((string) $textChild['characters']) : '';
        if ($placeholder === '') {
            return [];
        }

        $layoutMode = isset($node['layoutMode']) && is_string($node['layoutMode']) ? strtoupper((string) $node['layoutMode']) : 'NONE';
        $direction = $layoutMode === 'VERTICAL' ? 'column' : 'row';

        $style = array_merge(
            $this->extractLayoutStyle($node, $direction),
            $visual,
            $this->extractTextStyle($textChild)
        );

        return [
            'type' => 'input',
            'placeholder' => $placeholder,
            'style' => $style,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNonAutoLayout(array $node): array
    {
        $children = $this->sortedChildren($node);
        $style = $this->mergeBackgroundLikeChildVisualStyle($node, $children);
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

        $layoutMode = isset($node['layoutMode']) && is_string($node['layoutMode']) ? strtoupper((string) $node['layoutMode']) : 'NONE';

        $filtered = [];
        foreach ($children as $child) {
            if (is_array($child)) {
                $filtered[] = $child;
            }
        }

        usort($filtered, function (array $a, array $b) use ($layoutMode): int {
            $ab = $this->getAbsBox($a);
            $bb = $this->getAbsBox($b);

            $ax = (float) ($ab['x'] ?? 0);
            $ay = (float) ($ab['y'] ?? 0);
            $bx = (float) ($bb['x'] ?? 0);
            $by = (float) ($bb['y'] ?? 0);

            if ($layoutMode === 'HORIZONTAL') {
                if ($ax === $bx) {
                    return $ay <=> $by;
                }

                return $ax <=> $bx;
            }

            if ($ay === $by) {
                return $ax <=> $bx;
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

    private function extractTextFontSize(array $node): float
    {
        $style = $node['style'] ?? null;
        if (is_array($style) && is_numeric($style['fontSize'] ?? null)) {
            $size = (float) $style['fontSize'];
            if ($size > 0) {
                return $size;
            }
        }

        $overrideTable = $node['styleOverrideTable'] ?? null;
        if (! is_array($overrideTable) || $overrideTable === []) {
            return 0.0;
        }

        foreach ($overrideTable as $override) {
            if (! is_array($override)) {
                continue;
            }
            if (is_numeric($override['fontSize'] ?? null)) {
                $size = (float) $override['fontSize'];
                if ($size > 0) {
                    return $size;
                }
            }
        }

        return 0.0;
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

        $primary = isset($node['primaryAxisAlignItems']) && is_string($node['primaryAxisAlignItems']) ? strtoupper($node['primaryAxisAlignItems']) : '';
        $counter = isset($node['counterAxisAlignItems']) && is_string($node['counterAxisAlignItems']) ? strtoupper($node['counterAxisAlignItems']) : '';

        $itemSpacing = $node['itemSpacing'] ?? null;
        if (is_numeric($itemSpacing) && ! in_array($primary, ['SPACE_BETWEEN', 'SPACE_AROUND', 'SPACE_EVENLY'], true)) {
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

        if (! isset($out['fontSize'])) {
            $fontSize = $this->extractTextFontSize($node);
            if ($fontSize > 0) {
                $out['fontSize'] = $fontSize;
            }
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
