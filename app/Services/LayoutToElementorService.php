<?php

namespace App\Services;

class LayoutToElementorService
{
    public const FORMAT_CLASSIC = 'classic';
    public const FORMAT_CLASSIC_SIMPLE = 'classic_simple';
    public const FORMAT_CONTAINER = 'container';

    public function export(mixed $layout, string $title = 'Template Title', string $format = self::FORMAT_CLASSIC): array
    {
        $format = $this->normalizeFormat($format);

        return [
            'title' => $title,
            'type' => 'page',
            'version' => '0.4',
            'page_settings' => [],
            'content' => $this->normalizeContent($layout, $format),
        ];
    }

    private function classicSectionFromChildren(mixed $children, string $path, string $format, int $depth): array
    {
        $elements = $this->mapNode(is_array($children) ? $children : [], $path . '.column.0.elements', $format, $depth + 2);

        if (! $this->isList($elements)) {
            $elements = $elements === [] ? [] : [$elements];
        }

        return [
            'id' => $this->makeId($path),
            'elType' => 'section',
            'isInner' => $depth > 0,
            'settings' => [],
            'elements' => [
                [
                    'id' => $this->makeId($path . '.column.0'),
                    'elType' => 'column',
                    'isInner' => false,
                    'settings' => [],
                    'elements' => $elements,
                ],
            ],
        ];
    }

    private function normalizeClassicRoot(array $elements, string $path): array
    {
        if ($elements === []) {
            return [];
        }

        if (! $this->isList($elements)) {
            $elements = [$elements];
        }

        $out = [];
        foreach ($elements as $i => $element) {
            if (! is_array($element)) {
                continue;
            }

            if (($element['elType'] ?? null) === 'section') {
                $out[] = $element;
                continue;
            }

            $out[] = [
                'id' => $this->makeId($path . '.wrap.section.' . $i),
                'elType' => 'section',
                'isInner' => false,
                'settings' => [],
                'elements' => [
                    [
                        'id' => $this->makeId($path . '.wrap.section.' . $i . '.column.0'),
                        'elType' => 'column',
                        'isInner' => false,
                        'settings' => [],
                        'elements' => [$element],
                    ],
                ],
            ];
        }

        return $out;
    }

    private function normalizeContainerRoot(array $elements, string $path): array
    {
        if ($elements === []) {
            return [];
        }

        if (! $this->isList($elements)) {
            $elements = [$elements];
        }

        $out = [];
        foreach ($elements as $i => $element) {
            if (! is_array($element)) {
                continue;
            }

            if (($element['elType'] ?? null) === 'container') {
                $out[] = $element;
                continue;
            }

            $out[] = [
                'id' => $this->makeId($path . '.wrap.container.' . $i),
                'elType' => 'container',
                'isInner' => false,
                'settings' => [],
                'elements' => [$element],
            ];
        }

        return $out;
    }

    private function normalizeContent(mixed $layout, string $format): array
    {
        if (empty($layout)) {
            return [];
        }

        $elements = $this->mapNode($layout, 'root', $format, 0);

        if ($format === self::FORMAT_CLASSIC) {
            $elements = $this->normalizeClassicRoot($elements, 'root');
            $elements = $this->flattenClassicContent($elements);
        }

        if ($format === self::FORMAT_CLASSIC_SIMPLE) {
            $elements = $this->normalizeClassicRoot($elements, 'root');
            $elements = $this->splitClassicIntoSimpleSections($elements);
            $elements = $this->flattenClassicContent($elements);
        }

        if ($format === self::FORMAT_CONTAINER) {
            $elements = $this->normalizeContainerRoot($elements, 'root');
        }

        if ($this->isList($elements)) {
            return $elements;
        }

        return [$elements];
    }

    private function splitClassicIntoSimpleSections(array $content): array
    {
        $out = [];

        foreach ($content as $i => $element) {
            if (! is_array($element)) {
                continue;
            }

            if (($element['elType'] ?? null) !== 'section' || ($element['isInner'] ?? null) !== false) {
                $out[] = $element;
                continue;
            }

            $split = $this->splitTopLevelClassicSection($element, 'simple.' . $i);
            foreach ($split as $s) {
                $out[] = $s;
            }
        }

        return $out;
    }

