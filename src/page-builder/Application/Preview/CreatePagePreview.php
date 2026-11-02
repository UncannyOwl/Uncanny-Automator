<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Preview;

use UncannyPageBuilder\Application\Export\StaticPageExportBuilderInterface;

/** Builds a fixed preview without changing source or the public artifact. */
final class CreatePagePreview
{
    public const LIFETIME_SECONDS = 900;
    private const MAX_BYTES = 10485760;

    public function __construct(
        private readonly StaticPageExportBuilderInterface $builder,
        private readonly PreviewSnapshotStoreInterface $store,
    ) {}

    /** @return array<string, mixed> */
    public function create(int $pageId, string $title, string $permalink): array
    {
        $export = $this->builder->buildForPage($pageId, $title, $permalink);
        $html = $export->artifact($export->entryPath())?->content() ?? '';
        if ($html === '' || !$export->staticRenderingReport()->isSafe()) {
            throw new \RuntimeException('The page could not be rendered as a preview.');
        }

        // Embed only the export's exact asset references. No asset URLs can grant
        // wider access, and this also works with WordPress plain permalinks.
        $replacements = [];
        $totalBytes = strlen($html);
        foreach ($export->artifacts() as $artifact) {
            if ($artifact->path() === $export->entryPath()) {
                continue;
            }
            $totalBytes += strlen($artifact->content());
            if ($totalBytes > self::MAX_BYTES) {
                throw new \RuntimeException('The preview is too large.');
            }
            $uri = 'data:' . $artifact->mimeType() . ';base64,' . base64_encode($artifact->content());
            foreach (['"', "'"] as $quote) {
                $replacements[$quote . $artifact->path() . $quote] = $quote . $uri . $quote;
                $replacements[$quote . str_replace('/', '\\/', $artifact->path()) . $quote] = $quote . $uri . $quote;
            }
        }
        $html = strtr($html, $replacements);
        if (strlen($html) > self::MAX_BYTES) {
            throw new \RuntimeException('The preview is too large.');
        }

        return $this->store->save($pageId, $html, self::LIFETIME_SECONDS) + [
            'page_id' => $pageId,
            'snapshot_sha256' => hash('sha256', $html),
            'source_generations' => $export->dependencies()['source_generations'] ?? null,
            'static_rendering_report' => $export->staticRenderingReport()->records(),
        ];
    }
}
