<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Reusable\Reusable;
use UncannyPageBuilder\Domain\Section\Section;

interface ReusablePortInterface
{
    /**
     * @return list<Reusable>
     */
    public function list(?GlobalPartType $type = null): array;

    public function find(int $reusableId): ?Reusable;

    public function create(string $title, GlobalPartType $type): Reusable;

    public function convertSection(
        Section $section,
        string $title,
        GlobalPartType $type,
    ): Reusable;

    public function update(int $reusableId, ?string $title, ?GlobalPartType $type): Reusable;

    public function delete(int $reusableId, bool $forceDelete): DeleteReusableResult;
}
