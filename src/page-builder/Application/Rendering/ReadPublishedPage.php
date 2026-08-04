<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifact;
use UncannyPageBuilder\Domain\Publishing\PublishedPageArtifactRepositoryInterface;

/**
 * Resolves the one exact artifact selected for public rendering.
 *
 * Results are request-cached so template selection, assets, content, and footer
 * output cannot observe different pointers during one WordPress request.
 */
final class ReadPublishedPage implements PublishedPageReaderInterface
{
    /** @var array<int, PublishedPageReadResult> */
    private array $reads = [];

    public function __construct(
        private readonly PageStateRepositoryInterface $states,
        private readonly PublishedPageArtifactRepositoryInterface $artifacts,
        private readonly PublishedPageAssetResolverInterface $assetResolver,
        private readonly PublicPageIdentityReaderInterface $publicIdentity,
    ) {}

    public function read(int $pageId): PublishedPageReadResult
    {
        if ($pageId <= 0) {
            return PublishedPageReadResult::failed(0, PublishedPageStatus::NotManaged, 'invalid_page_id');
        }

        return $this->reads[$pageId] ??= $this->readUncached($pageId);
    }

    private function readUncached(int $pageId): PublishedPageReadResult
    {
        try {
            $state = $this->states->findForPage($pageId);
        } catch (\Throwable) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::ReadFailed, 'page_state_read_failed');
        }

        if ($state === null) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::NotManaged);
        }

        $artifactId = $state->publishedArtifactId();
        if ($artifactId === null) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::Unpublished);
        }

        try {
            $artifact = $this->artifacts->findForPage($pageId, $artifactId);
        } catch (\Throwable) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::InvalidArtifact, 'artifact_integrity_failed');
        }

        if (!$artifact instanceof PublishedPageArtifact) {
            return $this->brokenPointer($pageId, $artifactId);
        }

        try {
            $identity = $this->publicIdentity->read($pageId);
        } catch (\Throwable) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::ReadFailed, 'public_identity_read_failed');
        }
        if (!$identity instanceof PublicPageIdentity || $identity->pageId() !== $pageId) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::ReadFailed, 'public_identity_missing');
        }
        if (!$identity->matchesPublication($artifact->title(), $artifact->slug())) {
            return PublishedPageReadResult::failed(
                $pageId,
                PublishedPageStatus::PublicIdentityMismatch,
                'public_identity_mismatch',
            );
        }

        try {
            $assets = $this->assetResolver->resolve($artifact);
        } catch (PublishedPageRuntimeUnavailable $exception) {
            return PublishedPageReadResult::failed(
                $pageId,
                PublishedPageStatus::RuntimeUnavailable,
                $exception->reasonCode(),
            );
        } catch (\Throwable) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::RuntimeUnavailable, 'runtime_resolution_failed');
        }

        return PublishedPageReadResult::ready(new PublishedPage($artifact, $assets));
    }

    private function brokenPointer(int $pageId, int $artifactId): PublishedPageReadResult
    {
        try {
            $ownerPageId = $this->artifacts->pageIdForArtifact($artifactId);
        } catch (\Throwable) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::ReadFailed, 'pointer_diagnostic_failed');
        }

        if ($ownerPageId !== null && $ownerPageId !== $pageId) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::CrossPageArtifact, 'cross_page_pointer');
        }

        return PublishedPageReadResult::failed($pageId, PublishedPageStatus::MissingArtifact, 'missing_artifact');
    }
}
