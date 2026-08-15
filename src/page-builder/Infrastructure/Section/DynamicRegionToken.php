<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Section;

/**
 * Code-editor mask for dynamic regions.
 *
 * A binding region like <nav data-ai-dynamic="wp_menu">…</nav> looks editable
 * in the code editor but is projected at render time — humans who edit
 * "inside" it lose their change silently. The mask form
 *
 *     <!-- upb:bindings:dynamic_data:site_logo -->
 *     <!-- upb:bindings:dynamic_data:wp_menu b64:eyJ0YWciOiJuYXYiL…= -->
 *
 * is honest about that affordance: there is nothing inside to edit, and
 * removing it is an unambiguous, intentional removal of the binding. Regions
 * that carry state (wrapper tag, attributes, template element) embed it as a
 * base64 JSON payload so the round trip is lossless; bare regions get the
 * payload-free form. Base64 keeps the payload safe inside an HTML comment
 * (no "--" sequences) and unambiguous next to other comment dialects such as
 * Gutenberg's <!-- wp:... --> block grammar.
 *
 * Storage and rendering keep the canonical element form. The mask is a
 * display/authoring encoding: the code editor shows masks, and the write
 * boundary decodes them back to canonical markup.
 */
final class DynamicRegionToken
{
    /**
     * Replace maskable dynamic regions with their comment mask for display
     * in the code editor.
     *
     * With $maskableBindingIds (the registry's contract-driven set: regions
     * whose children are projected and that are not card templates), any
     * matching region is masked — bare regions as payload-free tokens,
     * state-carrying regions with a base64 payload. With null, only the
     * structural rule applies: bare span/div regions with no other
     * attributes and no children, for callers without registry access.
     *
     * With $payloadMasks=false (agent surfaces), state-carrying regions stay
     * visible markup even when their binding id is maskable — agents get
     * payload-free masks only; payload masks are a human-editor affordance.
     *
     * @param list<string>|null $maskableBindingIds
     */
    public static function encodeForCodeEditor(string $html, ?array $maskableBindingIds = null, bool $payloadMasks = true): string
    {
        if ($html === '' || stripos($html, 'data-ai-dynamic') === false) {
            return $html;
        }

        if ($maskableBindingIds === null) {
            return (string) preg_replace_callback(
                '/<(span|div)\s+data-ai-dynamic="([a-z0-9_]+)"\s*>\s*<\/\1>/i',
                static fn(array $matches): string => self::token(strtolower($matches[2])),
                $html,
            );
        }

        // Encode Alpine @ shorthand attributes before DOMDocument parsing.
        // DOMDocument's HTML4 parser strips @-prefixed attribute names.
        $encoded = (string) preg_replace('/\s@([\w.:+-]+)=/', ' data-x-on-$1=', $html);

        $doc = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__upb_mask_root">' . $encoded . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $root = $doc->getElementById('__upb_mask_root');
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $changed = false;

        // Collect into array — DOM mutations during iteration invalidate DOMNodeList.
        $regions = [];
        foreach ($xpath->query('//*[@data-ai-dynamic]') as $node) {
            if ($node instanceof \DOMElement) {
                $regions[] = $node;
            }
        }

        foreach ($regions as $region) {
            $bindingId = strtolower(trim($region->getAttribute('data-ai-dynamic')));
            if (!in_array($bindingId, $maskableBindingIds, true)) {
                continue;
            }

            $payload = self::regionPayload($region, $doc);
            if ($payload !== null && !$payloadMasks) {
                continue;
            }
            $comment = $doc->createComment(
                $payload === null
                    ? ' upb:bindings:dynamic_data:' . $bindingId . ' '
                    : ' upb:bindings:dynamic_data:' . $bindingId . ' b64:' . $payload . ' '
            );
            $region->parentNode?->replaceChild($comment, $region);
            $changed = true;
        }

        if (!$changed) {
            return $html;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        // Decode Alpine @ shorthand back from placeholders.
        return (string) preg_replace('/\sdata-x-on-([\w.:+-]+)=/', ' @$1=', $output);
    }

    /**
     * Decode comment masks back to canonical region markup. Self-contained:
     * payload masks rebuild their stored element; payload-free masks become
     * a bare span. Unparseable payloads degrade to the bare span — the
     * renderer projects into it the same way.
     */
    public static function decode(string $html): string
    {
        if ($html === '' || stripos($html, '<!--') === false) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<!--\s*upb:bindings:dynamic_data:([a-z0-9_]+)(?:\s+b64:([A-Za-z0-9+\/=]+))?\s*-->/i',
            static function (array $matches): string {
                $bindingId = strtolower($matches[1]);
                $payload = $matches[2] ?? '';

                if ($payload !== '') {
                    $rebuilt = self::rebuildFromPayload($bindingId, $payload);
                    if ($rebuilt !== null) {
                        return $rebuilt;
                    }
                }

                return '<span data-ai-dynamic="' . $bindingId . '"></span>';
            },
            $html,
        );
    }

