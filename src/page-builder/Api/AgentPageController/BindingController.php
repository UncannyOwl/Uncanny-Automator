<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Api\PermissionChecker;
use UncannyPageBuilder\Api\RequestId;
use UncannyPageBuilder\Application\Controls\PageDetails;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Binding\BindingRegistry;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiff;
use UncannyPageBuilder\Domain\Editing\CompactSourceDiffer;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Domain\Exception\PageNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Exception\SectionValidationException;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Section\BindingSchema;
use UncannyPageBuilder\Domain\Section\HtmlCssProcessor;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;
use UncannyPageBuilder\Infrastructure\Section\DomSectionBindingContractInspector;
use UncannyPageBuilder\Infrastructure\Section\DynamicRegionToken;

/**
 * Handles Agent inspection and mutation of dynamic binding declarations.
 *
 * Binding updates preserve the existing two-read stale-source guard: the
 * contract is inspected from the requested section snapshot, then that exact
 * snapshot is compared with the revision-bearing collection used to save.
 */
final class BindingController
{
    /** @var list<string>|null Cached per request because the registry scan is not free. */
    private ?array $maskableBindingIds = null;

    public function __construct(
        private readonly SectionService $sectionService,
        private readonly DatabaseSectionRepository $sections,
        private readonly PermissionChecker $permissions,
        private readonly DomSectionBindingContractInspector $bindingInspector,
        private readonly HtmlCssProcessor $htmlCssProcessor,
        private readonly CompactSourceDiffer $sourceDiffer,
        private readonly ?BindingRegistry $bindingRegistry = null,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
    ) {}

