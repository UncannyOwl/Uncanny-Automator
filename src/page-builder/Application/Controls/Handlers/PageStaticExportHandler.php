<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Export\StaticPageExportService;

/**
 * Builds portable static artifacts for a Page Builder page.
 */
final class PageStaticExportHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly StaticPageExportService $exports,
        private readonly PageDetailsPortInterface $pageDetails,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        if ($request->pageId() <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        $details = $this->pageDetails->find($request->pageId());
        if ($details === null) {
            throw new \InvalidArgumentException('Page details are unavailable.');
        }

        $export = $this->exports->buildForPage(
            $request->pageId(),
            $details->title(),
            $details->permalink(),
        );

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Static export ready',
            data: [
                'export' => $export->toArray(),
            ],
        );
    }
}
