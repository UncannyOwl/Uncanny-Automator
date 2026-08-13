<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\AgentWrite;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Exception\ParkedDraftNotLoadedException;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PageLiveState;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;

/**
 * Wraps Agent source mutations with the human draft/publication boundary.
 */
final class AgentWriteGuard
{
    public function __construct(
        private readonly AgentWriteErrorMapper $errors,
        private readonly ?PageSourceMutation $pageSourceMutation = null,
        private readonly ?PageStateRepositoryInterface $pageStates = null,
        private readonly ?SelectEditorPageSource $editorPageSources = null,
        private readonly ?PageLiveStateReaderInterface $pageLiveState = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    /**
     * @param callable(\WP_REST_Request): (\WP_REST_Response|\WP_Error) $callback
     */
    public function guard(string $toolName, callable $callback): \Closure
    {
        return function (\WP_REST_Request $request) use ($toolName, $callback): \WP_REST_Response|\WP_Error {
            try {
                $blocked = $this->parkedDraftAgentWriteError($toolName, $request);
                if ($blocked instanceof \WP_REST_Response) {
                    return $blocked;
                }

                $invoke = fn(): \WP_REST_Response|\WP_Error => $callback($request);
                $pageId = $this->requestPageId($request);
                $response = $this->isDraftWriteRequest($toolName, $request)
                    && $pageId > 0
                    && $this->pageSourceMutation instanceof PageSourceMutation
                    && $this->pageStates instanceof PageStateRepositoryInterface
                        ? $this->pageSourceMutation->runAsAgentWrite(
                            $pageId,
                            $invoke,
                            fn(): mixed => $this->pageStates->saveDraftResumePolicy(
                                $pageId,
                                DraftResumePolicy::Active,
                            ),
                            fn() => $this->assertAgentDraftIsVisible($pageId),
                        )
                        : $invoke();
            } catch (ParkedDraftNotLoadedException) {
                return $this->parkedDraftAgentWriteResponse();
            } catch (CssRuleIntegrityException $exception) {
                return $this->errors->cssRuleIntegrity(
                    $toolName,
                    $this->errors->contextLines($request),
                    $exception,
                );
            } catch (\RuntimeException $exception) {
                $contextLines = $this->errors->contextLines($request);
                $verificationError = $this->errors->conservativeWordPressWriteVerification(
                    $toolName,
                    $contextLines,
                    $exception,
                );
                if ($verificationError instanceof \WP_REST_Response) {
                    return $verificationError;
                }

                $transactionError = $this->errors->conservativeSourceTransaction(
                    $toolName,
                    $contextLines,
                    $exception,
                );
                if ($transactionError instanceof \WP_REST_Response) {
                    return $transactionError;
                }

                // An unmapped failure must not escape the REST boundary as a
                // fatal. The write state is uncertain, so the response demands
                // a state read before any retry.
                $this->recordBoundaryFailure($toolName, $exception);

                return $this->uncertainWriteError($toolName, $contextLines);
            } catch (\Throwable $failure) {
                $this->recordBoundaryFailure($toolName, $failure);

                return $this->uncertainWriteError($toolName, $this->errors->contextLines($request));
            }

            try {
                return $this->describeDraftWriteBoundary($toolName, $request, $response);
            } catch (\Throwable $failure) {
                // The callback already returned success. Response decoration
                // is informative and cannot reclassify or retry that write.
                $this->recordBoundaryFailure($toolName . '.response', $failure);

                return $response;
            }
        };
    }

    private function recordBoundaryFailure(string $toolName, \Throwable $failure): void
    {
        try {
            $this->failureReporter?->report('agent write', 0, $toolName, $failure);
        } catch (\Throwable) {
            // A report failure cannot change the controlled Agent response.
        }
    }

    /** @param list<string> $contextLines */
    private function uncertainWriteError(string $toolName, array $contextLines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: write_failed',
            ...$contextLines,
            'RETRY_SAFETY: An earlier persistence step may already have completed. Do not retry blindly.',
            'NEXT STEP',
            'Call the matching read operation first: read_runtime for custom JavaScript, or read_part for page and global-part source. If the requested change is present, do not retry. If it is absent, read the current state again and retry once against it.',
        ]), 500);
    }

    public function describeDraftWriteBoundary(
        string $toolName,
        \WP_REST_Request $request,
        \WP_REST_Response|\WP_Error $response,
    ): \WP_REST_Response|\WP_Error {
        if (!$response instanceof \WP_REST_Response || $response->get_status() >= 400) {
            return $response;
        }

        $body = $response->get_data();
        if (!is_string($body) || !str_contains($body, 'RESULT: success')) {
            return $response;
        }

        if (!$this->isDraftWriteRequest($toolName, $request)) {
            return $response;
        }

        if (!$this->pageSourceMutation instanceof PageSourceMutation) {
            $this->markAgentDraftActive($request);
        }
        if (str_contains($body, 'LIVE_PAGE: unchanged')) {
            return $response;
        }

        $publicationState = $this->publicationStateAfterDraftWrite($request);
        $publicationLine = $publicationState !== null
            ? "\nPUBLICATION_STATE: {$publicationState}"
            : '';
        $body = preg_replace(
            '/^RESULT: success$/m',
            "RESULT: success\nWORKING_STATE: saved_not_live\nLIVE_PAGE: unchanged{$publicationLine}\nPUBLICATION: A human must Publish changes in the Manual editor.",
            $body,
            1,
        ) ?? $body;

        return AgentTextResponse::withStatus($body, $response->get_status());
    }

    private function parkedDraftAgentWriteError(string $toolName, \WP_REST_Request $request): ?\WP_REST_Response
    {
        if (
            !$this->isDraftWriteRequest($toolName, $request)
            || !$this->editorPageSources instanceof SelectEditorPageSource
        ) {
            return null;
        }

        $pageId = $this->requestPageId($request);
        if ($pageId <= 0 || !$this->editorPageSources->forPage($pageId)->shouldOfferParkedDraft()) {
            return null;
        }

        return $this->parkedDraftAgentWriteResponse();
    }

    private function assertAgentDraftIsVisible(int $pageId): void
    {
        if (
            $this->editorPageSources instanceof SelectEditorPageSource
            && $this->editorPageSources->forPage($pageId)->shouldOfferParkedDraft()
        ) {
            throw new ParkedDraftNotLoadedException();
        }
    }

    private function parkedDraftAgentWriteResponse(): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'RESULT: error',
            'ERROR_CODE: parked_draft_not_loaded',
            'MESSAGE: There is a draft newer than the currently published page.',
            'RECOVERY: Open the Manual editor and choose Yes to load the draft before asking Uncanny Agent to edit it.',
        ]), 409);
    }

    private function markAgentDraftActive(\WP_REST_Request $request): void
    {
        $pageId = $this->requestPageId($request);
        if ($pageId <= 0 || !$this->pageStates instanceof PageStateRepositoryInterface) {
            return;
        }

        try {
            $this->pageStates->saveDraftResumePolicy($pageId, DraftResumePolicy::Active);
        } catch (\Throwable) {
            // The source write succeeded; diagnostic metadata cannot invite a retry.
        }
    }

    private function isDraftWriteRequest(string $toolName, \WP_REST_Request $request): bool
    {
        $requestedOperation = $request->get_param('operation');
        $operation = is_string($requestedOperation) ? trim($requestedOperation) : '';

        return match ($toolName) {
            'create_section', 'edit_part', 'edit_runtime', 'manage_sections' => true,
            'manage_binding' => in_array($operation, ['update_query', 'update_template'], true),
            'manage_reusable' => $operation !== 'list',
            'manage_canvas' => in_array($operation, ['create', 'update', 'attach_reusable'], true),
            default => false,
        };
    }

    private function publicationStateAfterDraftWrite(\WP_REST_Request $request): ?string
    {
        $pageId = $this->requestPageId($request);
        if ($pageId <= 0 || !$this->pageLiveState instanceof PageLiveStateReaderInterface) {
            return null;
        }

        try {
            $state = $this->pageLiveState->forPage($pageId);
        } catch (\Throwable) {
            return null;
        }

        return match ($state) {
            PageLiveState::Draft => 'unpublished',
            PageLiveState::Live => 'published_clean',
            PageLiveState::ChangesNotLive => 'published_dirty',
        };
    }

    private function requestPageId(\WP_REST_Request $request): int
    {
        $pageId = RequestId::fromUrl($request, 'page_id') ?? 0;
        if ($pageId > 0) {
            return $pageId;
        }

        $context = $request->get_param('page_builder_context');

        return is_array($context) ? \absint($context['page_id'] ?? 0) : 0;
    }
}
