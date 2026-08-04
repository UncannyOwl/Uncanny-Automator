<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

/**
 * Portable HTML/CSS export for one Page Builder page.
 */
final class StaticPageExport
{
    /** @var array<string, StaticExportArtifact> */
    private array $artifacts = [];

    /**
     * @param StaticExportArtifact[] $artifacts
     * @param array<string, mixed> $dependencies
     */
    public function __construct(
        private readonly int $pageId,
        private readonly string $entryPath,
        array $artifacts,
        private readonly StaticRenderingReport $staticRenderingReport = new StaticRenderingReport(),
        private readonly array $dependencies = [],
        private readonly string $customJavaScript = '',
    ) {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Static export page id is required.');
        }
        if ($entryPath === '') {
            throw new \InvalidArgumentException('Static export entry path is required.');
        }
        if ($dependencies !== [] && array_is_list($dependencies)) {
            throw new \InvalidArgumentException('Static export dependencies must be an associative array.');
        }

        foreach ($artifacts as $artifact) {
            $this->artifacts[$artifact->path()] = $artifact;
        }

        if (!isset($this->artifacts[$entryPath])) {
            throw new \InvalidArgumentException('Static export entry artifact is missing.');
        }
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function entryPath(): string
    {
        return $this->entryPath;
    }

    /**
     * @return StaticExportArtifact[]
     */
    public function artifacts(): array
    {
        return array_values($this->artifacts);
    }

    public function artifact(string $path): ?StaticExportArtifact
    {
        return $this->artifacts[$path] ?? null;
    }

    public function staticRenderingReport(): StaticRenderingReport
    {
        return $this->staticRenderingReport;
    }

    /**
     * @return array<string, mixed>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function customJavaScript(): string
    {
        return $this->customJavaScript;
    }

    /**
     * @return array{page_id: int, entry_path: string, artifacts: array<int, array{path: string, mime_type: string, size: int, sha256: string, content: string}>, static_safety_report: array<int, array<string, mixed>>, dependencies: array<string, mixed>, custom_javascript: string}
     */
    public function toArray(): array
    {
        return [
            'page_id'    => $this->pageId,
            'entry_path' => $this->entryPath,
            'artifacts'  => array_map(
                static fn(StaticExportArtifact $artifact): array => $artifact->toArray(),
                $this->artifacts(),
            ),
            'static_safety_report' => $this->staticRenderingReport->records(),
            'dependencies' => $this->dependencies,
            'custom_javascript' => $this->customJavaScript,
        ];
    }
}
