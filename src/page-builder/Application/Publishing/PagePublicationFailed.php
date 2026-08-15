<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

/**
 * Expected publication failure with a stable outcome for presentation layers.
 */
final class PagePublicationFailed extends \RuntimeException
{
    /** @param array<string, mixed> $details */
    private function __construct(
        private readonly PagePublicationOutcome $outcome,
        string $message,
        private readonly array $details = [],
        ?\Throwable $previous = null,
    ) {
        if ($outcome === PagePublicationOutcome::Published) {
            throw new \InvalidArgumentException('A successful publication is not a failure.');
        }

        parent::__construct($message, 0, $previous);
    }

    public static function notAuthorized(): self
    {
        return new self(
            PagePublicationOutcome::NotAuthorized,
            'You do not have permission to publish this page.',
        );
    }

    public static function staleSource(string $scope = 'page', ?\Throwable $previous = null): self
    {
        return new self(
            PagePublicationOutcome::StaleSource,
            'The working page changed while its publication was being built. Reload and try again.',
            ['scope' => $scope],
            $previous,
        );
    }

    /** @param array<int, array<string, mixed>> $report */
    public static function staticSafetyFailed(array $report, string $message = ''): self
    {
        return new self(
            PagePublicationOutcome::StaticSafetyFailed,
            $message !== '' ? $message : 'The page contains output that is not safe to publish.',
            ['report' => $report],
        );
    }

    public static function nothingToPublish(): self
    {
        return new self(
            PagePublicationOutcome::NothingToPublish,
            'Add at least one Page Builder section before publishing.',
        );
    }

    public static function slugConflict(string $requestedSlug, string $suggestedSlug): self
    {
        return new self(
            PagePublicationOutcome::SlugConflict,
            'That page URL is already in use. Update the working slug and try again.',
            [
                'requested_slug' => $requestedSlug,
                'suggested_slug' => $suggestedSlug,
            ],
        );
    }

    public static function artifactPersistFailed(\Throwable $previous): self
    {
        return new self(
            PagePublicationOutcome::ArtifactPersistFailed,
            'The immutable page artifact could not be stored. Nothing was published.',
            [],
            $previous,
        );
    }

    public static function publicStateCommitFailed(\Throwable $previous, string $reasonCode = ''): self
    {
        return new self(
            PagePublicationOutcome::PublicStateCommitFailed,
            'The public page state could not be committed. Nothing was published.',
            $reasonCode !== '' ? ['reason_code' => $reasonCode] : [],
            $previous,
        );
    }

    public function outcome(): PagePublicationOutcome
    {
        return $this->outcome;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }
}