    private function splitTopLevelClassicSection(array $section, string $seed): array
    {
        if (($section['settings'] ?? null) !== []) {
            return [$section];
        }

        $cols = $section['elements'] ?? null;
        if (! is_array($cols) || ! $this->isList($cols) || count($cols) !== 1) {
            return [$section];
        }

        $col = $cols[0];
        if (! is_array($col) || ($col['elType'] ?? null) !== 'column') {
            return [$section];
        }

        $children = $col['elements'] ?? null;
        if (! is_array($children) || ! $this->isList($children) || $children === []) {
            return [$section];
        }

        $out = [];
        $buffer = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            if (($child['elType'] ?? null) === 'section' && ($child['isInner'] ?? null) === true) {
                if ($buffer !== []) {
                    $out[] = $this->wrapClassicElementsAsTopLevelSection($buffer, $seed . '.buf.' . count($out));
                    $buffer = [];
                }

                $child['isInner'] = false;
                $out[] = $child;
                continue;
            }

            $buffer[] = $child;
        }

        if ($buffer !== []) {
            $out[] = $this->wrapClassicElementsAsTopLevelSection($buffer, $seed . '.buf.' . count($out));
        }

        // Only replace the section if we actually produced more than one editable block.
        if (count($out) <= 1) {
            return [$section];
        }

