<?php

namespace App\Services;

class LayoutToElementorService
{
    public const FORMAT_CLASSIC = 'classic';
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
                    'isInner' => true,
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
                        'isInner' => true,
                        'settings' => [],
                        'elements' => [$element],
                    ],
                ],
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
        }

        if ($this->isList($elements)) {
            return $elements;
        }

        return [$elements];
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
                return $this->containerElement('container', $children, $path, $format, $depth);
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

            return $this->containerElement('container', $children, $path, $format, $depth);
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
            'settings' => [],
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
                'isInner' => true,
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
                'isInner' => true,
                'settings' => [],
                'elements' => $mapped,
            ];
        }

        return [
            'id' => $this->makeId($path),
            'elType' => 'container',
            'isInner' => $depth > 0,
            'settings' => [],
            'elements' => $colElements,
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
