<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;

/**
 * Advances shared source and refreshes working canvases after menu changes.
 *
 * A published artifact keeps the menu snapshot it captured. Advancing the
 * global generation makes the working draft visibly dirty and prevents an
 * older publication candidate from committing after WordPress changes a menu.
 */
final class WorkingCanvasMenuChangeListener
{
    public function __construct(
        private readonly GlobalSourceMutation $globalSource,
        private readonly WorkingCanvasRefreshScheduler $refreshScheduler,
    ) {}

    public function register(): void
    {
        add_action('wp_update_nav_menu', [$this, 'menuChanged'], 10, 1);
        add_action('wp_delete_nav_menu', [$this, 'menuChanged'], 10, 1);
    }

    public function menuChanged($menuId = null): void
    {
        if (WordPressPostId::fromMixed($menuId) === null) {
            return;
        }

        $this->globalSource->run(static fn(): mixed => null);
        $this->refreshScheduler->enqueueAll();
    }
}
