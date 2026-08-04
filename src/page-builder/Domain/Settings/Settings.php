<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * Sitewide settings aggregate root.
 */
final class Settings
{
    public function __construct(
        private readonly BrandStylesSettings $brandStyles,
        private readonly DesignDirectionSettings $designDirection,
        private readonly PageLayoutSettings $pageLayout,
        private readonly ContentTypeSettings $contentTypes,
        private readonly ToolSettings $tools,
    ) {}

    public static function defaults(): self
    {
        return new self(
            BrandStylesSettings::defaults(),
            DesignDirectionSettings::defaults(),
            PageLayoutSettings::defaults(),
            ContentTypeSettings::defaults(),
            ToolSettings::defaults(),
        );
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::defaults();
        }

        return new self(
            BrandStylesSettings::fromArray($data['brand_styles'] ?? null),
            DesignDirectionSettings::fromArray($data['design_direction'] ?? null),
            PageLayoutSettings::fromArray($data['page_layout'] ?? null),
            ContentTypeSettings::fromArray($data['content_types'] ?? null),
            ToolSettings::fromArray($data['tools'] ?? null),
        );
    }

    /**
     * @return array{
     *     brand_styles: array{
     *         logo: array{attachment_id: int},
     *         fonts: array{
     *             google: list<array{family: string, weights: string}>,
     *             custom: list<array{family: string, attachment_id: int, weight: string}>
     *         },
     *         text_styles: array{typography: array<string, mixed>, locked_keys: list<string>},
     *         colors: array{tokens: array<string, string>, locked_keys: list<string>},
     *         breakpoints: array<string, int>
     *     },
     *     design_direction: array{custom_instructions: string},
     *     page_layout: array{default_header_id: ?int, default_footer_id: ?int},
     *     content_types: array{enabled: list<string>},
     *     tools: array{
     *         custom_javascript: array{page: bool, global_part: bool},
     *         approved_libraries: array{anime: bool, swiper: bool}
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'brand_styles' => $this->brandStyles->toArray(),
            'design_direction' => $this->designDirection->toArray(),
            'page_layout' => $this->pageLayout->toArray(),
            'content_types' => $this->contentTypes->toArray(),
            'tools' => $this->tools->toArray(),
        ];
    }

    public function brandStyles(): BrandStylesSettings
    {
        return $this->brandStyles;
    }

    public function designDirection(): DesignDirectionSettings
    {
        return $this->designDirection;
    }

    public function pageLayout(): PageLayoutSettings
    {
        return $this->pageLayout;
    }

    public function contentTypes(): ContentTypeSettings
    {
        return $this->contentTypes;
    }

    public function tools(): ToolSettings
    {
        return $this->tools;
    }

    public function withBrandStyles(BrandStylesSettings $brandStyles): self
    {
        return new self($brandStyles, $this->designDirection, $this->pageLayout, $this->contentTypes, $this->tools);
    }

    public function withDesignDirection(DesignDirectionSettings $designDirection): self
    {
        return new self($this->brandStyles, $designDirection, $this->pageLayout, $this->contentTypes, $this->tools);
    }

    public function withPageLayout(PageLayoutSettings $pageLayout): self
    {
        return new self($this->brandStyles, $this->designDirection, $pageLayout, $this->contentTypes, $this->tools);
    }

    public function withContentTypes(ContentTypeSettings $contentTypes): self
    {
        return new self($this->brandStyles, $this->designDirection, $this->pageLayout, $contentTypes, $this->tools);
    }

    public function withTools(ToolSettings $tools): self
    {
        return new self($this->brandStyles, $this->designDirection, $this->pageLayout, $this->contentTypes, $tools);
    }
}
