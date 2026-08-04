<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\DesignStyles\ElementStyleSheet;

final class SectionContent
{
    public function __construct(
        private readonly string $html,
        private readonly string $css,
        private readonly ?ElementStyleSheet $elementStyles = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            html: $data['html'] ?? '',
            css: $data['css'] ?? '',
            elementStyles: ElementStyleSheet::fromArray(is_array($data['element_styles'] ?? null)
                ? $data['element_styles']
                : []),
        );
    }

    /**
     * Build a source edit without treating omitted durable styles as a clear.
     *
     * New/imported sections use fromArray() and legitimately default to an
     * empty style sheet. Existing-source writers must preserve durable element
     * styles unless the caller explicitly supplies element_styles, including
     * an explicit empty array when clearing is intentional.
     */
    public static function fromSourceUpdate(array $data, self $current): self
    {
        $elementStyles = array_key_exists('element_styles', $data)
            ? ElementStyleSheet::fromArray(is_array($data['element_styles']) ? $data['element_styles'] : [])
            : $current->elementStyles();

        return new self(
            html: array_key_exists('html', $data) ? (string) $data['html'] : $current->html(),
            css: array_key_exists('css', $data) ? (string) $data['css'] : $current->css(),
            elementStyles: $elementStyles,
        );
    }

    public function html(): string { return $this->html; }
    public function css(): string  { return $this->css; }
    public function elementStyles(): ElementStyleSheet { return $this->elementStyles ?? ElementStyleSheet::empty(); }

    public function withHtml(string $html): self
    {
        return new self($html, $this->css, $this->elementStyles());
    }

    public function withCss(string $css): self
    {
        return new self($this->html, $css, $this->elementStyles());
    }

    public function withElementStyles(ElementStyleSheet $elementStyles): self
    {
        return new self($this->html, $this->css, $elementStyles);
    }

    public function toArray(): array
    {
        return [
            'html'           => $this->html,
            'css'            => $this->css,
            'element_styles' => $this->elementStyles()->toArray(),
        ];
    }
}
