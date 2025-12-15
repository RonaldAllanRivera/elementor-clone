<?php

namespace App\Services;

class LayoutToHtmlService
{
    public function render(?array $layout): ?string
    {
        if (empty($layout)) {
            return null;
        }

        $body = $this->renderNode($layout);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 0; padding: 24px; background: #fff; color: #111827; }
    .container { max-width: 960px; margin: 0 auto; }
    .section { margin-bottom: 24px; padding: 16px; border: 1px solid #e5e7eb; border-radius: 10px; }
    .row { display: flex; gap: 16px; }
    .col { flex: 1; }
    h1,h2,h3 { margin: 0 0 12px; }
    p { margin: 0 0 12px; line-height: 1.6; }
    a.button { display: inline-block; padding: 10px 14px; border-radius: 8px; background: #4f46e5; color: white; text-decoration: none; font-weight: 600; }
    img { max-width: 100%; height: auto; border-radius: 8px; }
    pre { white-space: pre-wrap; word-break: break-word; background: #0b1020; color: #e5e7eb; padding: 12px; border-radius: 10px; overflow: auto; }
</style>
</head>
<body>
<div class="container">
$body
</div>
</body>
</html>
HTML;
    }

    private function renderNode(mixed $node): string
    {
        if ($node === null) {
            return '';
        }

        if (is_string($node)) {
            return '<p>' . htmlspecialchars($node, ENT_QUOTES) . '</p>';
        }

        if (is_array($node)) {
            if ($this->isList($node)) {
                $out = '';
                foreach ($node as $item) {
                    $out .= $this->renderNode($item);
                }

                return $out;
            }

            if (isset($node['html']) && is_string($node['html'])) {
                return $node['html'];
            }

            $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : null;

            if ($type === 'section' || $type === 'container') {
                $children = $node['children'] ?? [];

                return '<div class="section">' . $this->renderNode($children) . '</div>';
            }

            if ($type === 'columns') {
                $columns = $node['columns'] ?? [];
                $out = '<div class="row">';
                foreach (is_array($columns) ? $columns : [] as $col) {
                    $out .= '<div class="col">' . $this->renderNode($col) . '</div>';
                }
                $out .= '</div>';

                return $out;
            }

            if ($type === 'heading') {
                $text = is_string($node['text'] ?? null) ? $node['text'] : '';
                $level = (int) ($node['level'] ?? 2);
                $level = max(1, min(3, $level));

                return '<h' . $level . '>' . htmlspecialchars($text, ENT_QUOTES) . '</h' . $level . '>';
            }

            if ($type === 'text') {
                $text = is_string($node['text'] ?? null) ? $node['text'] : '';

                return '<p>' . htmlspecialchars($text, ENT_QUOTES) . '</p>';
            }

            if ($type === 'image') {
                $src = is_string($node['src'] ?? null) ? $node['src'] : '';
                $alt = is_string($node['alt'] ?? null) ? $node['alt'] : '';

                if ($src === '') {
                    return '';
                }

                return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" />';
            }

            if ($type === 'button') {
                $label = is_string($node['label'] ?? null) ? $node['label'] : 'Button';
                $href = is_string($node['href'] ?? null) ? $node['href'] : '#';

                return '<a class="button" href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
            }

            return '<pre>' . htmlspecialchars(json_encode($node, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '', ENT_QUOTES) . '</pre>';
        }

        return '';
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
