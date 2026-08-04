<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

/**
 * Returns the active page body to WordPress without deleting Page Builder work.
 */
final class ReturnPageToWordPressUseCase
{
    public function __construct(
        private readonly ReturnPageToWordPressTransitionInterface $transition,
    ) {}

    /**
     * @return bool True when ownership changed; false when WordPress already owns the page.
     */
    public function __invoke(int $pageId): bool
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('A positive page ID is required.');
        }

        return $this->transition->returnToWordPress($pageId);
    }
}
