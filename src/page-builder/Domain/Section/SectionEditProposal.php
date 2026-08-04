<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Versioned proposal submitted by the AI agent to edit an existing section.
 *
 * Supported operations: replace_source, patch_source, update_editables,
 * rewrite_editable, replace_binding_contract, no_op.
 */
final class SectionEditProposal
{
    private const SCHEMA_VERSION = '1.0';

    private const VALID_OPERATIONS = ['replace_source', 'patch_source', 'update_editables', 'rewrite_editable', 'replace_binding_contract', 'no_op'];

    /**
     * @param string   $schemaVersion
     * @param string   $operation
     * @param int      $pageId
     * @param int      $sectionId
     * @param ?string  $name
     * @param ?string  $html
     * @param ?string  $css
     * @param string[] $preservedEditableKeys
     * @param EditableUpdate[] $editableUpdates
     * @param ?string  $editableKey
     * @param ?string  $editableType
     * @param ?string  $replacementHtml
     * @param ?string  $replacementCss
     * @param ?string  $bindingId
     * @param ?string  $bindingSource
     * @param ?string  $expectedContractHash
     * @param ?string  $replacementTemplateHtml
     */
    private function __construct(
        private readonly string $schemaVersion,
        private readonly string $operation,
        private readonly int $pageId,
        private readonly int $sectionId,
        private readonly ?string $name,
        private readonly ?string $html,
        private readonly ?string $css,
        private readonly array $preservedEditableKeys,
        private readonly array $editableUpdates,
        private readonly ?string $editableKey = null,
        private readonly ?string $editableType = null,
        private readonly ?string $replacementHtml = null,
        private readonly ?string $replacementCss = null,
        private readonly ?string $bindingId = null,
        private readonly ?string $bindingSource = null,
        private readonly ?string $expectedContractHash = null,
        private readonly ?string $replacementTemplateHtml = null,
        /** @var array<int, array{old: string, new: string}> */
        private readonly array $htmlPatches = [],
        /** @var array<int, array{old: string, new: string}> */
        private readonly array $cssPatches = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $schemaVersion = $data['schema_version'] ?? '';
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException("Unsupported schema_version: '{$schemaVersion}'. Expected '1.0'.");
        }

        $operation = $data['operation'] ?? '';
        if (!in_array($operation, self::VALID_OPERATIONS, true)) {
            throw new \InvalidArgumentException("Unknown operation: '{$operation}'.");
        }

        $pageId = $data['page_id'] ?? null;
        if (!is_int($pageId) || $pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be a positive integer.');
        }

        $sectionId = $data['section_id'] ?? null;
        if (!is_int($sectionId) || $sectionId <= 0) {
            throw new \InvalidArgumentException('section_id must be a positive integer.');
        }

        $name = $data['name'] ?? null;
        $html = $data['html'] ?? null;
        $css  = $data['css'] ?? null;
        $preservedEditableKeys = $data['preserved_editable_keys'] ?? [];
        $editableUpdates = $data['editable_updates'] ?? [];

        if ($operation === 'replace_source') {
            if (!is_string($name) || trim($name) === '') {
                throw new \InvalidArgumentException('name is required for replace_source.');
            }
            if (!is_string($html) || trim($html) === '') {
                throw new \InvalidArgumentException('html is required for replace_source.');
            }
            if (!is_string($css) || trim($css) === '') {
                throw new \InvalidArgumentException('css is required for replace_source.');
            }
        }

        $htmlPatches = [];
        $cssPatches = [];

