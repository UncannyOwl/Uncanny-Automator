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
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Infrastructure\Section\DomSectionTargetInspector;

/**
 * Handles Agent edits to text, link, and image content targets.
 *
 * These tools validate the exact target against the same revision-bearing
 * section collection that is later saved, preventing a concurrent human edit
 * from being silently overwritten.
 */
final class ContentTargetController
{
    public function __construct(
        private readonly SectionWriteRequestResolver $sectionWrites,
        private readonly DomSectionTargetInspector $targets,
        private readonly SectionNodeUpdateService $nodeUpdates,
        private readonly CompactSourceDiffer $sourceDiffer,
    ) {}

    public function updateText(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error, $loadedSections] = $this->sectionWrites->resolve($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) $section->id();
        $target = $request->get_param('target');
        $text = $request->get_param('text');
        $format = strtolower(trim((string) ($request->get_param('format') ?? 'plain')));

        if (
            !is_array($target)
            || trim((string) ($target['source_path'] ?? '')) === ''
            || trim((string) ($target['tag'] ?? '')) === ''
        ) {
            return $this->textToolError('update_text_target', 400, 'invalid_target', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Call read_part include=content_targets and retry with a target SOURCE_PATH and TAG.',
            ]);
        }
        if (!is_scalar($text)) {
            return $this->textToolError('update_text_target', 400, 'missing_text', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Retry with a text value. Use an empty string only when intentionally clearing text.',
            ]);
        }
        if (!in_array($format, ['plain', 'safe_html'], true)) {
            return $this->textToolError('update_text_target', 400, 'invalid_format', [
                'FORMAT: ' . $format,
                'NEXT STEP',
                'Retry with format plain or safe_html.',
            ]);
        }

        $sourcePath = trim((string) $target['source_path']);
        $tag = trim((string) $target['tag']);
        $oldText = $this->targets->textForTarget($section, $sourcePath, $tag);
        if ($oldText === null) {
            return $this->textToolError('update_text_target', 422, 'target_not_found', [
                'SECTION_ID: ' . $sectionId,
                'TAG: ' . $tag,
                'SOURCE_PATH: ' . $sourcePath,
                'NEXT STEP',
                'Call read_part include=content_targets again. The source path or tag no longer matches stored HTML.',
            ]);
        }

        $expectedText = trim((string) ($target['expected_text'] ?? ''));
        if ($expectedText !== '' && $this->normalizeText($expectedText) !== $this->normalizeText($oldText)) {
            return $this->textToolError('update_text_target', 409, 'target_text_mismatch', [
                'SECTION_ID: ' . $sectionId,
                'TAG: ' . $tag,
                'SOURCE_PATH: ' . $sourcePath,
                'EXPECTED_TEXT: ' . $expectedText,
                'CURRENT_TEXT: ' . $oldText,
                'NEXT STEP',
                'Call read_part include=content_targets again and retry only if the current target still matches the user request.',
            ]);
        }

        try {
            $result = $this->nodeUpdates->update(
                pageId: $pageId,
                sectionId: $sectionId,
                target: [
                    'source_path' => $sourcePath,
                    'tag' => $tag,
                    'selector' => is_scalar($target['selector'] ?? null) ? (string) $target['selector'] : null,
                    'identity' => is_scalar($target['identity'] ?? null) ? (string) $target['identity'] : null,
                ],
                changes: [[
                    'kind' => 'content',
                    'name' => $format === 'safe_html' ? 'safe_html' : 'text',
                    'path' => $sourcePath,
                    'value' => (string) $text,
                ]],
                loadedSections: $loadedSections,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('update_text_target', 422, 'invalid_text_target_update', [
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Call read_part include=content_targets again and retry with a valid target and supported text format.',
            ]);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionValidationException $exception) {
            return ApiResponse::validationError($exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError('update_text_target', $exception);
        }

        $lines = [
            'TOOL: update_text_target',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            '',
            'TARGET',
            'TAG: ' . $tag,
            'SOURCE_PATH: ' . $sourcePath,
            'OLD_TEXT: ' . $oldText,
            'NEW_TEXT: ' . $this->normalizeText((string) $text),
            '',
        ];
        $this->appendNodeDiffLines($lines, $result);
        $lines[] = 'NEXT STEP';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function updateLink(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error, $loadedSections] = $this->sectionWrites->resolve($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) $section->id();
        $target = $request->get_param('target');
        $href = $request->get_param('href');
        $text = $request->get_param('text');

        $targetError = $this->targetValidationError('update_link_target', $sectionId, $target, 'a');
        if ($targetError instanceof \WP_REST_Response) {
            return $targetError;
        }

        $sourcePath = trim((string) $target['source_path']);
        $tag = trim((string) $target['tag']);
        $currentHref = $this->targets->attributeForTarget($section, $sourcePath, $tag, 'href');
        $currentText = $this->targets->textForTarget($section, $sourcePath, $tag);
        if ($currentHref === null || $currentText === null) {
            return $this->textToolError('update_link_target', 422, 'target_not_found', [
                'SECTION_ID: ' . $sectionId,
                'TAG: ' . $tag,
                'SOURCE_PATH: ' . $sourcePath,
                'NEXT STEP',
                'Call read_part include=content_targets again. The source path or tag no longer matches stored HTML.',
            ]);
        }

        $expectedHref = trim((string) ($target['expected_href'] ?? ''));
        if ($expectedHref !== '' && $expectedHref !== $currentHref) {
            return $this->textToolError('update_link_target', 409, 'target_href_mismatch', [
                'SECTION_ID: ' . $sectionId,
                'TAG: ' . $tag,
                'SOURCE_PATH: ' . $sourcePath,
                'EXPECTED_HREF: ' . $expectedHref,
                'CURRENT_HREF: ' . $currentHref,
                'NEXT STEP',
                'Call read_part include=content_targets again and retry only if the current target still matches the user request.',
            ]);
        }

        $changes = [];
        $updated = [];
        if ($href !== null) {
            if (!is_scalar($href) || !$this->isSafeUrl((string) $href, true)) {
                return $this->textToolError('update_link_target', 422, 'unsafe_href', [
                    'HREF: ' . (is_scalar($href) ? (string) $href : ''),
                    'NEXT STEP',
                    'Retry with an http, https, mailto, tel, root-relative, or hash URL.',
                ]);
            }
            $changes[] = ['kind' => 'attribute', 'name' => 'href', 'value' => (string) $href];
            $updated['HREF'] = (string) $href;
        }
        if ($text !== null) {
            if (!is_scalar($text)) {
                return $this->textToolError('update_link_target', 400, 'invalid_text', [
                    'NEXT STEP',
                    'Retry with text as a string, or omit text for an href-only update.',
                ]);
            }
            $changes[] = [
                'kind' => 'content',
                'name' => 'text',
                'path' => $sourcePath,
                'value' => (string) $text,
            ];
            $updated['TEXT'] = $this->normalizeText((string) $text);
        }
        if ($changes === []) {
            return $this->textToolError('update_link_target', 400, 'missing_link_update', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Retry with href, text, or both.',
            ]);
        }

        $result = $this->runTargetNodeUpdate(
            'update_link_target',
            $pageId,
            $sectionId,
            $target,
            $changes,
            $loadedSections,
        );
        if ($result instanceof \WP_REST_Response || $result instanceof \WP_Error) {
            return $result;
        }

        $lines = [
            'TOOL: update_link_target',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            '',
            'TARGET',
            'TAG: a',
            'SOURCE_PATH: ' . $sourcePath,
            '',
            'UPDATED',
            ...$this->updatedLines($updated),
            '',
        ];
        $this->appendNodeDiffLines($lines, $result);
        $lines[] = 'NEXT STEP';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    public function updateImage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error, $loadedSections] = $this->sectionWrites->resolve($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) $section->id();
        $target = $request->get_param('target');

        $targetError = $this->targetValidationError('update_image_target', $sectionId, $target, 'img');
        if ($targetError instanceof \WP_REST_Response) {
            return $targetError;
        }

        $sourcePath = trim((string) $target['source_path']);
        $tag = trim((string) $target['tag']);
        $currentSrc = $this->targets->attributeForTarget($section, $sourcePath, $tag, 'src');
        if ($currentSrc === null) {
            return $this->textToolError('update_image_target', 422, 'target_not_found', [
                'SECTION_ID: ' . $sectionId,
                'TAG: ' . $tag,
                'SOURCE_PATH: ' . $sourcePath,
                'NEXT STEP',
                'Call read_part include=content_targets again. The source path or tag no longer matches stored HTML.',
            ]);
        }

        $expectedSrc = trim((string) ($target['expected_src'] ?? ''));
        if ($expectedSrc !== '' && $expectedSrc !== $currentSrc) {
            return $this->textToolError('update_image_target', 409, 'target_src_mismatch', [
                'SECTION_ID: ' . $sectionId,
                'TAG: ' . $tag,
                'SOURCE_PATH: ' . $sourcePath,
                'EXPECTED_SRC: ' . $expectedSrc,
                'CURRENT_SRC: ' . $currentSrc,
                'NEXT STEP',
                'Call read_part include=content_targets again and retry only if the current image still matches the user request.',
            ]);
        }

        $changes = [];
        $updated = [];
        foreach (['src', 'alt', 'loading', 'decoding', 'width', 'height'] as $attribute) {
            $value = $request->get_param($attribute);
            if ($value === null) {
                continue;
            }
            if (!is_scalar($value)) {
                return $this->textToolError('update_image_target', 400, 'invalid_image_attribute', [
                    'ATTRIBUTE: ' . strtoupper($attribute),
                    'NEXT STEP',
                    'Retry with scalar image attribute values.',
                ]);
            }

            $stringValue = trim((string) $value);
            if ($attribute === 'src' && !$this->isSafeUrl($stringValue, false)) {
                return $this->textToolError('update_image_target', 422, 'unsafe_src', [
                    'SRC: ' . $stringValue,
                    'NEXT STEP',
                    'Retry with an http, https, root-relative, or hash image URL.',
                ]);
            }
            if ($attribute === 'loading' && $stringValue !== '' && !in_array($stringValue, ['lazy', 'eager', 'auto'], true)) {
                return $this->textToolError('update_image_target', 422, 'invalid_loading', [
                    'LOADING: ' . $stringValue,
                    'NEXT STEP',
                    'Retry with loading lazy, eager, auto, or omit it.',
                ]);
            }
            if ($attribute === 'decoding' && $stringValue !== '' && !in_array($stringValue, ['async', 'sync', 'auto'], true)) {
                return $this->textToolError('update_image_target', 422, 'invalid_decoding', [
                    'DECODING: ' . $stringValue,
                    'NEXT STEP',
                    'Retry with decoding async, sync, auto, or omit it.',
                ]);
            }
            if (
                in_array($attribute, ['width', 'height'], true)
                && $stringValue !== ''
                && !ctype_digit($stringValue)
            ) {
                return $this->textToolError('update_image_target', 422, 'invalid_image_dimension', [
                    strtoupper($attribute) . ': ' . $stringValue,
                    'NEXT STEP',
                    'Retry with a positive integer dimension or omit it.',
                ]);
            }

            $changes[] = ['kind' => 'attribute', 'name' => $attribute, 'value' => $stringValue];
            $updated[strtoupper($attribute)] = $stringValue;
        }
        if ($changes === []) {
            return $this->textToolError('update_image_target', 400, 'missing_image_update', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Retry with src, alt, loading, decoding, width, or height.',
            ]);
        }

        $result = $this->runTargetNodeUpdate(
            'update_image_target',
            $pageId,
            $sectionId,
            $target,
            $changes,
            $loadedSections,
        );
        if ($result instanceof \WP_REST_Response || $result instanceof \WP_Error) {
            return $result;
        }

        $lines = [
            'TOOL: update_image_target',
            'RESULT: success',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            '',
            'TARGET',
            'TAG: img',
            'SOURCE_PATH: ' . $sourcePath,
            '',
            'UPDATED',
            ...$this->updatedLines($updated),
            '',
        ];
        $this->appendNodeDiffLines($lines, $result);
        $lines[] = 'NEXT STEP';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    // ── Target validation and mutation ──────────────────────

    private function targetValidationError(
        string $toolName,
        int $sectionId,
        mixed $target,
        string $expectedTag,
    ): ?\WP_REST_Response {
        if (
            !is_array($target)
            || trim((string) ($target['source_path'] ?? '')) === ''
            || trim((string) ($target['tag'] ?? '')) === ''
        ) {
            return $this->textToolError($toolName, 400, 'invalid_target', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Call read_part include=content_targets and retry with a target SOURCE_PATH and TAG.',
            ]);
        }

        $tag = strtolower(trim((string) $target['tag']));
        if ($tag !== $expectedTag) {
            return $this->textToolError($toolName, 422, 'wrong_target_type', [
                'SECTION_ID: ' . $sectionId,
                'EXPECTED_TAG: ' . $expectedTag,
                'ACTUAL_TAG: ' . $tag,
                'NEXT STEP',
                'Call read_part include=content_targets and retry with the correct target type.',
            ]);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $target
     * @param list<array<string, string>> $changes
     * @return array<string, mixed>|\WP_REST_Response|\WP_Error
     */
    private function runTargetNodeUpdate(
        string $toolName,
        int $pageId,
        int $sectionId,
        array $target,
        array $changes,
        SectionCollection $loadedSections,
    ): array|\WP_REST_Response|\WP_Error {
        try {
            return $this->nodeUpdates->update(
                pageId: $pageId,
                sectionId: $sectionId,
                target: [
                    'source_path' => trim((string) ($target['source_path'] ?? '')),
                    'tag' => trim((string) ($target['tag'] ?? '')),
                    'selector' => is_scalar($target['selector'] ?? null) ? (string) $target['selector'] : null,
                    'identity' => is_scalar($target['identity'] ?? null) ? (string) $target['identity'] : null,
                ],
                changes: $changes,
                loadedSections: $loadedSections,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError($toolName, 422, 'invalid_target_update', [
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Call read_part include=content_targets again and retry with a valid target.',
            ]);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionValidationException $exception) {
            return ApiResponse::validationError($exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError($toolName, $exception);
        }
    }

    private function normalizeText(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function isSafeUrl(string $url, bool $allowContactSchemes): bool
    {
        $trimmed = strtolower(trim($url));
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/')) {
            return true;
        }

        if (preg_match('/^https?:\/\//', $trimmed) === 1) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return $allowContactSchemes && preg_match('/^(mailto:|tel:)/', $trimmed) === 1;
    }

    /**
     * @param array<string, string> $updated
     * @return list<string>
     */
    private function updatedLines(array $updated): array
    {
        $lines = [];
        foreach ($updated as $label => $value) {
            $lines[] = $label . ': ' . $value;
        }

        return $lines;
    }

    // ── Agent response contract ──────────────────────────────

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
