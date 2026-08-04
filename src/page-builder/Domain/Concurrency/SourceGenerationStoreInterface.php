<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Concurrency;

/**
 * Serializes writes at the page/global source boundaries.
 *
 * Implementations must execute each callback while holding the corresponding
 * persistence lock. A stale expected generation must fail before the callback
 * runs, so an old full snapshot cannot overwrite or delete newer source.
 */
interface SourceGenerationStoreInterface
{
    public function pageGeneration(int $pageId): int;

    public function globalGeneration(): int;

    /**
     * @param callable(): mixed $write
     * @return mixed
     */
    public function commitPage(int $pageId, int $expectedGeneration, callable $write): mixed;

    /**
     * @param callable(): mixed $write
     * @return mixed
     */
    public function commitGlobal(int $expectedGeneration, callable $write): mixed;

    /**
     * Run a publication write only while both captured source generations are
     * still current.
     *
     * @param callable(): mixed $publish
     * @return mixed
     */
    public function publishIfCurrent(SourceGenerationSnapshot $snapshot, callable $publish): mixed;
}