        if ($operation === 'patch_source') {
            $rawHtmlPatches = $data['html_patches'] ?? [];
            $rawCssPatches = $data['css_patches'] ?? [];

            if (!is_array($rawHtmlPatches)) {
                $rawHtmlPatches = [];
            }
            if (!is_array($rawCssPatches)) {
                $rawCssPatches = [];
            }

            if (empty($rawHtmlPatches) && empty($rawCssPatches)) {
                throw new \InvalidArgumentException('patch_source requires at least one patch in html_patches or css_patches.');
            }

            foreach ($rawHtmlPatches as $i => $patch) {
                if (!is_array($patch) || !isset($patch['old'], $patch['new']) || !is_string($patch['old']) || !is_string($patch['new'])) {
                    throw new \InvalidArgumentException("html_patches[{$i}] must have 'old' and 'new' string fields.");
                }
                $htmlPatches[] = ['old' => $patch['old'], 'new' => $patch['new']];
            }

            foreach ($rawCssPatches as $i => $patch) {
                if (!is_array($patch) || !isset($patch['old'], $patch['new']) || !is_string($patch['old']) || !is_string($patch['new'])) {
                    throw new \InvalidArgumentException("css_patches[{$i}] must have 'old' and 'new' string fields.");
                }
                $cssPatches[] = ['old' => $patch['old'], 'new' => $patch['new']];
            }
        }

        $parsedUpdates = [];
        if ($operation === 'update_editables') {
            if (!is_array($editableUpdates) || empty($editableUpdates)) {
                throw new \InvalidArgumentException('editable_updates is required for update_editables.');
            }
            foreach ($editableUpdates as $i => $raw) {
                if (!is_array($raw)) {
                    throw new \InvalidArgumentException("editable_updates[{$i}] must be an object.");
                }
                $parsedUpdates[] = EditableUpdate::fromArray($raw);
            }
        }

        $editableKey = null;
        $editableType = null;
        $replacementHtml = null;
        $replacementCss = null;
        $bindingId = null;
        $bindingSource = null;
        $expectedContractHash = null;
        $replacementTemplateHtml = null;

        if ($operation === 'rewrite_editable') {
            $editableKey = $data['editable_key'] ?? '';
            if (!is_string($editableKey) || trim($editableKey) === '') {
                throw new \InvalidArgumentException('editable_key is required for rewrite_editable.');
            }
            $editableKey = trim($editableKey);

            $editableType = $data['editable_type'] ?? '';
            if (!is_string($editableType) || trim($editableType) === '') {
                throw new \InvalidArgumentException('editable_type is required for rewrite_editable.');
            }

            $replacementHtml = $data['replacement_html'] ?? '';
            if (!is_string($replacementHtml) || trim($replacementHtml) === '') {
                throw new \InvalidArgumentException('replacement_html is required for rewrite_editable.');
            }
            $replacementHtml = trim($replacementHtml);

            $replacementCss = $data['replacement_css'] ?? null;
            if ($replacementCss !== null && !is_string($replacementCss)) {
                throw new \InvalidArgumentException('replacement_css must be a string when provided.');
            }
        }

        if ($operation === 'replace_binding_contract') {
            $bindingId = $data['binding_id'] ?? '';
            if (!is_string($bindingId) || trim($bindingId) === '') {
                throw new \InvalidArgumentException('binding_id is required for replace_binding_contract.');
            }
            $bindingId = trim($bindingId);

            $bindingSource = $data['binding_source'] ?? '';
            if (!is_string($bindingSource) || trim($bindingSource) === '') {
                throw new \InvalidArgumentException('binding_source is required for replace_binding_contract.');
            }

            $expectedContractHash = $data['expected_contract_hash'] ?? '';
            if (!is_string($expectedContractHash) || trim($expectedContractHash) === '') {
                throw new \InvalidArgumentException('expected_contract_hash is required for replace_binding_contract.');
            }
            $expectedContractHash = trim($expectedContractHash);

            $replacementTemplateHtml = $data['replacement_template_html'] ?? '';
            if (!is_string($replacementTemplateHtml) || trim($replacementTemplateHtml) === '') {
                throw new \InvalidArgumentException('replacement_template_html is required for replace_binding_contract.');
            }
            $replacementTemplateHtml = trim($replacementTemplateHtml);
        }

