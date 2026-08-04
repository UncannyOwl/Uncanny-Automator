<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

final class Section
{
    private ?int $id;
    private int $pageId;
    private int $position;
    private string $name;
    private SectionContent $content;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    private function __construct(
        ?int $id,
        int $pageId,
        int $position,
        string $name,
        SectionContent $content,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->id        = $id;
        $this->pageId    = $pageId;
        $this->position  = $position;
        $this->name      = $name;
        $this->content   = $content;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        int $pageId,
        int $position,
        string $name,
        SectionContent $content,
    ): self {
        return new self(null, $pageId, $position, $name, $content);
    }

    /**
     * Hydrate from a DB row (stdClass or assoc array).
     */
    public static function fromRow(object|array $row): self
    {
        $r = (object) $row;
        $elementStyles = \UncannyPageBuilder\Domain\DesignStyles\ElementStyleSheet::fromJson($r->element_styles ?? '');
        return new self(
            id:        (int) $r->id,
            pageId:    (int) $r->page_id,
            position:  (int) $r->position,
            name:      $r->name,
            content:   new SectionContent($r->html, $r->css, $elementStyles),
            createdAt: isset($r->created_at) ? new \DateTimeImmutable($r->created_at) : null,
            updatedAt: isset($r->updated_at) ? new \DateTimeImmutable($r->updated_at) : null,
        );
    }

    /**
     * Hydrate from a serialized array (e.g. API payloads).
     */
    public static function fromArray(array $data, int $pageId = 0, int $position = 0): self
    {
        return new self(
            id:       $data['id'] ?? null,
            pageId:   $pageId,
            position: $position,
            name:     $data['name'] ?? 'Untitled',
            content:  SectionContent::fromArray($data['content'] ?? []),
        );
    }

    /**
     * Hydrate persisted section data.
     */
    public static function fromStoredArray(array $data, int $pageId = 0, int $position = 0): self
    {
        return new self(
            id:       $data['id'] ?? null,
            pageId:   $pageId,
            position: $position,
            name:     $data['name'] ?? 'Untitled',
            content:  SectionContent::fromArray($data['content'] ?? []),
        );
    }

    // ── Accessors ──────────────────────────────

    public function id(): ?int                       { return $this->id; }
    public function pageId(): int                    { return $this->pageId; }
    public function position(): int                  { return $this->position; }
    public function name(): string                   { return $this->name; }
    public function content(): SectionContent        { return $this->content; }
    public function isNew(): bool { return $this->id === null; }

    // ── Mutators ───────────────────────────────

    public function assignId(int $id): void
    {
        $this->id = $id;
    }

    public function replaceContent(SectionContent $content): void
    {
        $this->content = $content;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    // ── Serialization ──────────────────────────

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'position' => $this->position,
            'name'     => $this->name,
            'content'  => $this->content->toArray(),
        ];
    }
}