        return $out;
    }

    private function wrapClassicElementsAsTopLevelSection(array $elements, string $seed): array
    {
        $sectionId = $this->makeSyntheticId('section.' . $seed);
        $columnId = $this->makeSyntheticId('column.' . $seed);

        return [
            'id' => $sectionId,
            'elType' => 'section',
            'isInner' => false,
            'settings' => [],
            'elements' => [
                [
                    'id' => $columnId,
                    'elType' => 'column',
                    'isInner' => false,
                    'settings' => [],
                    'elements' => $elements,
                ],
            ],
        ];
    }

    private function makeSyntheticId(string $seed): string
    {
        return substr(md5($seed), 0, 8);
    }

    private function flattenClassicContent(array $content): array
    {
        $out = [];
        foreach ($content as $element) {
            if (! is_array($element)) {
                continue;
            }

            $out[] = $this->flattenClassicElement($element);
        }

        return $out;
    }

    private function flattenClassicElement(array $element): array
    {
        $children = $element['elements'] ?? null;
        if (! is_array($children) || ! $this->isList($children)) {
            return $element;
        }

        // Columns are where we most often get redundant wrappers: column -> inner section -> column.
        if (($element['elType'] ?? null) === 'column') {
            $element['elements'] = $this->flattenClassicColumnChildren($children);
            return $element;
        }

        $outChildren = [];
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $outChildren[] = $this->flattenClassicElement($child);
        }

        $element['elements'] = $outChildren;

        return $element;
    }

    private function flattenClassicColumnChildren(array $children): array
    {
        $out = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $child = $this->flattenClassicElement($child);

            if ($this->isRedundantClassicInnerSection($child)) {
                $innerColumn = $child['elements'][0] ?? null;
                $grandChildren = is_array($innerColumn) ? ($innerColumn['elements'] ?? null) : null;
                $grandChildren = is_array($grandChildren) && $this->isList($grandChildren) ? $grandChildren : [];

                foreach ($grandChildren as $grandChild) {
                    if (! is_array($grandChild)) {
                        continue;
                    }

                    $out[] = $this->flattenClassicElement($grandChild);
                }

                continue;
            }

            $out[] = $child;
        }

        return $out;
    }

    private function isRedundantClassicInnerSection(array $element): bool
    {
        if (($element['elType'] ?? null) !== 'section') {
            return false;
        }

        if (($element['isInner'] ?? null) !== true) {
            return false;
        }

        if (($element['settings'] ?? null) !== []) {
            return false;
        }

        $elements = $element['elements'] ?? null;
        if (! is_array($elements) || ! $this->isList($elements) || count($elements) !== 1) {
            return false;
        }

        $col = $elements[0];
        if (! is_array($col)) {
            return false;
        }

        if (($col['elType'] ?? null) !== 'column') {
            return false;
        }

        if (($col['settings'] ?? null) !== []) {
            return false;
        }

        // In Elementor docs, classic columns are not marked as inner.
        if (($col['isInner'] ?? null) !== false) {
            return false;
        }

        return true;
    }

    private function mapNode(mixed $node, string $path, string $format, int $depth): array
    {
        if ($node === null) {
            return [];
        }

        if (is_string($node)) {
            return $this->textWidget($node, $path);
        }

        if (! is_array($node)) {
            return [];
        }

        if ($this->isList($node)) {
            $out = [];
            foreach ($node as $i => $child) {
                $mapped = $this->mapNode($child, $path . '.' . $i, $format, $depth);

                if ($mapped === []) {
                    continue;
                }

                if ($this->isList($mapped)) {
                    foreach ($mapped as $m) {
                        $out[] = $m;
                    }
                } else {
                    $out[] = $mapped;
                }
            }

            return $out;
        }

        if (isset($node['html']) && is_string($node['html'])) {
            return $this->htmlWidget($node['html'], $path);
        }

        $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : null;

        if ($type === 'section') {
            $children = $node['children'] ?? [];

            if ($format === self::FORMAT_CONTAINER) {
                return $this->containerElement('container', $children, $path, $format, $depth, $this->layoutStyleToContainerSettings($node));
            }

            if (is_array($children)
                && count($children) === 1
                && is_array($children[0])
                && (($children[0]['type'] ?? null) === 'columns')
            ) {
                return $this->columnsElement($children[0], $path, $format, $depth);
            }

            return $this->classicSectionFromChildren($children, $path, $format, $depth);
        }

        if ($type === 'container') {
            $children = $node['children'] ?? [];

            if ($format === self::FORMAT_CLASSIC) {
                return $this->classicSectionFromChildren($children, $path, $format, $depth);
            }

            return $this->containerElement('container', $children, $path, $format, $depth, $this->layoutStyleToContainerSettings($node));
        }

        if ($type === 'columns') {
            if ($format === self::FORMAT_CONTAINER) {
                return $this->columnsAsContainersElement($node, $path, $format, $depth);
            }

            return $this->columnsElement($node, $path, $format, $depth);
        }

        if ($type === 'heading') {
            $text = is_string($node['text'] ?? null) ? $node['text'] : '';
            $level = (int) ($node['level'] ?? 2);
            $level = max(1, min(6, $level));

            return $this->headingWidget($text, $level, $path);
        }

        if ($type === 'text') {
            $text = is_string($node['text'] ?? null) ? $node['text'] : '';

            return $this->textWidget($text, $path);
        }

        if ($type === 'image') {
            $src = is_string($node['src'] ?? null) ? $node['src'] : '';
            $alt = is_string($node['alt'] ?? null) ? $node['alt'] : '';

            return $this->imageWidget($src, $alt, $path);
        }

        if ($type === 'button') {
            $label = is_string($node['label'] ?? null) ? $node['label'] : 'Button';
            $href = is_string($node['href'] ?? null) ? $node['href'] : '#';

            return $this->buttonWidget($label, $href, $path);
        }

        if ($type === 'input') {
            $placeholder = is_string($node['placeholder'] ?? null) ? $node['placeholder'] : '';

            $html = '<input type="text" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES) . '" />';

            return $this->htmlWidget($html, $path);
        }

        if ($type === 'nav') {
            $items = $node['items'] ?? [];
            $items = is_array($items) ? $items : [];

            $html = '<nav>';
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $label = is_string($item['label'] ?? null) ? $item['label'] : '';
                $href = is_string($item['href'] ?? null) ? $item['href'] : '#';

                if ($label === '') {
                    continue;
                }

                $html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
            }
            $html .= '</nav>';

            return $this->htmlWidget($html, $path);
        }

        return [];
    }

    private function containerElement(string $elType, mixed $children, string $path, string $format, int $depth): array
    {
        $elements = $this->mapNode(is_array($children) ? $children : [], $path . '.elements', $format, $depth + 1);

        if (! $this->isList($elements)) {
            $elements = $elements === [] ? [] : [$elements];
        }

        return [
            'id' => $this->makeId($path),
            'elType' => $elType,
            'isInner' => $depth > 0,
            'settings' => func_num_args() >= 6 && is_array(func_get_arg(5)) ? func_get_arg(5) : [],
            'elements' => $elements,
        ];
    }

    private function columnsElement(array $node, string $path, string $format, int $depth): array
    {
        $columns = $node['columns'] ?? [];
        $columns = is_array($columns) ? $columns : [];

        $colElements = [];
        foreach ($columns as $i => $col) {
            $mapped = $this->mapNode($col, $path . '.col.' . $i, $format, $depth + 2);

            if (! $this->isList($mapped)) {
                $mapped = $mapped === [] ? [] : [$mapped];
            }

            $colElements[] = [
                'id' => $this->makeId($path . '.column.' . $i),
                'elType' => 'column',
                'isInner' => false,
                'settings' => [],
                'elements' => $mapped,
            ];
        }

        return [
            'id' => $this->makeId($path),
            'elType' => 'section',
            'isInner' => $depth > 0,
            'settings' => [],
            'elements' => $colElements,
        ];
    }

    private function columnsAsContainersElement(array $node, string $path, string $format, int $depth): array
    {
        $columns = $node['columns'] ?? [];
        $columns = is_array($columns) ? $columns : [];

        $colElements = [];
        foreach ($columns as $i => $col) {
            $mapped = $this->mapNode($col, $path . '.col.' . $i, $format, $depth + 2);

            if (! $this->isList($mapped)) {
                $mapped = $mapped === [] ? [] : [$mapped];
            }

            $colElements[] = [
                'id' => $this->makeId($path . '.container.' . $i),
                'elType' => 'container',
                'isInner' => $depth > 0,
                'settings' => $this->layoutStyleToContainerSettings(is_array($col) ? $col : []),
                'elements' => $mapped,
            ];
        }

        return [
            'id' => $this->makeId($path),
            'elType' => 'container',
            'isInner' => $depth > 0,
            'settings' => $this->layoutStyleToContainerSettings($node),
            'elements' => $colElements,
        ];
    }

    private function layoutStyleToContainerSettings(array $node): array
    {
        $style = $node['style'] ?? null;
        if (! is_array($style)) {
            return [];
        }

        $padding = $style['padding'] ?? null;
        if (! is_array($padding)) {
            return [];
        }

        $t = is_numeric($padding['top'] ?? null) ? (float) $padding['top'] : null;
        $r = is_numeric($padding['right'] ?? null) ? (float) $padding['right'] : null;
        $b = is_numeric($padding['bottom'] ?? null) ? (float) $padding['bottom'] : null;
        $l = is_numeric($padding['left'] ?? null) ? (float) $padding['left'] : null;

        if ($t === null && $r === null && $b === null && $l === null) {
            return [];
        }

        $t = (string) (int) round((float) ($t ?? 0));
        $r = (string) (int) round((float) ($r ?? 0));
        $b = (string) (int) round((float) ($b ?? 0));
        $l = (string) (int) round((float) ($l ?? 0));

        $isLinked = $t === $r && $r === $b && $b === $l;

        return [
            'padding' => [
                'unit' => 'px',
                'top' => $t,
                'right' => $r,
                'bottom' => $b,
                'left' => $l,
                'isLinked' => $isLinked,
            ],
        ];
    }

    private function headingWidget(string $text, int $level, string $path): array
    {
        return [
            'id' => $this->makeId($path),
            'elType' => 'widget',
            'widgetType' => 'heading',
            'isInner' => false,
            'settings' => [
                'title' => $text,
                'header_size' => 'h' . $level,
            ],
            'elements' => [],
        ];
    }

    private function textWidget(string $text, string $path): array
    {
        return [
            'id' => $this->makeId($path),
            'elType' => 'widget',
            'widgetType' => 'text-editor',
            'isInner' => false,
            'settings' => [
                'editor' => '<p>' . htmlspecialchars($text, ENT_QUOTES) . '</p>',
            ],
            'elements' => [],
        ];
    }

    private function imageWidget(string $src, string $alt, string $path): array
    {
        if ($src === '') {
            return [];
        }

        return [
            'id' => $this->makeId($path),
            'elType' => 'widget',
            'widgetType' => 'image',
            'isInner' => false,
            'settings' => [
                'image' => [
                    'url' => $src,
                ],
                'caption' => $alt,
            ],
            'elements' => [],
        ];
    }

    private function buttonWidget(string $label, string $href, string $path): array
    {
        return [
            'id' => $this->makeId($path),
            'elType' => 'widget',
            'widgetType' => 'button',
            'isInner' => false,
            'settings' => [
                'text' => $label,
                'link' => [
                    'url' => $href,
                ],
            ],
            'elements' => [],
        ];
    }

    private function htmlWidget(string $html, string $path): array
    {
        return [
            'id' => $this->makeId($path),
            'elType' => 'widget',
            'widgetType' => 'html',
            'isInner' => false,
            'settings' => [
                'html' => $html,
            ],
            'elements' => [],
        ];
    }

    private function makeId(string $path): string
    {
        return substr(md5($path), 0, 8);
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ($format === self::FORMAT_CONTAINER) {
            return self::FORMAT_CONTAINER;
        }

        if ($format === self::FORMAT_CLASSIC_SIMPLE) {
            return self::FORMAT_CLASSIC_SIMPLE;
        }

        return self::FORMAT_CLASSIC;
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