    public function read(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $sectionId = RequestId::positive($request->get_param('section_id'));
        $pageIdValue = $request->get_param('page_id');
        $requestedPageId = $pageIdValue === null ? 0 : RequestId::positive($pageIdValue);
        if ($sectionId === null || $requestedPageId === null) {
            return ApiResponse::error(ErrorMessage::InvalidRouteId);
        }

        if ($requestedPageId !== 0) {
            if (!$this->permissions->canEditPage($requestedPageId)) {
                return ApiResponse::error(ErrorMessage::PageEditForbidden);
            }
            if (!$this->sectionService->isPageOwned($requestedPageId)) {
                return ApiResponse::error(ErrorMessage::PageNotOwned);
            }
        }

        try {
            $section = $this->sections->findById($sectionId);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(
                $requestedPageId === 0
                    ? ErrorMessage::SectionNotFound
                    : ErrorMessage::SectionNotFoundOnPage,
            );
        }

        $pageId = $section->pageId();
        if ($requestedPageId !== 0 && $requestedPageId !== $pageId) {
            return ApiResponse::error(ErrorMessage::SectionNotFoundOnPage);
        }
        if ($requestedPageId === 0) {
            if (!$this->permissions->canEditPage($pageId)) {
                return ApiResponse::error(ErrorMessage::SectionNotFound);
            }
            if (!$this->sectionService->isPageOwned($pageId)) {
                return ApiResponse::error(ErrorMessage::SectionNotFound);
            }
        }

        $contracts = $this->bindingInspector->inspect($section);
        $bindings = array_map(static fn ($contract) => $contract->toArray(), $contracts);

        $bindingId = $request->get_param('binding_id');
        if ($bindingId !== null && $bindingId !== '') {
            $bindings = array_values(array_filter(
                $bindings,
                static fn ($binding) => $binding['binding_id'] === $bindingId,
            ));
            if ($bindings === []) {
                return ApiResponse::error(ErrorMessage::AgentBindingNotFound);
            }
        }

        $lines = [
            'TOOL: manage_binding',
            'RESULT: success',
            'OPERATION: inspect',
            'SECTION_ID: ' . $sectionId,
            '',
            'BINDINGS',
        ];
        if ($bindings === []) {
            $lines[] = 'none';
        }
        foreach ($bindings as $binding) {
            $lines[] = '- BINDING_ID: ' . (string) ($binding['binding_id'] ?? '');
            $lines[] = '  SOURCE: ' . (string) ($binding['source'] ?? '');
            $lines[] = '  PATH: ' . (string) ($binding['path'] ?? '');
            $lines[] = '  CONTRACT_HASH: ' . (string) ($binding['contract_hash'] ?? '');
            $lines[] = '  QUERY_ATTRIBUTES: ' . (\json_encode($binding['query_attributes'] ?? [], JSON_UNESCAPED_SLASHES) ?: '{}');
            $lines[] = '  BIND_KEYS: ' . \implode(', ', \array_map('strval', (array) ($binding['bind_keys'] ?? [])));
            $lines[] = '  TEMPLATE_HTML:';
            $lines[] = (string) ($binding['template_html'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = $bindings === []
            ? 'Use manage_binding operation=search and operation=guide before adding a dynamic binding.'
            : 'Use manage_binding operation=update_query or operation=update_template with a BINDING_ID copied exactly from this response.';

        return AgentTextResponse::ok(\implode("\n", $lines));
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        [$section, $pageId, $error] = $this->resolveSection($request);
        if ($error) {
            return $error;
        }

        $sectionId = (int) ($section->id() ?? 0);
        $bindingId = (string) ($request->get_param('binding_id') ?? '');
        $changeType = (string) ($request->get_param('change_type') ?? '');

        if ($bindingId === '') {
            return ApiResponse::error(ErrorMessage::AgentMissingBindingId);
        }
        if (!\in_array($changeType, ['query', 'template'], true)) {
            return ApiResponse::error(ErrorMessage::AgentInvalidChangeType);
        }

        // The declaration defines both the editable region and allowed query keys.
        $target = null;
        foreach ($this->bindingInspector->inspect($section) as $contract) {
            if ($contract->bindingId() === $bindingId) {
                $target = $contract;
                break;
            }
        }
        if ($target === null) {
            return ApiResponse::error(ErrorMessage::AgentBindingNotFound);
        }

        $queryArgs = [];
        if ($changeType === 'query') {
            $queryArgs = $request->get_param('query_args');
            if (!\is_array($queryArgs) || $queryArgs === []) {
                return ApiResponse::error(ErrorMessage::AgentMissingQueryArgs);
            }
            $queryArgs = $this->canonicalBindingQueryArgs($target->source(), $queryArgs);
            if ($queryArgs instanceof \WP_Error) {
                return $queryArgs;
            }
        } else {
            $templateHtml = (string) ($request->get_param('template_html') ?? '');
            if ($templateHtml === '') {
                return ApiResponse::error(ErrorMessage::AgentMissingTemplateHtml);
            }
        }

        try {
            $newHtml = $this->htmlCssProcessor->applyBindingChange(
                $section->content()->html(),
                $bindingId,
                $changeType,
                $changeType === 'query'
                    ? [
                        'query_args' => $queryArgs,
                        'allowed_query_attributes' => BindingSchema::queryAttributesForSource($target->source()),
                    ]
                    : ['template_html' => (string) ($request->get_param('template_html') ?? '')],
            );
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::error(ErrorMessage::AgentBindingElementNotFound, [
                'detail' => $exception->getMessage(),
            ]);
        }

        $saveResult = $this->saveSectionSource(
            'manage_binding',
            $pageId,
            $section,
            $newHtml,
            $section->content()->css(),
        );
        if ($saveResult instanceof \WP_REST_Response || $saveResult instanceof \WP_Error) {
            return $saveResult;
        }

        $operation = $changeType === 'query' ? 'update_query' : 'update_template';
        $lines = [
            'TOOL: manage_binding',
            'RESULT: success',
            'OPERATION: ' . $operation,
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            'BINDING_ID: ' . $bindingId,
            'CHANGE_TYPE: ' . $changeType,
            '',
        ];
        $this->appendDiffLines($lines, 'HTML DIFF', $saveResult['html_diff']);
        $this->appendDiffLines($lines, 'CSS DIFF', $saveResult['css_diff']);
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call manage_binding operation=inspect to verify the binding contract if needed.';

        return AgentTextResponse::ok(\implode("\n", $lines));
    }

    public function manage(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = \trim((string) ($request->get_param('operation') ?? ''));

        if ($operation === 'inspect') {
            return $this->facadeResponse($this->read($request), 'manage_binding', 'inspect');
        }

        if ($operation === 'update_query') {
            return $this->facadeResponse($this->update($this->requestWithOverrides($request, [
                'change_type' => 'query',
            ])), 'manage_binding', 'update_query');
        }

        if ($operation === 'update_template') {
            return $this->facadeResponse($this->update($this->requestWithOverrides($request, [
                'change_type' => 'template',
            ])), 'manage_binding', 'update_template');
        }

        return $this->textToolError('manage_binding', 400, 'invalid_operation', [
            'OPERATION: ' . ($operation !== '' ? $operation : 'missing'),
            'NEXT STEP',
            'Retry with operation inspect, update_query, or update_template.',
        ]);
    }

    /**
     * @param array<string, mixed> $queryArgs
     * @return array<string, mixed>|\WP_Error
     */
    private function canonicalBindingQueryArgs(string $source, array $queryArgs): array|\WP_Error
    {
        $attributeConfig = BindingSchema::queryAttributeConfigForSource($source);
        $allowed = [];

        foreach ($attributeConfig as $attribute => $_config) {
            if (!\is_string($attribute) || $attribute === '') {
                continue;
            }
            $allowed[$attribute] = $attribute;
            $allowed[$this->queryAttributeKey($attribute)] = $attribute;
        }

        $canonical = [];
        foreach ($queryArgs as $key => $value) {
            if (!\is_string($key) || !\preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
                return ApiResponse::error(ErrorMessage::AgentInvalidQueryKey, [
                    'detail' => 'Query arg keys must match a declared query attribute for ' . $source . '.',
                ]);
            }
            if ($value !== null && !\is_scalar($value)) {
                return ApiResponse::error(ErrorMessage::AgentInvalidQueryKey, [
                    'detail' => 'Query arg "' . $key . '" must be a scalar value.',
                ]);
            }

            $attribute = $allowed[$key] ?? null;
            if (!\is_string($attribute) || \str_starts_with($attribute, 'data-ai-')) {
                return ApiResponse::error(ErrorMessage::AgentInvalidQueryKey, [
                    'detail' => 'Unsupported query arg "' . $key . '" for binding "' . $source . '".',
                    'allowed_query_args' => \array_values(\array_unique(\array_map(
                        fn (string $attributeName): string => $this->queryAttributeKey($attributeName),
                        \array_keys($attributeConfig),
                    ))),
                ]);
            }

            $canonical[$attribute] = $value;
        }

        return $canonical;
    }

    private function queryAttributeKey(string $attribute): string
    {
        return \str_replace('-', '_', \preg_replace('/^data-/', '', $attribute) ?? $attribute);
    }

    /**
     * Resolve the exact section snapshot whose binding contract will be edited.
     *
     * @return array{0: ?Section, 1: int, 2: ?\WP_Error}
     */
    private function resolveSection(\WP_REST_Request $request): array
    {
        $sectionId = RequestId::positive($request->get_param('section_id'));
        $pageIdValue = $request->get_param('page_id');
        $requestedPageId = $pageIdValue === null ? 0 : RequestId::positive($pageIdValue);
        if ($sectionId === null || $requestedPageId === null) {
            return [null, 0, ApiResponse::error(ErrorMessage::InvalidRouteId)];
        }

        if ($requestedPageId !== 0) {
            if (!$this->permissions->canEditPage($requestedPageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::PageEditForbidden)];
            }
            if (!$this->sectionService->isPageOwned($requestedPageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::PageNotOwned)];
            }
        }

        try {
            $section = $this->sections->findById($sectionId);
        } catch (SectionNotFoundException) {
            if ($requestedPageId !== 0) {
                return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFoundOnPage)];
            }
            if (\get_post_type($sectionId) === 'upb_global_part') {
                return [null, 0, ApiResponse::error(ErrorMessage::AgentWrongTool)];
            }

            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound)];
        }

        $pageId = $section->pageId();
        if ($requestedPageId !== 0 && $requestedPageId !== $pageId) {
            return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFoundOnPage)];
        }
        if ($requestedPageId === 0) {
            if (!$this->permissions->canEditPage($pageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound)];
            }
            if (!$this->sectionService->isPageOwned($pageId)) {
                return [null, 0, ApiResponse::error(ErrorMessage::SectionNotFound)];
            }
        }

        return [$section, $pageId, null];
    }

    /**
     * @return array{section_id: int, page_id: int, position: int, name: string, preview: string, html_diff: CompactSourceDiff, css_diff: CompactSourceDiff, warnings: string[]}|\WP_REST_Response|\WP_Error
     */
    private function saveSectionSource(
        string $toolName,
        int $pageId,
        Section $section,
        string $html,
        string $css,
    ): array|\WP_REST_Response|\WP_Error {
        $preview = $this->previewSectionSource($toolName, $pageId, $section, $html, $css);
        if ($preview instanceof \WP_REST_Response || $preview instanceof \WP_Error) {
            return $preview;
        }

        try {
            $result = $this->sectionService->replaceLoadedSectionSource(
                pageId: $pageId,
                sections: $preview['sections'],
                sectionId: (int) $section->id(),
                sectionName: $section->name(),
                content: ['html' => $preview['html'], 'css' => $preview['css']],
                requireExactCss: false,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError($toolName, 400, 'invalid_section_write', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Read the section again and retry.',
            ]);
        } catch (SectionValidationException $exception) {
            return $this->textToolError($toolName, 422, 'section_validation_failed', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the replacement source and retry.',
            ]);
        } catch (CssRuleIntegrityException $exception) {
            return $this->cssRuleIntegrityError($toolName, $pageId, (int) $section->id(), $exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError($toolName, $exception);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        } catch (\RuntimeException $exception) {
            return $this->sectionWriteError($toolName, $pageId, (int) $section->id(), $exception);
        }

        return [
            'section_id' => (int) ($result['section_id'] ?? $section->id()),
            'page_id' => $pageId,
            'position' => $section->position(),
            'name' => $section->name(),
            'preview' => $this->pagePreviewUrl($pageId, \get_permalink($pageId) ?: ''),
            'html_diff' => $preview['html_diff'],
            'css_diff' => $preview['css_diff'],
            'warnings' => \array_values(\array_unique([
                ...($preview['warnings'] ?? []),
                ...($result['warnings'] ?? []),
            ])),
        ];
    }

    /**
     * @return array{html: string, css: string, html_diff: CompactSourceDiff, css_diff: CompactSourceDiff, warnings: string[], sections: SectionCollection}|\WP_REST_Response|\WP_Error
     */
    private function previewSectionSource(
        string $toolName,
        int $pageId,
        Section $section,
        string $html,
        string $css,
    ): array|\WP_REST_Response|\WP_Error {
        try {
            $sections = $this->sections->findByPageId($pageId);
            $currentSection = $sections->getById((int) $section->id());
            if (!$this->sameSectionSourceSnapshot($section, $currentSection)) {
                return $this->textToolError($toolName, 409, 'stale_source_generation', [
                    'PAGE_ID: ' . $pageId,
                    'SECTION_ID: ' . (string) $section->id(),
                    'DETAIL: The section changed after this edit was prepared. Nothing was saved.',
                    'NEXT STEP',
                    'Call read_part again and retry against the current source.',
                ]);
            }

            $preview = $this->sectionService->previewLoadedSectionSource(
                pageId: $pageId,
                sections: $sections,
                sectionId: (int) $section->id(),
                sectionName: $section->name(),
                content: ['html' => $html, 'css' => $css],
                requireExactCss: false,
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->textToolError($toolName, 400, 'invalid_section_write', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Read the section again and retry.',
            ]);
        } catch (SectionValidationException $exception) {
            return $this->textToolError($toolName, 422, 'section_validation_failed', [
                'SECTION_ID: ' . (string) $section->id(),
                'DETAIL: ' . $exception->getMessage(),
                'NEXT STEP',
                'Fix the replacement source and retry.',
            ]);
        } catch (CssRuleIntegrityException $exception) {
            return $this->cssRuleIntegrityError($toolName, $pageId, (int) $section->id(), $exception);
        } catch (StaleSourceGenerationException $exception) {
            return $this->staleSourceToolError($toolName, $exception);
        } catch (PageNotFoundException) {
            return ApiResponse::error(ErrorMessage::PageNotFound);
        } catch (SectionNotFoundException) {
            return ApiResponse::error(ErrorMessage::SectionNotFound);
        }

        return [
            'html' => $preview['html'],
            'css' => $preview['css'],
            'html_diff' => $this->sourceDiffer->diff(
                'HTML DIFF',
                $this->maskForAgent($section->content()->html()),
                $this->maskForAgent((string) $preview['html']),
            ),
            'css_diff' => $this->sourceDiffer->diff('CSS DIFF', $section->content()->css(), $preview['css']),
            'warnings' => $preview['warnings'] ?? [],
            'sections' => $sections,
        ];
    }

    private function sameSectionSourceSnapshot(Section $expected, Section $current): bool
    {
        return $expected->id() === $current->id()
            && $expected->pageId() === $current->pageId()
            && $expected->position() === $current->position()
            && $expected->name() === $current->name()
            && $expected->content()->toArray() === $current->content()->toArray();
    }

    private function maskForAgent(string $html): string
    {
        $this->maskableBindingIds ??= $this->bindingRegistry?->fullyProjectedBindingIds() ?? [];

        return DynamicRegionToken::encodeForCodeEditor(
            $html,
            $this->maskableBindingIds,
            payloadMasks: false,
        );
    }

    private function cssRuleIntegrityError(
        string $toolName,
        int $pageId,
        int $sectionId,
        CssRuleIntegrityException $exception,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 422, 'css_rule_integrity_failed', [
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            'DETAIL: ' . $exception->getMessage(),
            'NEXT STEP',
            $this->cssRuleIntegrityNextStep($exception),
        ]);
    }

    private function cssRuleIntegrityNextStep(CssRuleIntegrityException $exception): string
    {
        return match ($exception->reason()) {
            CssRuleIntegrityException::MALFORMED_SOURCE => 'Call read_part include=source, repair the unbalanced CSS with mode=source_replace, then retry the css_rule edit.',
            CssRuleIntegrityException::AMBIGUOUS_COMMENT => 'Call read_part include=source and use mode=source_patch with an exact current substring so the comment and intended replacement are both explicit.',
            CssRuleIntegrityException::AMBIGUOUS_DECLARATION_BOUNDARY => 'Call read_part include=source and repair the declaration boundary with mode=source_patch or mode=source_replace before retrying css_rule.',
            CssRuleIntegrityException::MULTIPLE_GLOBAL_PART_SOURCE_ROWS => 'Call read_part kind=global_part include=source. Migrate or explicitly consolidate every stored source row before retrying the write.',
            CssRuleIntegrityException::UNPRESERVABLE_GLOBAL_PART_SOURCE_ROWS => 'Ask an administrator to repair or explicitly consolidate the stored legacy global-part rows before retrying the write.',
            default => 'Call read_part include=source again. Preserve or explicitly repair the rejected CSS with mode=source_replace before retrying the requested edit.',
        };
    }

    private function sectionWriteError(
        string $toolName,
        int $pageId,
        int $sectionId,
        \RuntimeException $exception,
    ): \WP_REST_Response {
        if ($exception instanceof SourceTransactionsUnavailableException) {
            return $this->textToolError($toolName, 500, 'source_transactions_unavailable', [
                'KIND: section',
                'PAGE_ID: ' . $pageId,
                'SECTION_ID: ' . $sectionId,
                'DETAIL: ' . $exception->getMessage(),
                'RETRY_SAFETY: Nothing was saved by this operation.',
                'NEXT STEP',
                'Convert the named database table to InnoDB. Then call read_part include=source again and retry against the current source.',
            ]);
        }

        return $this->textToolError($toolName, 500, 'section_write_failed', [
            'KIND: section',
            'PAGE_ID: ' . $pageId,
            'SECTION_ID: ' . $sectionId,
            'DETAIL: The section write did not complete cleanly: ' . $exception->getMessage(),
            'RETRY_SAFETY: The source may already have been saved. Do not retry blindly.',
            'NEXT STEP',
            'Call read_part kind=section section_id=' . $sectionId . ' include=source first. If the requested change is present, do not retry. If it is absent, resolve the server error before retrying.',
        ]);
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

    /** @param list<string> $lines */
    private function textToolError(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(\implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    /** @param list<string> $lines */
    private function appendDiffLines(array &$lines, string $heading, CompactSourceDiff $diff): void
    {
        $lines[] = $heading;
        foreach (\explode("\n", $diff->body()) as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
    }

    private function facadeResponse(
        \WP_REST_Response|\WP_Error $response,
        string $toolName,
        ?string $operation = null,
    ): \WP_REST_Response|\WP_Error {
        if (!$response instanceof \WP_REST_Response) {
            return $response;
        }

        $body = $response->get_data();
        if (!\is_string($body)) {
            return $response;
        }

        $body = \strtr($body, [
            'read_page_outline' => 'read_page_context',
            'read_section_manifest' => 'read_part include=manifest',
            'read_section_source' => 'read_part include=source',
            'read_content_targets' => 'read_part include=content_targets',
            'read_design_targets' => 'read_part include=design_targets',
            'read_global_part_source' => 'read_part kind=global_part include=source',
            'update_text_target' => 'edit_part mode=text',
            'update_link_target' => 'edit_part mode=link',
            'update_image_target' => 'edit_part mode=image',
            'update_element_style' => 'edit_part mode=durable_style',
            'patch_section_source' => 'edit_part mode=source_patch',
            'rewrite_section_source' => 'edit_part mode=source_replace',
            'update_global_part' => 'edit_part kind=global_part mode=source_replace',
            'patch_global_part_source' => 'edit_part kind=global_part mode=source_patch',
            'patch_global_part' => 'edit_part kind=global_part mode=source_patch',
            'list_bindings' => 'manage_binding operation=search',
            'get_binding_guide' => 'manage_binding operation=guide',
            'update_binding' => 'manage_binding operation=update_query or update_template',
            'preview_patch' => 'preview_change',
            'reorder_sections' => 'manage_sections operation=reorder',
            'delete_section' => 'manage_sections operation=delete',
        ]);
        $replacement = 'TOOL: ' . $toolName;
        $body = \preg_match('/^TOOL: /m', $body) === 1
            ? (\preg_replace('/^TOOL: [^\n]+/m', $replacement, $body, 1) ?? $body)
            : $replacement . "\n" . $body;

        if ($operation !== null && !\str_contains($body, "\nOPERATION:")) {
            $body = \preg_replace(
                '/^RESULT: ([^\n]+)/m',
                'RESULT: $1' . "\n" . 'OPERATION: ' . $operation,
                $body,
                1,
            ) ?? $body;
        }

        return AgentTextResponse::withStatus($body, $response->get_status());
    }

    /**
     * @param array<string, mixed> $params
     */
    private function requestWithOverrides(\WP_REST_Request $request, array $params): \WP_REST_Request
    {
        if (\method_exists($request, 'set_param')) {
            $cloned = clone $request;
            foreach ($params as $key => $value) {
                $cloned->set_param((string) $key, $value);
            }

            return $cloned;
        }

        $existing = [];
        if (\method_exists($request, 'get_params')) {
            $existing = (array) $request->get_params();
        } elseif (\property_exists($request, 'params')) {
            $reflection = new \ReflectionProperty($request, 'params');
            $reflection->setAccessible(true);
            $existing = (array) $reflection->getValue($request);
        }

        return new \WP_REST_Request(\array_replace($existing, $params));
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