        return new self(
            schemaVersion: $schemaVersion,
            operation: $operation,
            pageId: $pageId,
            sectionId: $sectionId,
            name: is_string($name) ? trim($name) : null,
            html: is_string($html) ? trim($html) : null,
            css: is_string($css) ? trim($css) : null,
            preservedEditableKeys: $preservedEditableKeys,
            editableUpdates: $parsedUpdates,
            editableKey: $editableKey,
            editableType: $editableType,
            replacementHtml: $replacementHtml,
            replacementCss: $replacementCss,
            bindingId: $bindingId,
            bindingSource: $bindingSource,
            expectedContractHash: $expectedContractHash,
            replacementTemplateHtml: $replacementTemplateHtml,
            htmlPatches: $htmlPatches,
            cssPatches: $cssPatches,
        );
    }

    // ── Accessors ──────────────────────────────

    public function pageId(): int           { return $this->pageId; }
    public function sectionId(): int        { return $this->sectionId; }
    public function name(): ?string         { return $this->name; }
    public function html(): ?string         { return $this->html; }
    public function css(): ?string          { return $this->css; }

    /** @return string[] */
    public function preservedEditableKeys(): array { return $this->preservedEditableKeys; }

    /** @return EditableUpdate[] */
    public function editableUpdates(): array { return $this->editableUpdates; }

    // ── Rewrite editable accessors ───────────────

    public function editableKey(): ?string       { return $this->editableKey; }
    public function editableType(): ?string      { return $this->editableType; }
    public function replacementHtml(): ?string   { return $this->replacementHtml; }
    public function replacementCss(): ?string    { return $this->replacementCss; }
    public function bindingId(): ?string         { return $this->bindingId; }
    public function expectedContractHash(): ?string { return $this->expectedContractHash; }
    public function replacementTemplateHtml(): ?string { return $this->replacementTemplateHtml; }

    // ── Operation helpers ──────────────────────

    public function isReplaceSource(): bool      { return $this->operation === 'replace_source'; }
    public function isPatchSource(): bool        { return $this->operation === 'patch_source'; }
    public function isUpdateEditables(): bool    { return $this->operation === 'update_editables'; }
    public function isRewriteEditable(): bool    { return $this->operation === 'rewrite_editable'; }
    public function isReplaceBindingContract(): bool { return $this->operation === 'replace_binding_contract'; }
    public function isNoOp(): bool               { return $this->operation === 'no_op'; }

    /** @return array<int, array{old: string, new: string}> */
    public function htmlPatches(): array { return $this->htmlPatches; }

    /** @return array<int, array{old: string, new: string}> */
    public function cssPatches(): array { return $this->cssPatches; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'schema_version'   => $this->schemaVersion,
            'operation'        => $this->operation,
            'page_id'          => $this->pageId,
            'section_id'       => $this->sectionId,
        ];

        if ($this->isReplaceSource()) {
            $data['name'] = $this->name;
            $data['html'] = $this->html;
            $data['css']  = $this->css;
            $data['preserved_editable_keys'] = $this->preservedEditableKeys;
        }

        if ($this->isUpdateEditables()) {
            $data['editable_updates'] = array_map(
                static fn(EditableUpdate $u) => $u->toArray(),
                $this->editableUpdates,
            );
        }

        if ($this->isRewriteEditable()) {
            $data['editable_key']     = $this->editableKey;
            $data['editable_type']    = $this->editableType;
            $data['replacement_html'] = $this->replacementHtml;
            if ($this->replacementCss !== null) {
                $data['replacement_css'] = $this->replacementCss;
            }
        }

        if ($this->isPatchSource()) {
            $data['html_patches'] = $this->htmlPatches;
            $data['css_patches'] = $this->cssPatches;
        }

        if ($this->isReplaceBindingContract()) {
            $data['binding_id'] = $this->bindingId;
            $data['binding_source'] = $this->bindingSource;
            $data['expected_contract_hash'] = $this->expectedContractHash;
            $data['replacement_template_html'] = $this->replacementTemplateHtml;
        }

        return $data;
    }
}
