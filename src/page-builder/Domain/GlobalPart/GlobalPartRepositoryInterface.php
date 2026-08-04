<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\GlobalPart;

use UncannyPageBuilder\Domain\Compiler\CompiledOutput;
use UncannyPageBuilder\Domain\Section\SectionCollection;

interface GlobalPartRepositoryInterface
{
    /** Insert the CPT post + type meta. Returns new post ID. */
    public function createPost(string $title, GlobalPartType $type): int;

    /** Persist section rows for a Global Part. */
    public function saveSections(int $globalPartId, SectionCollection $sections): void;

    /** Persist compiled CSS + post_content. */
    public function saveCompiled(int $globalPartId, CompiledOutput $compiled): void;

    /**
     * Find the first published Global Part of the given type.
     * Returns [post_id, sections, css] or null if none found.
     */
    public function findByType(GlobalPartType $type): ?array;

    /**
     * Find a published Global Part by post ID.
     * Returns [post_id, type, title, sections, css] or null if none found.
     * Each section item should include the persisted global section row `id`
     * when available so downstream manifest inspection can preserve identity.
     */
    public function findById(int $postId): ?array;

    /**
     * Find all published Global Parts of the given type.
     *
     * @return array<int, array{post_id: int, type: string, title: string, sections: array, css: string}>
     */
    public function findAllByType(GlobalPartType $type): array;
}
