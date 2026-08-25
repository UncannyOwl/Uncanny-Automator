<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\DesignStyles\CommitsDesignStyles;
use UncannyPageBuilder\Application\DesignStyles\CommitsDesignStylesWithinPageMutation;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleBatchChange;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleBatchCommitRequest;
use UncannyPageBuilder\Application\DesignStyles\WorkingDesignTokenCssRendererInterface;
use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;

/**
 * Control-plane entry point for committing design style changes.
 *
 * Commits the whole pending design stack from one Save click.
 *
 * The handler only parses the command payload into application data. Grouping,
 * ordering, validation, and persistence decisions live in the application
 * service.
 */
final class DesignStyleCommitHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly CommitsDesignStyles $service,
        private readonly WorkingDesignTokenCssRendererInterface $workingDesignTokenCss,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        return $this->invokeBatch($request, false);
    }

    /**
     * Commit page-owned design changes without rebuilding derived canvas state.
     *
     * The Manual change-set handler calls this method inside its page-source
     * transaction and refreshes the working canvas after that transaction
     * commits.
     */
    public function invokeWithinPageMutation(ControlInvokeRequest $request): ControlInvokeResult
    {
        return $this->invokeBatch($request, true);
    }

    private function invokeBatch(
        ControlInvokeRequest $request,
        bool $withinPageMutation,
    ): ControlInvokeResult {
        $payload = is_array($request->value()) ? $request->value() : $request->extra();

        $rawChanges = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        $changes = [];
        foreach ($rawChanges as $rawChange) {
            if (!is_array($rawChange)) {
                throw new \InvalidArgumentException('Each design change must be an object.');
            }

            $changes[] = DesignStyleBatchChange::fromArray($rawChange);
        }

        if ($changes === []) {
            throw new \InvalidArgumentException('At least one change is required.');
        }

        $commitRequest = new DesignStyleBatchCommitRequest(
            pageId: $request->pageId(),
            changes: $changes,
            capabilities: $request->context()->capabilities(),
        );

        if ($withinPageMutation) {
            if (!$this->service instanceof CommitsDesignStylesWithinPageMutation) {
                throw new \LogicException('The design style service cannot join a page-source mutation.');
            }

            $result = $this->service->commitBatchWithinPageMutation($commitRequest);
        } else {
            $result = $this->service->commitBatch($commitRequest);
        }

        if ($result->isSuccess() && $this->containsDesignTokenChanges($changes)) {
            $compiledCss = $this->workingDesignTokenCss->renderForEditor($request->pageId());
            if ($compiledCss !== null) {
                $result = $result->withDesignTokenCss($compiledCss);
            }
        }

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: $result->message(),
            data: $result->toArray(),
        );
    }

    /** @param DesignStyleBatchChange[] $changes */
    private function containsDesignTokenChanges(array $changes): bool
    {
        foreach ($changes as $change) {
            if ($change->scope() === DesignWriteScope::Page || $change->scope() === DesignWriteScope::Global) {
                return true;
            }
        }

        return false;
    }
}
