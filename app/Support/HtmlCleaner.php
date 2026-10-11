<?php

namespace App\Support;

/**
 * Cleans the HTML typed or pasted into the product description editors.
 * Formatting is kept (bold, italic, colors, font sizes, links, lists, tables, images ...),
 * everything that can run code is removed (scripts, iframes, event handlers, javascript: links ...).
 * It uses PHP's built-in DOM extension only, so no package is needed.
 */
class HtmlCleaner
{
    /** Tags that are kept. Any other tag is removed but its text is kept. */
    private const ALLOWED = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup', 'dd', 'del', 'div', 'dl', 'dt',
        'em', 'figcaption', 'figure', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'mark',
        'ol', 'p', 'pre', 's', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot',
        'th', 'thead', 'tr', 'u', 'ul',
    ];

    /** Tags that are removed together with everything inside them. */
    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'link', 'meta', 'base', 'noscript', 'svg', 'math', 'head', 'title',
        'audio', 'video', 'source', 'canvas', 'template', 'xml',
    ];

    /** Extra attributes per tag (every tag may also have style and title). */
    private const ATTRS = [
        'a'     => ['href', 'target', 'rel'],
        'img'   => ['src', 'alt', 'width', 'height'],
        'td'    => ['colspan', 'rowspan', 'align', 'valign', 'width'],
        'th'    => ['colspan', 'rowspan', 'align', 'valign', 'width', 'scope'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'width', 'align'],
        'col'   => ['span', 'width'],
        'colgroup' => ['span', 'width'],
        'ol'    => ['start', 'type'],
        'font'  => ['color', 'size', 'face'],
        'p'     => ['align'],
        'div'   => ['align'],
        'h1' => ['align'], 'h2' => ['align'], 'h3' => ['align'], 'h4' => ['align'], 'h5' => ['align'], 'h6' => ['align'],
    ];

    /** CSS properties that are kept. */
    private const STYLE_PROPS = [
        'color', 'background-color', 'background', 'font-size', 'font-family', 'font-weight', 'font-style',
        'text-decoration', 'text-align', 'text-indent', 'line-height', 'letter-spacing', 'vertical-align',
        'list-style-type', 'width', 'max-width', 'height', 'border', 'border-top', 'border-bottom', 'border-left',
        'border-right', 'border-collapse', 'border-color', 'border-style', 'border-width', 'margin', 'margin-top',
        'margin-bottom', 'margin-left', 'margin-right', 'padding', 'padding-top', 'padding-bottom', 'padding-left',
        'padding-right',
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        // Without the DOM extension, fall back to plain text (always safe).
        if (!class_exists(\DOMDocument::class)) {
            return nl2br(e(strip_tags($html)));
        }

        $prev = libxml_use_internal_errors(true);
        $dom  = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body><div id="hc-root">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $dom->getElementById('hc-root');
        if (!$root) {
            return nl2br(e(strip_tags($html)));
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /** True when the cleaned HTML has no visible text and no image (for example "<p><br></p>"). */
    public static function isEmpty(?string $html): bool
    {
        $html = (string) $html;

        return trim(self::plain($html)) === '' && !preg_match('/<img\b/i', $html);
    }

    /** Plain text of some HTML (entities decoded, tags removed, words kept apart). */
    public static function plain(?string $html): string
    {
        $text = preg_replace('#</(p|div|li|h[1-6]|tr|blockquote)>|<br\s*/?>#i', ' ', (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }
            if (!($child instanceof \DOMElement)) {
                $node->removeChild($child);   // comments, processing instructions, CDATA
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::ALLOWED, true)) {
                self::walk($child);                                  // clean what is inside first
                while ($child->firstChild) {                         // then keep only the contents
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
            self::walk($child);
        }
    }

    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = array_merge(['style', 'title'], self::ATTRS[$tag] ?? []);

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $val  = trim($attr->nodeValue);

            if (!in_array($name, $allowed, true) || self::badValue($val)) {
                $el->removeAttribute($attr->nodeName);
                continue;
            }

            switch ($name) {
                case 'style':
                    $style = self::cleanStyle($val);
                    $style === '' ? $el->removeAttribute('style') : $el->setAttribute('style', $style);
                    break;
                case 'href':
                    self::safeUrl($val, ['http', 'https', 'mailto', 'tel']) ? null : $el->removeAttribute('href');
                    break;
                case 'src':
                    self::safeUrl($val, ['http', 'https']) ? null : $el->removeAttribute('src');
                    break;
                case 'target':
                    $val === '_blank' ? null : $el->removeAttribute('target');
                    break;
                case 'color':
                case 'face':
                    preg_match('/^[#A-Za-z0-9 ,\'"\-]{1,60}$/', $val) ? null : $el->removeAttribute($name);
                    break;
                case 'width':
                case 'height':
                case 'size':
                case 'colspan':
                case 'rowspan':
                case 'span':
                case 'start':
                case 'border':
                case 'cellpadding':
                case 'cellspacing':
                    preg_match('/^\d{1,4}%?$/', $val) ? null : $el->removeAttribute($name);
                    break;
                case 'rel':
                case 'scope':
                case 'align':
                case 'valign':
                case 'type':
                    preg_match('/^[A-Za-z0-9 \-]{1,30}$/', $val) ? null : $el->removeAttribute($name);
                    break;
            }
        }

        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'noopener noreferrer');
        } elseif ($tag === 'a') {
            $el->removeAttribute('target');
        }
        if ($tag === 'img') {
            if (!$el->hasAttribute('src')) {
                $el->parentNode->removeChild($el);
                return;
            }
            $el->setAttribute('loading', 'lazy');
        }
    }

    /** Values that could hide code are never allowed in any attribute. */
    private static function badValue(string $v): bool
    {
        return (bool) preg_match('/(javascript|vbscript|expression\s*\(|-moz-binding|behavior\s*:|@import|<\s*script)/i', $v);
    }

    /** Allowed web address: one of the given schemes, or a relative / anchor link. */
    private static function safeUrl(string $url, array $schemes): bool
    {
        $u = preg_replace('/[\x00-\x20]+/', '', $url);
        if ($u === '' || strlen($u) > 2000) {
            return false;
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $u, $m)) {
            return in_array(strtolower($m[1]), $schemes, true);
        }

        return true;
    }

    private static function cleanStyle(string $style): string
    {
        $out = [];
        foreach (explode(';', $style) as $decl) {
            $pos = strpos($decl, ':');
            if ($pos === false) {
                continue;
            }
            $prop = strtolower(trim(substr($decl, 0, $pos)));
            $val  = trim(substr($decl, $pos + 1));
            $val  = trim(preg_replace('/\s*!important\s*$/i', '', $val));

            if ($val === '' || strlen($val) > 200 || !in_array($prop, self::STYLE_PROPS, true)) {
                continue;
            }
            if (preg_match('/(url\s*\(|\\\\|[<>@{}]|\/\*)/', $val)) {
                continue;
            }
            if (strpos($prop, 'font-family') === 0 && !preg_match('/^[A-Za-z0-9 ,\'"\-_]+$/u', $val)) {
                continue;
            }
            if ($prop === 'background' && !preg_match('/^(#[0-9a-f]{3,8}|rgba?\([\d\s,.%]+\)|[a-z]+)$/i', $val)) {
                continue;
            }

            $out[] = $prop . ': ' . $val;
        }

        return implode('; ', $out);
    }
}
