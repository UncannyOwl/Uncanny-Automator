<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;

/**
 * The only application use case authorized to move Page Builder public state.
 */
final class PublishPage implements PublishPageInterface
{
    public function __construct(
        private readonly PagePublicationAuthorizerInterface $authorizer,
        private readonly PageArtifactBuilderInterface $artifacts,
        private readonly PagePublisherInterface $publisher,
    ) {}

    public function publish(
        int $pageId,
        int $userId,
        ?int $expectedPageGeneration = null,
    ): PublishPageResult {
        if ($pageId <= 0 || $userId <= 0 || !$this->authorizer->canPublish($pageId, $userId)) {
            throw PagePublicationFailed::notAuthorized();
        }

        try {
            $candidate = $this->artifacts->buildForPage(
                $pageId,
                $userId,
                $expectedPageGeneration,
            );

            return $this->publisher->publish($candidate);
        } catch (StaleSourceGenerationException $exception) {
            throw PagePublicationFailed::staleSource($exception->scope(), $exception);
        }
    }
}
