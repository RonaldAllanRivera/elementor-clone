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
    * { box-sizing: border-box; }
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; margin: 0; padding: 0; background: #fff; color: #111827; }
    .container { width: 100%; max-width: none; margin: 0; padding: 0; }
    .section { margin: 0; padding: 0; border: 0; }
    .row { display: flex; }
    h1,h2,h3,p { margin: 0; font-size: inherit; font-weight: inherit; line-height: inherit; }
    a { color: inherit; text-decoration: none; }
    a.button { display: inline-flex; align-items: center; justify-content: center; }
    img { max-width: 100%; height: auto; display: block; }
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

                $style = is_array($node['style'] ?? null) ? $node['style'] : [];
                $inline = $this->inlineStyleFromLayoutStyle($style);

                return '<div class="section"' . ($inline !== '' ? (' style="' . htmlspecialchars($inline, ENT_QUOTES) . '"') : '') . '>'
                    . $this->renderNode($children)
                    . '</div>';
            }

            if ($type === 'columns') {
                $columns = $node['columns'] ?? [];

                $style = is_array($node['style'] ?? null) ? $node['style'] : [];
                $inline = $this->inlineStyleFromLayoutStyle($style, true);

                $out = '<div class="row"' . ($inline !== '' ? (' style="' . htmlspecialchars($inline, ENT_QUOTES) . '"') : '') . '>';
                foreach (is_array($columns) ? $columns : [] as $col) {
                    $colStyle = is_array($col) && is_array($col['style'] ?? null) ? $col['style'] : [];

                    if (! isset($colStyle['widthPercent']) && ! isset($colStyle['flexGrow']) && ! isset($colStyle['flexBasis'])) {
                        $colStyle['flexGrow'] = 1;
                        $colStyle['flexBasis'] = 0;
                    }

                    $colInline = $this->inlineStyleFromLayoutStyle($colStyle);
                    $children = is_array($col) ? ($col['children'] ?? null) : null;

                    $out .= '<div class="col"' . ($colInline !== '' ? (' style="' . htmlspecialchars($colInline, ENT_QUOTES) . '"') : '') . '>'
                        . $this->renderNode(is_array($children) ? $children : $col)
                        . '</div>';
                }
                $out .= '</div>';

                return $out;
            }

            if ($type === 'heading') {
                $text = is_string($node['text'] ?? null) ? $node['text'] : '';
                $level = (int) ($node['level'] ?? 2);
                $level = max(1, min(3, $level));

                $style = is_array($node['style'] ?? null) ? $node['style'] : [];
                $inline = $this->inlineStyleFromLayoutStyle($style);

                return '<h' . $level . ($inline !== '' ? (' style="' . htmlspecialchars($inline, ENT_QUOTES) . '"') : '') . '>'
                    . htmlspecialchars($text, ENT_QUOTES)
                    . '</h' . $level . '>';
            }

            if ($type === 'text') {
                $text = is_string($node['text'] ?? null) ? $node['text'] : '';

                $style = is_array($node['style'] ?? null) ? $node['style'] : [];
                $inline = $this->inlineStyleFromLayoutStyle($style);

                return '<p' . ($inline !== '' ? (' style="' . htmlspecialchars($inline, ENT_QUOTES) . '"') : '') . '>'
                    . htmlspecialchars($text, ENT_QUOTES)
                    . '</p>';
            }

            if ($type === 'image') {
                $src = is_string($node['src'] ?? null) ? $node['src'] : '';
                $alt = is_string($node['alt'] ?? null) ? $node['alt'] : '';

                if ($src === '') {
                    return '';
                }

                $style = is_array($node['style'] ?? null) ? $node['style'] : [];
                $inline = $this->inlineStyleFromLayoutStyle($style);

                return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"'
                    . ($inline !== '' ? (' style="' . htmlspecialchars($inline, ENT_QUOTES) . '"') : '')
                    . ' />';
            }

            if ($type === 'button') {
                $label = is_string($node['label'] ?? null) ? $node['label'] : 'Button';
                $href = is_string($node['href'] ?? null) ? $node['href'] : '#';

                $style = is_array($node['style'] ?? null) ? $node['style'] : [];
                $inline = $this->inlineStyleFromLayoutStyle($style);

                return '<a class="button" href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
                    . ($inline !== '' ? (' style="' . htmlspecialchars($inline, ENT_QUOTES) . '"') : '')
                    . '>' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
            }

            if ($type === 'nav') {
                $items = $node['items'] ?? [];
                $style = is_array($node['style'] ?? null) ? $node['style'] : [];
                $inline = $this->inlineStyleFromLayoutStyle($style, true);

                $out = '<nav' . ($inline !== '' ? (' style="' . htmlspecialchars($inline, ENT_QUOTES) . '"') : '') . '>';

                foreach (is_array($items) ? $items : [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $label = is_string($item['label'] ?? null) ? $item['label'] : '';
                    $href = is_string($item['href'] ?? null) ? $item['href'] : '#';
                    $itemStyle = is_array($item['style'] ?? null) ? $item['style'] : [];
                    $itemInline = $this->inlineStyleFromLayoutStyle($itemStyle);

                    if ($label === '') {
                        continue;
                    }

                    $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '"'
                        . ($itemInline !== '' ? (' style="' . htmlspecialchars($itemInline, ENT_QUOTES) . '"') : '')
                        . '>' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
                }

                $out .= '</nav>';

                return $out;
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

    private function inlineStyleFromLayoutStyle(array $style, bool $forceRow = false): string
    {
        $css = [];

        $direction = is_string($style['direction'] ?? null) ? (string) $style['direction'] : '';
        if ($forceRow) {
            $direction = 'row';
        }

        if ($direction === 'row' || $direction === 'column') {
            $css[] = 'display:flex';
            $css[] = 'flex-direction:' . $direction;
        }

        if (is_numeric($style['flexGrow'] ?? null)) {
            $css[] = 'flex-grow:' . (string) (float) $style['flexGrow'];
        }

        if (is_numeric($style['flexShrink'] ?? null)) {
            $css[] = 'flex-shrink:' . (string) (float) $style['flexShrink'];
        }

        if (is_numeric($style['flexBasis'] ?? null)) {
            $css[] = 'flex-basis:' . (string) (float) $style['flexBasis'] . 'px';
        }

        if (is_numeric($style['widthPercent'] ?? null)) {
            $w = max(0.0, min(100.0, (float) $style['widthPercent']));
            $css[] = 'flex:0 0 ' . rtrim(rtrim(number_format($w, 3, '.', ''), '0'), '.') . '%';
            $css[] = 'max-width:' . rtrim(rtrim(number_format($w, 3, '.', ''), '0'), '.') . '%';
        }

        if (is_numeric($style['minHeightPx'] ?? null)) {
            $css[] = 'min-height:' . (string) (int) round((float) $style['minHeightPx']) . 'px';
        }

        if (is_numeric($style['gap'] ?? null)) {
            $css[] = 'gap:' . ((float) $style['gap']) . 'px';
        }

        if (is_string($style['backgroundColor'] ?? null) && $style['backgroundColor'] !== '') {
            $css[] = 'background-color:' . (string) $style['backgroundColor'];
        }

        if (is_string($style['border'] ?? null) && $style['border'] !== '') {
            $css[] = 'border:' . (string) $style['border'];
        }

        if (is_string($style['borderRadius'] ?? null) && $style['borderRadius'] !== '') {
            $css[] = 'border-radius:' . (string) $style['borderRadius'];
        }

        if (is_string($style['boxShadow'] ?? null) && $style['boxShadow'] !== '') {
            $css[] = 'box-shadow:' . (string) $style['boxShadow'];
        }

        $padding = $style['padding'] ?? null;
        if (is_array($padding)) {
            $t = is_numeric($padding['top'] ?? null) ? (float) $padding['top'] : 0;
            $r = is_numeric($padding['right'] ?? null) ? (float) $padding['right'] : 0;
            $b = is_numeric($padding['bottom'] ?? null) ? (float) $padding['bottom'] : 0;
            $l = is_numeric($padding['left'] ?? null) ? (float) $padding['left'] : 0;

            if ($t || $r || $b || $l) {
                $css[] = 'padding:' . $t . 'px ' . $r . 'px ' . $b . 'px ' . $l . 'px';
            }
        }

        $justify = is_string($style['justify'] ?? null) ? strtoupper((string) $style['justify']) : '';
        $align = is_string($style['align'] ?? null) ? strtoupper((string) $style['align']) : '';
        $alignSelf = is_string($style['alignSelf'] ?? null) ? strtoupper((string) $style['alignSelf']) : '';

        $justifyMap = [
            'MIN' => 'flex-start',
            'CENTER' => 'center',
            'MAX' => 'flex-end',
            'SPACE_BETWEEN' => 'space-between',
            'SPACE_AROUND' => 'space-around',
            'SPACE_EVENLY' => 'space-evenly',
        ];

        $alignMap = [
            'MIN' => 'flex-start',
            'CENTER' => 'center',
            'MAX' => 'flex-end',
            'BASELINE' => 'baseline',
            'STRETCH' => 'stretch',
        ];

        if (isset($justifyMap[$justify])) {
            $css[] = 'justify-content:' . $justifyMap[$justify];
        }

        if (isset($alignMap[$align])) {
            $css[] = 'align-items:' . $alignMap[$align];
        }

        if (isset($alignMap[$alignSelf])) {
            $css[] = 'align-self:' . $alignMap[$alignSelf];
        }

        if (is_string($style['fontFamily'] ?? null) && $style['fontFamily'] !== '') {
            $css[] = 'font-family:' . (string) $style['fontFamily'];
        }

        if (is_numeric($style['fontSize'] ?? null)) {
            $css[] = 'font-size:' . ((float) $style['fontSize']) . 'px';
        }

        if (is_numeric($style['fontWeight'] ?? null)) {
            $css[] = 'font-weight:' . (int) $style['fontWeight'];
        }

        if (is_numeric($style['lineHeightPx'] ?? null)) {
            $css[] = 'line-height:' . ((float) $style['lineHeightPx']) . 'px';
        }

        if (is_numeric($style['letterSpacing'] ?? null)) {
            $css[] = 'letter-spacing:' . ((float) $style['letterSpacing']) . 'px';
        }

        if (is_string($style['color'] ?? null) && $style['color'] !== '') {
            $css[] = 'color:' . (string) $style['color'];
        }

        if (is_string($style['textAlign'] ?? null) && $style['textAlign'] !== '') {
            $css[] = 'text-align:' . (string) $style['textAlign'];
        }

        if (is_string($style['textTransform'] ?? null) && $style['textTransform'] !== '') {
            $css[] = 'text-transform:' . (string) $style['textTransform'];
        }

        return implode(';', $css);
    }
}
