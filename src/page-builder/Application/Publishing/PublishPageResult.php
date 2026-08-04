<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;

final class PublishPageResult
{
    /** @param list<array{code: string, message: string}> $warnings */
    public function __construct(
        private readonly PublishedPageArtifact $artifact,
        private readonly string $previousStatus,
        private readonly string $publicStatus,
        private readonly bool $firstPublication,
        private readonly array $warnings = [],
    ) {
        if ($artifact->id() === null) {
            throw new \InvalidArgumentException('A publication result requires a stored artifact.');
        }
    }

    public function outcome(): PagePublicationOutcome
    {
        return PagePublicationOutcome::Published;
    }

    public function artifact(): PublishedPageArtifact
    {
        return $this->artifact;
    }

    public function previousStatus(): string
    {
        return $this->previousStatus;
    }

    public function publicStatus(): string
    {
        return $this->publicStatus;
    }

    public function firstPublication(): bool
    {
        return $this->firstPublication;
    }

    /** @return list<array{code: string, message: string}> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function withWarning(string $code, string $message): self
    {
        return new self(
            artifact: $this->artifact,
            previousStatus: $this->previousStatus,
            publicStatus: $this->publicStatus,
            firstPublication: $this->firstPublication,
            warnings: [...$this->warnings, ['code' => $code, 'message' => $message]],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome()->value,
            'artifact_id' => $this->artifact->id(),
            'title' => $this->artifact->title(),
            'slug' => $this->artifact->slug(),
            'previous_status' => $this->previousStatus,
            'public_status' => $this->publicStatus,
            'first_publication' => $this->firstPublication,
            'warnings' => $this->warnings,
        ];
    }
}
