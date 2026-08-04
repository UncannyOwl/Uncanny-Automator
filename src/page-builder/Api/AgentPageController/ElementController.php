<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Application\Editing\SectionNodeUpdateService;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiff;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Infrastructure\Section\DomSectionTargetInspector;

/**
 * Handles structural element lifecycle tools for Agent page edits.
 *
 * Every operation resolves one revision-bearing section collection and uses
 * that same snapshot for target validation and persistence. This preserves
 * the stale-write boundary while the root controller remains a REST shim.
 */
final class ElementController
{
    public function __construct(
        private readonly SectionWriteRequestResolver $sectionWrites,
        private readonly DomSectionTargetInspector $targets,
        private readonly SectionNodeUpdateService $nodeUpdates,
        private readonly CompactSourceDiffer $sourceDiffer,
    ) {}

    public function delete(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error, $loadedSections] = $this->sectionWrites->resolve($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) $section->id();
        $target = $request->get_param('target');

        $targetError = $this->structuralTargetError('delete_element', $sectionId, $target, 'target');
        if ($targetError instanceof \WP_REST_Response) {
            return $targetError;
        }

        $sourcePath = trim((string) $target['source_path']);
        $tag = strtolower(trim((string) $target['tag']));
        $textPreview = $this->targets->textForTarget($section, $sourcePath, $tag);
        if ($textPreview === null) {
            return $this->textToolError('delete_element', 422, 'target_not_found', [
                'SECTION_ID: ' . $sectionId,
                'TAG: ' . $tag,
                'SOURCE_PATH: ' . $sourcePath,
                'NEXT STEP',
                'Call read_part include=design_targets again. The source path or tag no longer matches stored HTML.',
            ]);
        }

        try {
            $result = $this->nodeUpdates->deleteElement($pageId, $sectionId, $target, $loadedSections);
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('delete_element', 422, 'invalid_element_delete', [
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Call read_part include=design_targets again and retry with a valid non-root, non-binding target.',
            ]);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionValidationException $exception) {
            return ApiResponse::validationError($exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('delete_element', $exception);
        }

        $lines = [
            'TOOL: delete_element',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            '',
            'DELETED',
            'TAG: ' . $tag,
            'SOURCE_PATH: ' . $sourcePath,
            'TEXT_PREVIEW: ' . $textPreview,
            '',
        ];
        $this->appendNodeDiffLines($lines, $result);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_part include=manifest,content_targets to verify the remaining structure.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function insert(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error, $loadedSections] = $this->sectionWrites->resolve($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) $section->id();
        $target = $request->get_param('target');
        $placement = strtolower(trim((string) ($request->get_param('placement') ?? '')));
        $html = $request->get_param('html');

        $targetError = $this->structuralTargetError('insert_element', $sectionId, $target, 'target');
        if ($targetError instanceof \WP_REST_Response) {
            return $targetError;
        }
        if (!is_string($html) || trim($html) === '') {
            return $this->textToolError('insert_element', 400, 'missing_html', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Retry with one safe root HTML element to insert.',
            ]);
        }

