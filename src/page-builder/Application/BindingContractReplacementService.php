<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Domain\Exception\BindingContractUpdateException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionBindingContract;
use UncannyPageBuilder\Domain\Section\SectionBindingContractInspectorInterface;

final class BindingContractReplacementService
{
    public function __construct(
        private readonly SectionBindingContractInspectorInterface $bindingContractInspector,
    ) {}

    /**
     * Replace exactly one dynamic binding template inside a section-like target.
     *
     * @throws BindingContractUpdateException
     */
    public function replace(
        Section $section,
        string $bindingId,
        string $expectedContractHash,
        string $replacementTemplateHtml,
    ): string {
        $contract = $this->findBindingContract($section, $bindingId);
        if ($contract === null) {
            throw BindingContractUpdateException::bindingNotFound($bindingId);
        }

        if ($expectedContractHash !== $contract->contractHash()) {
            throw BindingContractUpdateException::contractHashMismatch($contract->bindingId());
        }

        $storedDoc = new \DOMDocument();
        $wrappedStored = '<div id="__upb_bc_root">' . $section->content()->html() . '</div>';
        $previousStoredErrors = libxml_use_internal_errors(true);
        $storedDoc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $wrappedStored,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousStoredErrors);

        $storedRoot = $storedDoc->getElementById('__upb_bc_root');
        if (!$storedRoot instanceof \DOMElement) {
            throw BindingContractUpdateException::bindingNotFound($contract->bindingId());
        }

        $targetNode = $this->findNodeByPath($storedRoot, $contract->path());
        if (!$targetNode instanceof \DOMElement) {
            throw BindingContractUpdateException::bindingNotFound($contract->bindingId());
        }

        $replacementDoc = new \DOMDocument();
        $wrappedReplacement = '<div id="__upb_bc_repl">' . $replacementTemplateHtml . '</div>';
        $previousReplacementErrors = libxml_use_internal_errors(true);
        $replacementDoc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $wrappedReplacement,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousReplacementErrors);

        $replacementRoot = $replacementDoc->getElementById('__upb_bc_repl');
        if (!$replacementRoot instanceof \DOMElement) {
            throw BindingContractUpdateException::invalidRoot();
        }

        // Clear existing children and import all replacement children.
        while ($targetNode->firstChild) {
            $targetNode->removeChild($targetNode->firstChild);
        }
        foreach ($replacementRoot->childNodes as $child) {
            $imported = $storedDoc->importNode($child, true);
            $targetNode->appendChild($imported);
        }

        $output = '';
        foreach ($storedRoot->childNodes as $child) {
            $output .= $storedDoc->saveHTML($child);
        }

        return trim($output);
    }

    private function findBindingContract(Section $section, string $bindingId): ?SectionBindingContract
    {
        foreach ($this->bindingContractInspector->inspect($section) as $contract) {
            if ($contract->bindingId() === $bindingId) {
                return $contract;
            }
        }

        return null;
    }

    private function findNodeByPath(\DOMElement $root, string $path): ?\DOMElement
    {
        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        if ($segments === []) {
            return null;
        }

        $start = $root;
        if (!$this->segmentMatches($start, $segments[0])) {
            $start = $this->findDirectChildBySegment($root, $segments[0]);
        }

        if (!$start instanceof \DOMElement) {
            return null;
        }

        $current = $start;
        foreach (array_slice($segments, 1) as $segment) {
            $current = $this->findDirectChildBySegment($current, $segment);
            if (!$current instanceof \DOMElement) {
                return null;
            }
        }

        return $current;
    }

    private function segmentMatches(\DOMElement $node, string $segment): bool
    {
        if (!preg_match('/^([a-z0-9_-]+)\[(\d+)\]$/i', $segment, $matches)) {
            return false;
        }

        return strtolower($node->tagName) === strtolower($matches[1]) && $matches[2] === '1';
    }

    private function findDirectChildBySegment(\DOMElement $parent, string $segment): ?\DOMElement
    {
        if (!preg_match('/^([a-z0-9_-]+)\[(\d+)\]$/i', $segment, $matches)) {
            return null;
        }

        $tag = strtolower($matches[1]);
        $targetIndex = (int) $matches[2];
        $index = 0;

        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (strtolower($child->tagName) !== $tag) {
                continue;
            }

            $index++;
            if ($index === $targetIndex) {
                return $child;
            }
        }

        return null;
    }
}
