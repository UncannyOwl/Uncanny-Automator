<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionCreate;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Application\Canvas\WorkingCanvasUrlPortInterface;
use UncannyPageBuilder\Application\Controls\PageDetails;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;

/**
 * Owns create_section's stable line-oriented success and failure contracts.
 */
final class SectionCreateResponseFormatter
{
    public function __construct(
        private readonly ?PageDetailsPortInterface $pageDetails = null,
        private readonly ?WorkingCanvasUrlPortInterface $workingCanvasUrls = null,
    ) {}

    /**
     * @param array<string, mixed> $result
     */
    public function pageSuccess(int $pageId, array $result): \WP_REST_Response
    {
        $lines = [
            'TOOL: create_section',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . (string) ($result['section_id'] ?? 0),
            'POSITION: ' . (string) ($result['position'] ?? 0),
            'NAME: ' . (string) ($result['name'] ?? ''),
            'SOURCE_GENERATION: ' . (string) ($result['source_generation'] ?? 0),
            'PREVIEW: ' . $this->pagePreviewUrl($pageId, (string) ($result['preview'] ?? '')),
            '',
        ];
        $this->appendWarnings($lines, $result['warnings'] ?? []);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call read_page_context to confirm the updated page layout.';

        return AgentTextResponse::ok(\implode("\n", $lines));
    }

    /**
     * @param array<string, mixed> $result
     */
    public function globalPartSuccess(
        int $globalPartId,
        string $partType,
        array $result,
    ): \WP_REST_Response {
        $lines = [
            'TOOL: create_section',
            'RESULT: success',
            'KIND: global_part',
            'GLOBAL_PART_ID: ' . $globalPartId,
            'PART_TYPE: ' . $partType,
            'SECTION_ID: ' . (string) ($result['section_id'] ?? 0),
            'POSITION: ' . (string) ($result['position'] ?? 0),
            'NAME: ' . (string) ($result['name'] ?? ''),
            'SOURCE_GENERATION: ' . (string) ($result['source_generation'] ?? 0),
            'PREVIEW: ' . ($this->workingCanvasUrls?->previewUrl($globalPartId) ?? ''),
            '',
        ];
        $this->appendWarnings($lines, $result['warnings'] ?? []);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_part kind=global_part global_part_id=' . $globalPartId . ' include=source to confirm the saved reusable source.';

        return AgentTextResponse::ok(\implode("\n", $lines));
    }

    public function stale(
        string $toolName,
        StaleSourceGenerationException $exception,
    ): \WP_REST_Response {
        return $this->error($toolName, 409, 'stale_source_generation', [
            'SCOPE: ' . $exception->scope(),
            'DETAIL: Page Builder source changed while this write was running.',
            'NEXT STEP',
            'Call read_page_context or read_part again, then reapply the change to the current source.',
        ]);
    }

    public function globalPartWriteError(
        string $toolName,
        string $partType,
        int $postId,
        \RuntimeException $exception,
    ): \WP_REST_Response {
        if ($exception instanceof SourceTransactionsUnavailableException) {
            return $this->error($toolName, 500, 'source_transactions_unavailable', [
                'KIND: global_part',
                'PART_TYPE: ' . $partType,
                'POST_ID: ' . $postId,
                'DETAIL: ' . $exception->getMessage(),
                'RETRY_SAFETY: Nothing was saved by this operation.',
                'NEXT STEP',
                'Convert the named database table to InnoDB. Then call read_part include=source again and retry against the current source.',
            ]);
        }

        return $this->error($toolName, 500, 'global_part_write_failed', [
            'KIND: global_part',
            'PART_TYPE: ' . $partType,
            'POST_ID: ' . $postId,
            'DETAIL: The global part write did not complete cleanly: ' . $exception->getMessage(),
            'RETRY_SAFETY: The source may already have been saved. Do not retry blindly.',
            'NEXT STEP',
            'Call read_part kind=global_part include=source first. If the requested change is present, do not retry. If it is absent, resolve the server error before retrying.',
        ]);
    }

    /** @param list<string> $lines */
    public function error(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(\implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    /**
     * @param list<string> $lines
     * @param string[] $warnings
     */
    private function appendWarnings(array &$lines, array $warnings): void
    {
        $warnings = \array_values(\array_unique(\array_filter(
            \array_map(static fn (mixed $warning): string => \trim((string) $warning), $warnings),
        )));
        if ($warnings === []) {
            return;
        }

        $lines[] = 'WARNING';
        foreach ($warnings as $warning) {
            $lines[] = $warning;
        }
        $lines[] = '';
    }

    private function pagePreviewUrl(int $pageId, string $fallback = ''): string
    {
        $previewUrl = $this->workingPageDetails($pageId)?->previewUrl();

        return \is_string($previewUrl) && $previewUrl !== '' ? $previewUrl : $fallback;
    }

    private function workingPageDetails(int $pageId): ?PageDetails
    {
        return $this->pageDetails?->find($pageId);
    }
}
