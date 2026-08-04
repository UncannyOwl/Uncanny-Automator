<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

final class KsesSanitizer
{
    private int $builderWriteDepth = 0;

    /**
     * Temporarily extends WordPress' post allowlist for one Page Builder write.
     *
     * The filter must never remain registered globally: doing so changes
     * wp_kses_post() behavior for unrelated posts, comments, and plugins.
     *
     * @template T
     * @param callable(): T $write
     * @return T
     */
    public function runWithBuilderAllowlist(callable $write): mixed
    {
        $registerFilter = $this->builderWriteDepth === 0;
        $this->builderWriteDepth++;

        if ($registerFilter) {
            add_filter('wp_kses_allowed_html', [$this, 'extend'], 10, 2);
        }

        try {
            return $write();
        } finally {
            $this->builderWriteDepth--;
            if ($registerFilter) {
                remove_filter('wp_kses_allowed_html', [$this, 'extend'], 10);
            }
        }
    }

    /** @param array<string, array> $tags */
    public function extend(array $tags, string $context): array
    {
        if ($this->builderWriteDepth < 1 || $context !== 'post') {
            return $tags;
        }

        $safeAttrs = apply_filters('uncanny_page_builder_kses_allowlist', [
            'data-ai-editable' => true,
            'data-ai-type'     => true,
            'data-ai-dynamic'  => true,
            'data-ai-bind'     => true,
            'data-post-type'   => true,
            'data-menu-id'     => true,
            'data-menu-location' => true,
            'data-depth'       => true,
            'data-count'       => true,
            'data-orderby'     => true,
            'data-paginate'    => true,
            'data-role'        => true,
            'class'            => true,
            'id'               => true,
            'href'             => true,
            'src'              => true,
            'srcset'           => true,
            'alt'              => true,
            'role'             => true,
            'aria-label'       => true,
            'aria-hidden'      => true,
        ]);
        $trustedRuntimeAttrs = current_user_can('unfiltered_html')
            ? [
                'x-data'       => true,
                'x-show'       => true,
                'x-transition' => true,
                'x-text'       => true,
                'x-bind'       => true,
                'x-cloak'      => true,
                '@click'       => true,
                'style'        => true,
            ]
            : [];
        $aiAttrs = array_merge($safeAttrs, $trustedRuntimeAttrs);

        $elements = [
            'div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav',
            'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'a', 'img', 'button', 'figure', 'figcaption',
            'ul', 'ol', 'li',
            'svg', 'path', 'circle', 'rect', 'g',
            'video', 'source',
        ];

        foreach ($elements as $tag) {
            $tags[$tag] = array_merge($tags[$tag] ?? [], $aiAttrs);
        }

        if (current_user_can('unfiltered_html')) {
            $tags['style'] = ['type' => true, 'id' => true];
        } else {
            unset($tags['style']);
        }

        // Form elements for shortcode output (Contact Form 7, WooCommerce, etc.).
        $formAttrs = [
            'class' => true, 'id' => true,
            'action' => true, 'method' => true, 'enctype' => true,
            'name' => true, 'value' => true, 'type' => true,
            'placeholder' => true, 'required' => true, 'disabled' => true,
            'checked' => true, 'selected' => true, 'readonly' => true,
            'for' => true, 'rows' => true, 'cols' => true,
            'min' => true, 'max' => true, 'step' => true,
            'multiple' => true, 'maxlength' => true, 'minlength' => true,
            'pattern' => true, 'autocomplete' => true,
            'aria-label' => true, 'aria-required' => true, 'aria-invalid' => true,
            'role' => true, 'tabindex' => true,
        ];
        if (current_user_can('unfiltered_html')) {
            $formAttrs['style'] = true;
        }
        foreach (['form', 'input', 'select', 'textarea', 'option', 'optgroup', 'label', 'fieldset', 'legend'] as $formTag) {
            $tags[$formTag] = array_merge($tags[$formTag] ?? [], $formAttrs);
        }

        return $tags;
    }
}
