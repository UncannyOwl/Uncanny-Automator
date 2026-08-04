<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;

final class SectionCollection
{
    /** @var Section[] */
    private array $sections;

    private int $generation = 0;

    /** @param Section[] $sections */
    public function __construct(array $sections = [])
    {
        $this->sections = array_values($sections);
        usort($this->sections, fn(Section $a, Section $b) => $a->position() <=> $b->position());
    }

    public static function fromArray(array $raw, int $pageId = 0, int $generation = 0): self
    {
        $sections = [];
        foreach ($raw as $i => $item) {
            $sections[] = Section::fromArray($item, $pageId, $i);
        }

        return self::fromPersisted($sections, $generation);
    }

    /**
     * Rehydrate a collection together with the aggregate generation that was
     * observed when its rows were read.
     *
     * @param Section[] $sections
     */
    public static function fromPersisted(array $sections, int $generation): self
    {
        if ($generation < 0) {
            throw new \InvalidArgumentException('Section aggregate generation must not be negative.');
        }

        $collection = new self($sections);
        $collection->generation = $generation;

        return $collection;
    }

    public function append(Section $section): void
    {
        $section->moveTo(count($this->sections));
        $this->sections[] = $section;
    }

    /** @throws SectionNotFoundException */
    public function getById(int $id): Section
    {
        foreach ($this->sections as $section) {
            if ($section->id() === $id) {
                return $section;
            }
        }
        throw SectionNotFoundException::withId($id);
    }

    /** @throws SectionNotFoundException */
    public function removeById(int $id): void
    {
        foreach ($this->sections as $i => $section) {
            if ($section->id() === $id) {
                unset($this->sections[$i]);
                $this->sections = array_values($this->sections);
                return;
            }
        }
        throw SectionNotFoundException::withId($id);
    }

    /** @throws SectionNotFoundException */
    public function replaceById(int $id, Section $replacement): void
    {
        foreach ($this->sections as $i => $section) {
            if ($section->id() === $id) {
                $replacement->moveTo($section->position());
                $this->sections[$i] = $replacement;
                return;
            }
        }
        throw SectionNotFoundException::withId($id);
    }

    /**
     * Reorder sections to match the given ID sequence.
     *
     * Every persisted ID in this collection must appear exactly once in
     * $orderedIds. Missing, duplicate, or unknown IDs are rejected before
     * mutating the collection.
     *
     * @param int[] $orderedIds Desired section ID order, first to last.
     * @throws SectionNotFoundException If any ID in $orderedIds is not in this collection.
     */
    public function reorderByIds(array $orderedIds): void
    {
        if ($orderedIds === []) {
            throw new \InvalidArgumentException('section_ids must be a non-empty array.');
        }

        $normalizedIds = array_map('intval', $orderedIds);
        if (count($normalizedIds) !== count(array_unique($normalizedIds))) {
            throw new \InvalidArgumentException('section_ids must not contain duplicates.');
        }

        $indexed = [];
        foreach ($this->sections as $section) {
            $id = $section->id();
            if ($id === null || $id <= 0) {
                throw new \InvalidArgumentException('Cannot reorder unsaved sections.');
            }
            $indexed[$id] = $section;
        }

        foreach ($normalizedIds as $id) {
            if (!isset($indexed[$id])) {
                throw SectionNotFoundException::withId($id);
            }
        }

        $missingIds = array_diff(array_keys($indexed), $normalizedIds);
        if ($missingIds !== []) {
            throw new \InvalidArgumentException('section_ids must include every section on the page.');
        }

        $reordered = [];
        foreach ($normalizedIds as $id) {
            $reordered[] = $indexed[$id];
        }

        $this->sections = array_values($reordered);

        // Assign sequential positions matching the new order.
        foreach ($this->sections as $i => $section) {
            $section->moveTo($i);
        }
    }

    /**
     * Normalize positions to sequential 0..n-1.
     */
    public function reindex(): void
    {
        usort($this->sections, fn(Section $a, Section $b) => $a->position() <=> $b->position());
        foreach ($this->sections as $i => $section) {
            $section->moveTo($i);
        }
    }

    public function count(): int { return count($this->sections); }

    /** @return Section[] */
    public function all(): array { return $this->sections; }

    public function generation(): int
    {
        return $this->generation;
    }

    public function markPersistedAtGeneration(int $generation): void
    {
        if ($generation < $this->generation) {
            throw new \InvalidArgumentException('Persisted generation must not move backwards.');
        }

        $this->generation = $generation;
    }

    public function toArray(): array
    {
        return array_map(fn(Section $s) => $s->toArray(), $this->sections);
    }
}
