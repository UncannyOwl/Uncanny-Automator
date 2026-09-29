<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Api\AgentPageController\AgentWrite\AgentWritePageOwnerResolver;
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
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartCreationUncertainException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Reusable\Reusable;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
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
        private readonly PermissionChecker $permissions,
        private readonly SectionRepositoryInterface $sections,
        private readonly ?AgentWritePageOwnerResolver $pageOwners = null,
    ) {}

    public function manage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));

        try {
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
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError($exception);
        }
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
        } catch (GlobalPartCreationUncertainException $exception) {
            return $this->uncertainCreationError('reusable_create_failed', $exception);
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
        $sectionId = RequestId::positive($request->get_param('section_id')) ?? 0;
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
            $section = $this->pageOwners instanceof AgentWritePageOwnerResolver
                ? $this->pageOwners->reusableConversionSection($request)
                : $this->sections->findById($sectionId);
        } catch (SectionNotFoundException) {
            return $this->textToolError('manage_reusable', 404, 'section_not_found', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Refresh page context and retry with a valid section_id.',
            ]);
        }
        if ($section === null) {
            return $this->textToolError('manage_reusable', 404, 'section_not_found', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Refresh page context and retry with a valid section_id.',
            ]);
        }
        if (!$this->permissions->canEditPage($section->pageId())) {
            return $this->textToolError('manage_reusable', 403, 'reusable_edit_forbidden', [
                'SECTION_ID: ' . $sectionId,
                'PAGE_ID: ' . $section->pageId(),
                'NEXT STEP',
                'Ask a site administrator for permission to edit the source page.',
            ]);
        }

        try {
            $reusable = ($this->convertSectionToReusable)(new ConvertSectionToReusableCommand(
                section: $section,
                title: is_string($request->get_param('title')) ? (string) $request->get_param('title') : '',
                type: $type ?? GlobalPartType::Section,
            ));
        } catch (SectionNotFoundException) {
            return $this->textToolError('manage_reusable', 404, 'section_not_found', [
                'SECTION_ID: ' . $sectionId,
                'NEXT STEP',
                'Refresh page context and retry with a valid section_id.',
            ]);
        } catch (GlobalPartCreationUncertainException $exception) {
            return $this->uncertainCreationError('reusable_convert_failed', $exception, $sectionId);
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
            'COPY_OWNERSHIP: Reusable ' . $reusable->id() . ' is an independent copy. Editing it does not update source section ' . $sectionId . '.',
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
        if (!$this->permissions->canEditPost($reusableId)) {
            return $this->textToolError('manage_reusable', 403, 'reusable_edit_forbidden', [
                'REUSABLE_ID: ' . $reusableId,
                'NEXT STEP',
                'Ask a site administrator for permission to edit this reusable.',
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

            return $this->textToolError('manage_reusable', 500, 'write_failed', [
                'REUSABLE_ID: ' . $reusableId,
                'RETRY_SAFETY: An earlier persistence step may already have completed. Do not retry blindly.',
                'NEXT STEP',
                'Read the current reusable first. If the requested change is present, do not retry. If it is absent, retry once against the current state.',
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
        if (!$this->permissions->canDeletePost($reusableId)) {
            return $this->textToolError('manage_reusable', 403, 'reusable_edit_forbidden', [
                'REUSABLE_ID: ' . $reusableId,
                'NEXT STEP',
                'Ask a site administrator for permission to delete this reusable.',
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

            return $this->textToolError('manage_reusable', 500, 'write_failed', [
                'REUSABLE_ID: ' . $reusableId,
                'RETRY_SAFETY: An earlier persistence step may already have completed. Do not retry blindly.',
                'NEXT STEP',
                'Read the current reusable list first. If the reusable is already absent, do not retry. If it remains, inspect it before retrying once.',
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
        foreach (['reusable_id', 'global_part_id', 'canvas_id', 'page_id'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null) {
                return $this->globalPartId($value);
            }
        }

        $context = $request->get_param('page_builder_context');
        if (!is_array($context)) {
            return 0;
        }

        if (array_key_exists('global_part_id', $context)) {
            return $this->globalPartId($context['global_part_id']);
        }

        return array_key_exists('page_id', $context)
            ? $this->globalPartId($context['page_id'])
            : 0;
    }

    private function globalPartId(mixed $value): int
    {
        $id = RequestId::positive($value);

        return $id !== null && \get_post_type($id) === 'upb_global_part' ? $id : 0;
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

        $warnings = array_values(array_unique(array_filter(
            array_map(static fn (mixed $warning): string => trim((string) $warning), $reusable->warnings()),
        )));
        if ($warnings !== []) {
            $lines[] = 'WARNING';
            array_push($lines, ...$warnings);
        }

        return $lines;
    }

    private function uncertainCreationError(
        string $errorCode,
        GlobalPartCreationUncertainException $exception,
        ?int $sectionId = null,
    ): \WP_REST_Response {
        $lines = [];
        if ($sectionId !== null) {
            $lines[] = 'SECTION_ID: ' . $sectionId;
        }
        $lines[] = 'REUSABLE_ID: ' . $exception->globalPartId();
        $lines[] = 'DETAIL: The reusable may exist because its failed creation could not be cleaned up.';
        $lines[] = 'RETRY_SAFETY: Do not retry blindly. A retry can create a second reusable.';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call manage_reusable operation=list and look for REUSABLE_ID ' . $exception->globalPartId() . '. If it exists, inspect it before continuing. If it does not exist, resolve the cleanup failure before retrying.';

        return $this->textToolError('manage_reusable', 500, $errorCode, $lines);
    }

    /**
     * Broad use-case catches keep their product-specific errors, but integrity
     * failures still belong to the root Agent write boundary.
     */
    private function rethrowAgentWriteBoundaryFailure(\RuntimeException $exception): void
    {
        if (
            $exception instanceof CssRuleIntegrityException
            || $exception instanceof StaleSourceGenerationException
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

    private function staleSourceToolError(StaleSourceGenerationException $exception): \WP_REST_Response
    {
        return $this->textToolError('manage_reusable', 409, 'stale_source_generation', [
            'SCOPE: ' . $exception->scope(),
            'DETAIL: Page Builder source changed while this write was running.',
            'NEXT STEP',
            'Call read_page_context or read_part again, then reapply the change to the current source.',
        ]);
    }
}