    /**
     * Serialize a region's state (wrapper tag, attributes, inner markup) for
     * the mask payload. Returns null for bare empty regions — they need no
     * payload.
     */
    private static function regionPayload(\DOMElement $region, \DOMDocument $doc): ?string
    {
        $attributes = [];
        foreach ($region->attributes as $attribute) {
            if ($attribute->name === 'data-ai-dynamic') {
                continue;
            }
            // Restore Alpine @ shorthand inside attribute names.
            $name = (string) preg_replace('/^data-x-on-/', '@', $attribute->name);
            $attributes[$name] = (string) $attribute->value;
        }

        $inner = '';
        foreach ($region->childNodes as $child) {
            $inner .= $doc->saveHTML($child);
        }
        $inner = (string) preg_replace('/\sdata-x-on-([\w.:+-]+)=/', ' @$1=', $inner);

        $tag = strtolower($region->nodeName);

        if ($attributes === [] && trim($inner) === '' && in_array($tag, ['span', 'div'], true)) {
            return null;
        }

        return base64_encode(self::encodeJson([
            'tag' => $tag,
            'attrs' => $attributes,
            'inner' => $inner,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function rebuildFromPayload(string $bindingId, string $payload): ?string
    {
        $decoded = base64_decode($payload, true);
        if (!is_string($decoded)) {
            return null;
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return null;
        }

        $tag = is_string($data['tag'] ?? null) && preg_match('/^[a-z][a-z0-9]*$/', $data['tag']) === 1
            ? $data['tag']
            : 'span';
        $attributes = is_array($data['attrs'] ?? null) ? $data['attrs'] : [];
        $inner = is_string($data['inner'] ?? null) ? $data['inner'] : '';

        $attributeString = '';
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value) || preg_match('/^[@a-zA-Z][\w.:+-]*$/', $name) !== 1) {
                continue;
            }
            $attributeString .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<' . $tag . $attributeString . ' data-ai-dynamic="' . $bindingId . '">' . $inner . '</' . $tag . '>';
    }

    /**
     * Masks are atomic: a patch may keep a mask byte-identical, delete it
     * whole, or add a well-formed payload-free token — never mutate one.
     * Returns a violation message, or null when the patched string honors
     * the contract. Both inputs are mask-space strings.
     */
    public static function findAtomicityViolation(string $beforeMasked, string $afterMasked): ?string
    {
        if (stripos($afterMasked, 'upb:bindings') === false) {
            return null;
        }

        preg_match_all('/<!--\s*upb:bindings:dynamic_data:[^>]*?-->/i', $beforeMasked, $beforeMatches);
        $allowed = array_flip($beforeMatches[0]);

        $wellFormedMasks = [];
        preg_match_all('/<!--\s*upb:bindings:dynamic_data:[^>]*?-->/i', $afterMasked, $afterMatches, PREG_OFFSET_CAPTURE);
        foreach ($afterMatches[0] as [$mask, $offset]) {
            $isPreExisting = isset($allowed[$mask]);
            $isPayloadFreeToken = preg_match('/^<!--\s*upb:bindings:dynamic_data:[a-z0-9_]+\s*-->$/i', $mask) === 1;

            if (!$isPreExisting && !$isPayloadFreeToken) {
                return sprintf(
                    'Mask edits are not allowed: "%s" is a modified binding mask. Masks are atomic — quote them verbatim, delete the whole mask, or add a payload-free <!-- upb:bindings:dynamic_data:{id} --> token.',
                    strlen($mask) > 120 ? substr($mask, 0, 117) . '…' : $mask,
                );
            }

            $wellFormedMasks[] = [$offset, strlen($mask)];
        }

        // Any 'upb:bindings' text OUTSIDE a well-formed mask is a mangled
        // mask remnant (e.g. a patch that broke the comment terminator).
        $offset = 0;
        while (($position = stripos($afterMasked, 'upb:bindings', $offset)) !== false) {
            $inside = false;
            foreach ($wellFormedMasks as [$maskStart, $maskLength]) {
                if ($position >= $maskStart && $position < $maskStart + $maskLength) {
                    $inside = true;
                    break;
                }
            }

            if (!$inside) {
                return 'Mask edits are not allowed: a binding mask was partially modified or its comment markers were broken. Masks are atomic — quote them verbatim or delete the whole mask.';
            }

            $offset = $position + 1;
        }

        return null;
    }

    /**
     * Binding ids present in the markup, tolerant of quoting style and
     * whitespace — authored HTML is not guaranteed to match DOMDocument's
     * double-quoted serialization.
     *
     * @return list<string>
     */
    public static function bindingIdsIn(string $html): array
    {
        if (
            preg_match_all(
                '/data-ai-dynamic\s*=\s*(?:"([a-z0-9_]+)"|\'([a-z0-9_]+)\'|([a-z0-9_]+)(?=[\s\/>]))/i',
                $html,
                $matches,
            ) === 0
        ) {
            return [];
        }

        $ids = array_filter(array_merge($matches[1], $matches[2], $matches[3]));

        return array_values(array_unique(array_map('strtolower', $ids)));
    }

    private static function token(string $bindingId): string
    {
        return '<!-- upb:bindings:dynamic_data:' . $bindingId . ' -->';
    }

    private static function encodeJson(mixed $value, int $flags = 0): string
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value, $flags);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone section tests run without WordPress functions.
        return json_encode($value, $flags);
    }
}
