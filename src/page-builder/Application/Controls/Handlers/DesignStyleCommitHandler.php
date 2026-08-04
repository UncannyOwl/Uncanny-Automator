<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\DesignStyles\CommitsDesignStyles;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleBatchChange;
use UncannyPageBuilder\Application\DesignStyles\DesignStyleBatchCommitRequest;

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
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
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

        $result = $this->service->commitBatch($commitRequest);

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: $result->message(),
            data: $result->toArray(),
        );
    }
}
