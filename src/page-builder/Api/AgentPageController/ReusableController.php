<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Application\Reusable\CreateReusableCommand;
use UncannyPageBuilder\Application\Reusable\CreateReusableUseCase;
use UncannyPageBuilder\Application\Reusable\ConvertSectionToReusableCommand;
use UncannyPageBuilder\Application\Reusable\ConvertSectionToReusableUseCase;
use UncannyPageBuilder\Application\Reusable\DeleteReusableCommand;
use UncannyPageBuilder\Application\Reusable\DeleteReusableUseCase;
use UncannyPageBuilder\Application\Reusable\ListReusableQuery;
use UncannyPageBuilder\Application\Reusable\ListReusableUseCase;
use UncannyPageBuilder\Application\Reusable\UpdateReusableCommand;
use UncannyPageBuilder\Application\Reusable\UpdateReusableUseCase;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Exception\ReusableNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Reusable\Reusable;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;
use UncannyPageBuilder\Infrastructure\Persistence\WordPressWriteVerificationException;

/**
 * Handles the Agent-facing reusable section lifecycle.
 *
 * The root controller keeps the stable REST callback. This collaborator owns
 * reusable request parsing, use-case dispatch, and the line-oriented response
 * contract without taking Canvas attachment behavior.
 */
final class ReusableController
{
    public function __construct(
        private readonly CreateReusableUseCase $createReusable,
        private readonly ConvertSectionToReusableUseCase $convertSectionToReusable,
        private readonly UpdateReusableUseCase $updateReusable,
        private readonly DeleteReusableUseCase $deleteReusable,
        private readonly ListReusableUseCase $listReusable,
    ) {}

