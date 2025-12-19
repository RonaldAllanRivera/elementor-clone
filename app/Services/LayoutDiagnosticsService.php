<?php

namespace App\Services;

class LayoutDiagnosticsService
{
    public function analyze(mixed $layout): array
    {
        $stats = [
            'total_nodes' => 0,
            'types' => [],
            'styles' => [
                'backgroundColor' => 0,
                'border' => 0,
                'borderRadius' => 0,
                'boxShadow' => 0,
                'typography' => 0,
                'gap' => 0,
                'padding' => 0,
                'widthPercent' => 0,
                'flexGrow' => 0,
            ],
            'images' => [
                'total' => 0,
                'placeholders' => 0,
            ],
            'warnings' => [],
        ];

        if (! is_array($layout) || $layout === []) {
            $stats['warnings'][] = 'Layout JSON is empty.';

            return $stats;
        }

        $this->walk($layout, $stats);

        if (($stats['styles']['backgroundColor'] ?? 0) === 0) {
            $stats['warnings'][] = 'No background colors detected.';
        }

        if (($stats['styles']['typography'] ?? 0) === 0) {
            $stats['warnings'][] = 'No typography styles detected.';
        }

        if (($stats['images']['placeholders'] ?? 0) > 0) {
            $stats['warnings'][] = 'Placeholder images are being used.';
        }

        return $stats;
    }

    private function walk(mixed $node, array &$stats): void
    {
        if ($node === null) {
            return;
        }

        if (is_array($node) && $this->isList($node)) {
            foreach ($node as $item) {
                $this->walk($item, $stats);
            }

            return;
        }

        if (! is_array($node)) {
            return;
        }

        $stats['total_nodes']++;

        $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : 'unknown';
        $stats['types'][$type] = ($stats['types'][$type] ?? 0) + 1;

        $style = isset($node['style']) && is_array($node['style']) ? $node['style'] : [];

        if (isset($style['backgroundColor'])) {
            $stats['styles']['backgroundColor']++;
        }

        if (isset($style['border'])) {
            $stats['styles']['border']++;
        }

        if (isset($style['borderRadius'])) {
            $stats['styles']['borderRadius']++;
        }

        if (isset($style['boxShadow'])) {
            $stats['styles']['boxShadow']++;
        }

        if (isset($style['fontFamily']) || isset($style['fontSize']) || isset($style['fontWeight']) || isset($style['color'])) {
            $stats['styles']['typography']++;
        }

        if (isset($style['gap'])) {
            $stats['styles']['gap']++;
        }

        if (isset($style['padding'])) {
            $stats['styles']['padding']++;
        }

        if (isset($style['widthPercent'])) {
            $stats['styles']['widthPercent']++;
        }

        if (isset($style['flexGrow'])) {
            $stats['styles']['flexGrow']++;
        }

        if ($type === 'image') {
            $stats['images']['total']++;
            $src = is_string($node['src'] ?? null) ? $node['src'] : '';
            if ($src !== '' && str_contains($src, 'placehold.co')) {
                $stats['images']['placeholders']++;
            }
        }

        foreach (['children', 'elements', 'columns', 'items'] as $key) {
            if (isset($node[$key]) && is_array($node[$key])) {
                $this->walk($node[$key], $stats);
            }
        }
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
