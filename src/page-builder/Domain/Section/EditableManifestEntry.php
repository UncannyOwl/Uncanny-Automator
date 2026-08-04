<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Immutable value object representing a single editable element in a section manifest.
 *
 * Each entry carries the element's identity (key, type, tag, path), optional
 * value fields (depending on type), and computed capabilities that downstream
 * consumers (TS toolbar, Python prompt builder) use to decide available actions.
 */
final class EditableManifestEntry
{
    /** @var string[] Editable types that support AI rewrite operations. */
    public const AI_REWRITABLE_TYPES = ['text', 'textarea', 'link'];

    private function __construct(
        private readonly string $key,
        private readonly string $type,
        private readonly string $tag,
        private readonly string $path,
        private readonly ?string $textValue,
        private readonly ?string $urlValue,
        private readonly ?string $srcValue,
        private readonly ?string $altValue,
        private readonly ?string $styleValue,
        private readonly bool $supportsInlineUpdate,
        private readonly bool $supportsAiRewrite,
    ) {}

    /**
     * Factory: build from the raw array returned by DomSectionManifestExtractor::extractEditableNode().
     *
     * @param array<string, mixed> $data
     */
    public static function fromExtracted(array $data): self
    {
        $type = (string) ($data['type'] ?? 'text');

        return new self(
            key: (string) ($data['key'] ?? ''),
            type: $type,
            tag: (string) ($data['tag'] ?? ''),
            path: (string) ($data['path'] ?? ''),
            textValue: isset($data['text_value']) ? (string) $data['text_value'] : null,
            urlValue: isset($data['url_value']) ? (string) $data['url_value'] : null,
            srcValue: isset($data['src_value']) ? (string) $data['src_value'] : null,
            altValue: isset($data['alt_value']) ? (string) $data['alt_value'] : null,
            styleValue: isset($data['style_value']) ? (string) $data['style_value'] : null,
            supportsInlineUpdate: true,
            supportsAiRewrite: in_array($type, self::AI_REWRITABLE_TYPES, true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [
            'key'  => $this->key,
            'type' => $this->type,
            'tag'  => $this->tag,
            'path' => $this->path,
        ];

        if ($this->textValue !== null) {
            $result['text_value'] = $this->textValue;
        }

        if ($this->urlValue !== null) {
            $result['url_value'] = $this->urlValue;
        }

        if ($this->srcValue !== null) {
            $result['src_value'] = $this->srcValue;
        }

        if ($this->altValue !== null) {
            $result['alt_value'] = $this->altValue;
        }

        if ($this->styleValue !== null) {
            $result['style_value'] = $this->styleValue;
        }

        $result['capabilities'] = [
            'supports_inline_update' => $this->supportsInlineUpdate,
            'supports_ai_rewrite'    => $this->supportsAiRewrite,
        ];

        return $result;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function tag(): string
    {
        return $this->tag;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function textValue(): ?string
    {
        return $this->textValue;
    }

    public function urlValue(): ?string
    {
        return $this->urlValue;
    }

    public function srcValue(): ?string
    {
        return $this->srcValue;
    }

    public function altValue(): ?string
    {
        return $this->altValue;
    }

    public function styleValue(): ?string
    {
        return $this->styleValue;
    }

    public function supportsInlineUpdate(): bool
    {
        return $this->supportsInlineUpdate;
    }

    public function supportsAiRewrite(): bool
    {
        return $this->supportsAiRewrite;
    }
}
