<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Export\StaticPageExportService;

/**
 * Builds portable static artifacts for a Page Builder page.
 */
final class PageStaticExportHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly StaticPageExportService $exports,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        if ($request->pageId() <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        $export = $this->exports->buildForPage($request->pageId());

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Static export ready',
            data: [
                'export' => $export->toArray(),
            ],
        );
    }
}