    public function manage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));

        return match ($operation) {
            'list' => $this->listFromRequest($request),
            'create' => $this->createFromRequest($request),
            'convert' => $this->convertSectionFromRequest($request),
            'update' => $this->updateFromRequest($request),
            'delete' => $this->deleteFromRequest($request),
            default => $this->textToolError('manage_reusable', 400, 'invalid_operation', [
                'OPERATION: ' . ($operation !== '' ? $operation : 'missing'),
                'NEXT STEP',
                'Retry with operation list, create, convert, update, or delete.',
            ]),
        };
    }

    // ---------------------------------------------------------------------
    // Reusable operations
    // ---------------------------------------------------------------------

    private function createFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $type = $this->requestedType($request);
        if ($type === false) {
            return $this->textToolError('manage_reusable', 400, 'invalid_reusable_type', [
                'NEXT STEP',
                'Retry with reusable_type header, footer, or section.',
            ]);
        }

        try {
            $reusable = ($this->createReusable)(new CreateReusableCommand(
                title: is_string($request->get_param('title')) ? (string) $request->get_param('title') : '',
                type: $type ?? GlobalPartType::Section,
            ));
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_reusable', 500, 'reusable_create_failed', [
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_reusable',
            'RESULT: success',
            'OPERATION: create',
            ...$this->summaryLines($reusable),
            '',
            'NEXT STEP',
            $reusable->hasSource()
                ? 'Use edit_part kind=global_part to keep editing this reusable.'
                : 'Use create_section once on this reusable canvas to bootstrap source content.',
        ]));
    }

    private function listFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $type = $this->requestedType($request);
        if ($type === false) {
            return $this->textToolError('manage_reusable', 400, 'invalid_reusable_type', [
                'NEXT STEP',
                'Retry with reusable_type header, footer, or section, or omit it to list all.',
            ]);
        }

        $reusables = ($this->listReusable)(new ListReusableQuery($type));
        $lines = [
            'TOOL: manage_reusable',
            'RESULT: success',
            'OPERATION: list',
            'COUNT: ' . count($reusables),
        ];

        foreach ($reusables as $index => $reusable) {
            $lines[] = '';
            $lines[] = 'ITEM ' . ($index + 1);
            array_push($lines, ...$this->summaryLines($reusable));
        }

        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = $reusables === []
            ? 'Create a reusable with manage_reusable operation=create.'
            : 'Pick a REUSABLE_ID from the list. Use create_section once when HAS_SOURCE is no; otherwise use manage_reusable update/delete or read_part kind=global_part.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function convertSectionFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $sectionId = absint($request->get_param('section_id'));
        $type = $this->requestedType($request);

        if ($sectionId <= 0) {
            return $this->textToolError('manage_reusable', 400, 'missing_section_id', [
                'NEXT STEP',
                'Retry with section_id from the page context.',
            ]);
        }

        if ($type === false) {
            return $this->textToolError('manage_reusable', 400, 'invalid_reusable_type', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Retry with reusable_type header, footer, or section.',
            ]);
        }

        try {
            $reusable = ($this->convertSectionToReusable)(new ConvertSectionToReusableCommand(
                sectionId: $sectionId,
                title: is_string($request->get_param('title')) ? (string) $request->get_param('title') : '',
                type: $type ?? GlobalPartType::Section,
            ));
        } catch (SectionNotFoundException) {
            return $this->textToolError('manage_reusable', 404, 'section_not_found', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Refresh page context and retry with a valid section_id.',
            ]);
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_reusable', 500, 'reusable_convert_failed', [
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_reusable',
            'RESULT: success',
            'OPERATION: convert',
            'SECTION_ID: ' . $sectionId,
            ...$this->summaryLines($reusable),
            '',
            'NEXT STEP',
            'Use read_part kind=global_part include=source to confirm the reusable source before further edits.',
        ]));
    }

    private function updateFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $reusableId = $this->requestedId($request);
        if ($reusableId <= 0) {
            return $this->textToolError('manage_reusable', 400, 'missing_reusable_id', [
                'NEXT STEP',
                'Retry with reusable_id, or run this from an active reusable canvas.',
            ]);
        }

        $type = $this->requestedType($request);
        if ($type === false) {
            return $this->textToolError('manage_reusable', 400, 'invalid_reusable_type', [
                'REUSABLE_ID: ' . $reusableId,
                'NEXT STEP',
                'Retry with reusable_type header, footer, or section.',
            ]);
        }

        try {
            $reusable = ($this->updateReusable)(new UpdateReusableCommand(
                reusableId: $reusableId,
                title: is_string($request->get_param('title')) ? (string) $request->get_param('title') : null,
                type: $type,
            ));
        } catch (ReusableNotFoundException) {
            return $this->textToolError('manage_reusable', 404, 'reusable_not_found', [
                'REUSABLE_ID: ' . $reusableId,
                'NEXT STEP',
                'Refresh context and retry with a valid reusable_id.',
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError('manage_reusable', 400, 'invalid_reusable_update', [
                'REUSABLE_ID: ' . $reusableId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Adjust the requested properties and retry.',
            ]);
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_reusable', 500, 'reusable_update_failed', [
                'REUSABLE_ID: ' . $reusableId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_reusable',
            'RESULT: success',
            'OPERATION: update',
            ...$this->summaryLines($reusable),
            '',
            'NEXT STEP',
            $reusable->hasSource()
                ? 'Use edit_part kind=global_part to keep editing this reusable source.'
                : 'This reusable is still blank. Use create_section once to bootstrap source content.',
        ]));
    }

    private function deleteFromRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $reusableId = $this->requestedId($request);
        if ($reusableId <= 0) {
            return $this->textToolError('manage_reusable', 400, 'missing_reusable_id', [
                'NEXT STEP',
                'Retry with reusable_id, or run this from an active reusable canvas.',
            ]);
        }

        $deleteMode = trim((string) ($request->get_param('delete_mode') ?? 'trash'));
        if (!in_array($deleteMode, ['trash', 'delete'], true)) {
            return $this->textToolError('manage_reusable', 400, 'invalid_delete_mode', [
                'REUSABLE_ID: ' . $reusableId,
                'DELETE_MODE: ' . ($deleteMode !== '' ? $deleteMode : 'missing'),
                'NEXT STEP',
                'Retry with delete_mode trash or delete.',
            ]);
        }

        try {
            $result = ($this->deleteReusable)(new DeleteReusableCommand(
                reusableId: $reusableId,
                forceDelete: $deleteMode === 'delete',
            ));
        } catch (ReusableNotFoundException) {
            return $this->textToolError('manage_reusable', 404, 'reusable_not_found', [
                'REUSABLE_ID: ' . $reusableId,
                'NEXT STEP',
                'Refresh context and retry with a valid reusable_id.',
            ]);
        } catch (\RuntimeException $exception) {
            $this->rethrowAgentWriteBoundaryFailure($exception);

            return $this->textToolError('manage_reusable', 500, 'reusable_delete_failed', [
                'REUSABLE_ID: ' . $reusableId,
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Retry once. If it still fails, inspect the server error log.',
            ]);
        }

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_reusable',
            'RESULT: success',
            'OPERATION: delete',
            'REUSABLE_ID: ' . $result->reusable()->id(),
            'TITLE: ' . $result->reusable()->title(),
            'REUSABLE_TYPE: ' . $result->reusable()->type()->value,
            'DELETE_MODE: ' . ($result->forceDeleted() ? 'delete' : 'trash'),
            '',
            'NEXT STEP',
            'Refresh the reusable list or open another reusable before continuing.',
        ]));
    }

    // ---------------------------------------------------------------------
    // Request parsing and response formatting
    // ---------------------------------------------------------------------

    private function requestedId(\WP_REST_Request $request): int
    {
        $reusableId = absint($request->get_param('reusable_id'));
        if ($reusableId > 0) {
            return \get_post_type($reusableId) === 'upb_global_part' ? $reusableId : 0;
        }

        $globalPartId = $this->requestGlobalPartId($request);
        if ($globalPartId > 0) {
            return \get_post_type($globalPartId) === 'upb_global_part' ? $globalPartId : 0;
        }

        $canvasId = absint($request->get_param('canvas_id'));
        if ($canvasId > 0) {
            return \get_post_type($canvasId) === 'upb_global_part' ? $canvasId : 0;
        }

        $pageId = absint($request->get_param('page_id'));
        if ($pageId > 0) {
            return \get_post_type($pageId) === 'upb_global_part' ? $pageId : 0;
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context)) {
            return 0;
        }

        $contextGlobalPartId = absint($context['global_part_id'] ?? 0);
        if ($contextGlobalPartId > 0) {
            return \get_post_type($contextGlobalPartId) === 'upb_global_part' ? $contextGlobalPartId : 0;
        }

        $contextPageId = absint($context['page_id'] ?? 0);

        return $contextPageId > 0 && \get_post_type($contextPageId) === 'upb_global_part'
            ? $contextPageId
            : 0;
    }

    private function requestGlobalPartId(\WP_REST_Request $request): int
    {
        $requestId = absint($request->get_param('global_part_id'));
        if ($requestId > 0) {
            return $requestId;
        }

        $context = $request->get_param('page_builder_context');
        if (is_array($context)) {
            return absint($context['global_part_id'] ?? 0);
        }

        return 0;
    }

    private function requestedType(\WP_REST_Request $request): GlobalPartType|false|null
    {
        $typeValue = $request->get_param('reusable_type');
        if (!is_string($typeValue) || trim($typeValue) === '') {
            $typeValue = $request->get_param('type');
        }
        if (!is_string($typeValue) || trim($typeValue) === '') {
            $typeValue = $request->get_param('global_part_type');
        }
        if (!is_string($typeValue) || trim($typeValue) === '') {
            return null;
        }

        $typeValue = trim($typeValue);

        return in_array($typeValue, GlobalPartType::validValues(), true)
            ? GlobalPartType::fromString($typeValue)
            : false;
    }

    /**
     * @return list<string>
     */
    private function summaryLines(Reusable $reusable): array
    {
        $lines = [
            'REUSABLE_ID: ' . $reusable->id(),
            'TITLE: ' . $reusable->title(),
            'REUSABLE_TYPE: ' . $reusable->type()->value,
            'STATUS: ' . $reusable->status(),
            'EDITOR_URL: ' . $reusable->editorUrl(),
            'HAS_SOURCE: ' . ($reusable->hasSource() ? 'yes' : 'no'),
        ];

        if ($reusable->sourceSectionId() !== null) {
            $lines[] = 'SOURCE_SECTION_ID: ' . $reusable->sourceSectionId();
        }

        return $lines;
    }

    /**
     * Broad use-case catches keep their product-specific errors, but integrity
     * failures still belong to the root Agent write boundary.
     */
    private function rethrowAgentWriteBoundaryFailure(\RuntimeException $exception): void
    {
        if (
            $exception instanceof CssRuleIntegrityException
            || $this->wordpressWriteVerificationFailureInChain($exception) instanceof WordPressWriteVerificationException
            || $this->sourceTransactionFailureInChain($exception) instanceof SourceTransactionsUnavailableException
        ) {
            throw $exception;
        }
    }

    private function wordpressWriteVerificationFailureInChain(
        \Throwable $exception,
    ): ?WordPressWriteVerificationException {
        for ($current = $exception; $current instanceof \Throwable; $current = $current->getPrevious()) {
            if ($current instanceof WordPressWriteVerificationException) {
                return $current;
            }
        }

        return null;
    }

    private function sourceTransactionFailureInChain(
        \Throwable $exception,
    ): ?SourceTransactionsUnavailableException {
        for ($current = $exception; $current instanceof \Throwable; $current = $current->getPrevious()) {
            if ($current instanceof SourceTransactionsUnavailableException) {
                return $current;
            }
        }

        return null;
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
}
