<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

/**
 * One file emitted by a static page export.
 */
final class StaticExportArtifact
{
    public function __construct(
        private readonly string $path,
        private readonly string $mimeType,
        private readonly string $content,
    ) {
        if (trim($path) === '' || str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Export artifact path must be relative.');
        }
        if (trim($mimeType) === '') {
            throw new \InvalidArgumentException('Export artifact mime type is required.');
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function content(): string
    {
        return $this->content;
    }

    /**
     * @return array{path: string, mime_type: string, size: int, sha256: string, content: string}
     */
    public function toArray(): array
    {
        return [
            'path'      => $this->path,
            'mime_type' => $this->mimeType,
            'size'      => strlen($this->content),
            'sha256'    => hash('sha256', $this->content),
            'content'   => $this->content,
        ];
    }
}
