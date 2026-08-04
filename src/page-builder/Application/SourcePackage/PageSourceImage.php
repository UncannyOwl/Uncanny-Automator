<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

use UncannyPageBuilder\Domain\SourcePackage\PageSourcePackage;

/**
 * One immutable image binary carried by a portable page archive.
 *
 * Multiple source URLs may point at the same bytes. Keeping those aliases on
 * one value object lets the archive deduplicate files without losing any URL
 * that must be rewritten after import.
 */
final class PageSourceImage
{
    public const MAX_SOURCE_URLS = 50;

    /**
     * @param list<string> $sourceUrls
     */
    public function __construct(
        private readonly array $sourceUrls,
        private readonly string $mimeType,
        private readonly string $extension,
        private readonly string $bytes,
        private readonly ?string $filePath = null,
        private readonly ?int $storedByteCount = null,
        private readonly ?string $storedSha256 = null,
    ) {
        if ($sourceUrls === []) {
            throw new \InvalidArgumentException('An archived image requires at least one source URL.');
        }

        if (count($sourceUrls) > self::MAX_SOURCE_URLS) {
            throw new \InvalidArgumentException('An archived image can contain at most 50 source URLs.');
        }

        $byteCount = $this->byteCount();
        if ($byteCount <= 0 || $byteCount > PageSourcePackage::MAX_IMAGE_BYTES) {
            throw new \InvalidArgumentException('An archived image must be 20 MB or smaller.');
        }

        if ($this->filePath !== null) {
            if ($this->filePath === '' || $this->storedByteCount === null || $this->storedByteCount <= 0) {
                throw new \InvalidArgumentException('The archived image file reference is invalid.');
            }

            if (!is_string($this->storedSha256) || !preg_match('/^[a-f0-9]{64}$/', $this->storedSha256)) {
                throw new \InvalidArgumentException('The archived image hash is invalid.');
            }

            return;
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $this->sha256())) {
            throw new \InvalidArgumentException('The archived image hash is invalid.');
        }
    }

    /**
     * Create an image reference that reads bytes from a temporary or upload file.
     *
     * The path remains outside the domain. Domain code only carries the
     * reference and its verified metadata.
     *
     * @param list<string> $sourceUrls
     */
    public static function fromFile(
        array $sourceUrls,
        string $mimeType,
        string $extension,
        string $filePath,
        int $byteCount,
        string $sha256,
    ): self {
        return new self($sourceUrls, $mimeType, $extension, '', $filePath, $byteCount, strtolower($sha256));
    }

    /** @return list<string> */
    public function sourceUrls(): array
    {
        return $this->sourceUrls;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function bytes(): string
    {
        if ($this->filePath !== null) {
            throw new \LogicException('This archived image stores bytes in a file reference.');
        }

        return $this->bytes;
    }

    public function byteCount(): int
    {
        return $this->storedByteCount ?? strlen($this->bytes);
    }

    public function sha256(): string
    {
        return $this->storedSha256 ?? hash('sha256', $this->bytes);
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * @param list<string> $sourceUrls
     */
    public function withSourceUrls(array $sourceUrls): self
    {
        if ($this->filePath !== null) {
            return self::fromFile(
                $sourceUrls,
                $this->mimeType,
                $this->extension,
                $this->filePath,
                $this->byteCount(),
                $this->sha256(),
            );
        }

        return new self($sourceUrls, $this->mimeType, $this->extension, $this->bytes);
    }

    public function archivePath(): string
    {
        return 'images/' . $this->sha256() . '.' . $this->extension;
    }
}
