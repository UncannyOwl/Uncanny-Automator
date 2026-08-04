<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

/**
 * Application boundary for Page Builder-owned working page details.
 *
 * Implementations may read WordPress to seed a newly adopted page and to
 * calculate a permalink preview, but draft writes must never change public
 * WordPress fields.
 */
interface PageDetailsPortInterface
{
    public function find(int $pageId): ?PageDetails;

    public function initialize(int $pageId, int $updatedBy): PageDetails;

    public function update(int $pageId, string $title, string $slug, int $updatedBy): PageDetails;
}
