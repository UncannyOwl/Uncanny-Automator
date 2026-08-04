<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveDownloadUrlInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveArtifactStoreInterface;
use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveService;
use UncannyPageBuilder\Application\SourcePackage\PageSourceExportException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\SourcePackage\SourcePackageValidationException;

/**
 * Builds a portable page archive before returning its download URL.
 */
final class PageSourceExportHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly PageSourceArchiveDownloadUrlInterface $downloads,
        private readonly PageSourceArchiveService $archives,
        private readonly PageSourceArchiveArtifactStoreInterface $artifacts,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        if ($request->pageId() <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        try {
            $artifact = $this->archives->exportPage($request->pageId());
        } catch (StaleSourceGenerationException $e) {
            throw $e;
        } catch (SourcePackageValidationException $e) {
            throw new PageSourceExportException($e->userMessage(), 422, $e);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[Uncanny Page Builder] Page source export failed for page %d: %s: %s',
                $request->pageId(),
                $e::class,
                $e->getMessage(),
            ));
            throw new PageSourceExportException(
                'The page export could not be created. Try again. Ask your site administrator for help if the problem continues.',
                500,
                $e,
            );
        }

        try {
            $token = $this->artifacts->store($request->pageId(), $artifact);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[Uncanny Page Builder] Page source export failed for page %d: %s: %s',
                $request->pageId(),
                $e::class,
                $e->getMessage(),
            ));
            throw new PageSourceExportException(
                'The page export was created but could not be prepared for download. Try again.',
                500,
                $e,
            );
        }

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Page export ready',
            data: [
                'download_url' => $this->downloads->forPage($request->pageId(), $token),
                'page_id' => $request->pageId(),
            ],
        );
    }
}
