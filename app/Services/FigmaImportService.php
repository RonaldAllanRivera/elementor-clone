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

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($baseUrl . '/files/' . $fileKey . '/nodes', [
                'ids' => $nodeId,
                'depth' => 4,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('Failed to fetch Figma node.');
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

        if (! preg_match('/^\d+:\d+$/', $nodeId)) {
            throw new RuntimeException('Invalid node-id in Figma URL.');
        }

        return [$fileKey, $nodeId];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapFrameToLayout(array $frame): array
    {
        $textNodes = [];
        $this->collectTextNodes($frame, $textNodes);

        usort($textNodes, function (array $a, array $b): int {
            $ay = (float) ($a['absY'] ?? 0);
            $by = (float) ($b['absY'] ?? 0);

            if ($ay === $by) {
                $ax = (float) ($a['absX'] ?? 0);
                $bx = (float) ($b['absX'] ?? 0);
                return $ax <=> $bx;
            }

            return $ay <=> $by;
        });

        $children = [];
        foreach ($textNodes as $t) {
            $text = is_string($t['text'] ?? null) ? trim($t['text']) : '';
            if ($text === '') {
                continue;
            }

            $fontSize = (float) ($t['fontSize'] ?? 0);

            if ($fontSize >= 32) {
                $children[] = ['type' => 'heading', 'text' => $text, 'level' => 1];
                continue;
            }

            if ($fontSize >= 24) {
                $children[] = ['type' => 'heading', 'text' => $text, 'level' => 2];
                continue;
            }

            $children[] = ['type' => 'text', 'text' => $text];
        }

        if ($children === []) {
            $name = is_string($frame['name'] ?? null) ? trim((string) $frame['name']) : '';
            $name = $name !== '' ? $name : 'Imported frame';
            $children[] = ['type' => 'text', 'text' => $name];
        }

        return $children;
    }

    /**
     * @param array<int, array<string, mixed>> $out
     */
    private function collectTextNodes(array $node, array &$out): void
    {
        $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : '';

        if ($type === 'TEXT') {
            $abs = $node['absoluteBoundingBox'] ?? null;
            $style = $node['style'] ?? null;

            $out[] = [
                'text' => $node['characters'] ?? '',
                'fontSize' => is_array($style) ? ($style['fontSize'] ?? 0) : 0,
                'absX' => is_array($abs) ? ($abs['x'] ?? 0) : 0,
                'absY' => is_array($abs) ? ($abs['y'] ?? 0) : 0,
            ];
        }

        $children = $node['children'] ?? [];
        if (is_array($children)) {
            foreach ($children as $child) {
                if (is_array($child)) {
                    $this->collectTextNodes($child, $out);
                }
            }
        }
    }
}
