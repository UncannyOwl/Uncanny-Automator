<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Reusable\Reusable;

interface ReusablePortInterface
{
    /**
     * @return list<Reusable>
     */
    public function list(?GlobalPartType $type = null): array;

    public function find(int $reusableId): ?Reusable;

    public function create(string $title, GlobalPartType $type): Reusable;

    public function convertSection(
        int $sectionId,
        string $title,
        GlobalPartType $type,
    ): Reusable;

    public function update(int $reusableId, ?string $title, ?GlobalPartType $type): Reusable;

    public function delete(int $reusableId, bool $forceDelete): DeleteReusableResult;
}