        try {
            $result = $this->nodeUpdates->insertElement(
                $pageId,
                $sectionId,
                $target,
                $placement,
                $html,
                $loadedSections,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('insert_element', 422, 'invalid_element_insert', [
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry with placement before, after, prepend, or append and one safe root element.',
            ]);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionValidationException $exception) {
            return ApiResponse::validationError($exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('insert_element', $exception);
        }

        $sourcePath = trim((string) $target['source_path']);
        $tag = strtolower(trim((string) $target['tag']));

        $lines = [
            'TOOL: insert_element',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            '',
            'INSERTED',
            'PLACEMENT: ' . $placement,
            'TARGET: ' . $tag . ' at ' . $sourcePath,
            'HTML: ' . trim($html),
            '',
        ];
        $this->appendNodeDiffLines($lines, $result);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_part include=content_targets if the inserted content needs follow-up editing.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function move(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error, $loadedSections] = $this->sectionWrites->resolve($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) $section->id();
        $source = $request->get_param('source');
        $destination = $request->get_param('destination');
        $placement = strtolower(trim((string) ($request->get_param('placement') ?? '')));

        $sourceError = $this->structuralTargetError('move_element', $sectionId, $source, 'source');
        if ($sourceError instanceof \WP_REST_Response) {
            return $sourceError;
        }
        $destinationError = $this->structuralTargetError('move_element', $sectionId, $destination, 'destination');
        if ($destinationError instanceof \WP_REST_Response) {
            return $destinationError;
        }

        try {
            $result = $this->nodeUpdates->moveElement(
                $pageId,
                $sectionId,
                $source,
                $destination,
                $placement,
                $loadedSections,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('move_element', 422, 'invalid_element_move', [
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Call read_part include=design_targets again and retry with valid source and destination targets.',
            ]);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionValidationException $exception) {
            return ApiResponse::validationError($exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('move_element', $exception);
        }

        $sourcePath = trim((string) $source['source_path']);
        $sourceTag = strtolower(trim((string) $source['tag']));
        $destinationPath = trim((string) $destination['source_path']);
        $destinationTag = strtolower(trim((string) $destination['tag']));

        $lines = [
            'TOOL: move_element',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            '',
            'MOVED',
            'SOURCE: ' . $sourceTag . ' at ' . $sourcePath,
            'DESTINATION: ' . $destinationTag . ' at ' . $destinationPath,
            'PLACEMENT: ' . $placement,
            '',
        ];
        $this->appendNodeDiffLines($lines, $result);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_part include=manifest to verify the new order.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    // ── Structural target and response contract ─────────────

    private function structuralTargetError(
        string $toolName,
        int $sectionId,
        mixed $target,
        string $field,
    ): ?\WP_REST_Response {
        if (
            !is_array($target)
            || trim((string) ($target['source_path'] ?? '')) === ''
            || trim((string) ($target['tag'] ?? '')) === ''
        ) {
            return $this->textToolError($toolName, 400, 'invalid_' . $field, [
                'SECTION_ID: ' . $sectionId,
                strtoupper($field) . ': missing source_path or tag',
                'NEXT STEP',
                'Call read_part include=design_targets and retry with a target SOURCE_PATH and TAG.',
            ]);
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $result
     */
    private function appendNodeDiffLines(array &$lines, array $result): void
    {
        $oldHtml = is_string($result['old_html'] ?? null) ? $result['old_html'] : '';
        $newHtml = is_string($result['new_html'] ?? null) ? $result['new_html'] : $oldHtml;
        $oldCss = is_string($result['old_css'] ?? null) ? $result['old_css'] : '';
        $newCss = is_string($result['new_css'] ?? null) ? $result['new_css'] : $oldCss;

        $this->appendDiffLines(
            $lines,
            'HTML DIFF',
            $this->sourceDiffer->diff('HTML DIFF', $oldHtml, $newHtml),
        );
        $this->appendDiffLines(
            $lines,
            'CSS DIFF',
            $this->sourceDiffer->diff('CSS DIFF', $oldCss, $newCss),
        );
    }

    /**
     * @param list<string> $lines
     */
    private function appendDiffLines(
        array &$lines,
        string $heading,
        CompactSourceDiff $diff,
    ): void {
        $lines[] = $heading;
        foreach (explode("\n", $diff->body()) as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     */
    private function textToolError(
        string $toolName,
        int $status,
        string $code,
        array $lines,
    ): \WP_REST_Response {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    private function staleSourceToolError(
        string $toolName,
        StaleSourceGenerationException $exception,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 409, 'stale_source_generation', [
            'SCOPE: ' . $exception->scope(),
            'DETAIL: Page Builder source changed while this write was running.',
            'NEXT STEP',
            'Call read_page_context or read_part again, then reapply the change to the current source.',
        ]);
    }
}
