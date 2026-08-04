<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\EditorLock\RefreshEditorOwnership;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipState;
use UncannyPageBuilder\Domain\EditorLock\EditorOwnershipStatus;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Heartbeat projection for Page Builder editor ownership.
 *
 * WordPress core refreshes the same `_edit_lock`; this filter runs afterward
 * and replaces the response with the stricter Page Builder validity rules.
 */
final class EditorLockHeartbeat
{
    public function __construct(
        private readonly RefreshEditorOwnership $refreshOwnership,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
        private readonly SectionRepositoryInterface $sectionRepository,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly SupportsPostTypeUseCase $supportsPostType = new SupportsPostTypeUseCase(),
    ) {}

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function refresh(array $response, array $data, string $screenId): array
    {
        unset($screenId);

        $received = $data['wp-refresh-post-lock'] ?? null;
        if (!is_array($received)) {
            return $response;
        }

        $postId = absint($received['post_id'] ?? 0);
        $post = $postId > 0 ? get_post($postId) : null;
        if (
            !$post instanceof \WP_Post
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
            || !current_user_can('edit_post', $postId)
            || !$this->isEditableTarget($post)
        ) {
            return $response;
        }

        $state = $this->refreshOwnership->execute(
            $postId,
            (int) get_current_user_id(),
            (string) ($received['lock'] ?? ''),
        );

        try {
            $sourceGeneration = $this->sourceGeneration($post);
        } catch (\Throwable) {
            $sourceGeneration = -1;
            $this->logSourceGenerationUnavailable($postId, $post->post_type);

            /*
             * A generation lookup failure must not erase a competing owner
             * that was already confirmed by the lock store. Owned/available
             * states still degrade because safe resume requires both facts.
             */
            if ($state->status() !== EditorOwnershipStatus::Blocked) {
                $state = EditorOwnershipState::unavailable('source generation unavailable');
            }
        }

        $response['wp-refresh-post-lock'] = $this->coreCompatibleResponse($state);
        $response['upb-editor-lock'] = [
            'status'            => $state->status()->value,
            'source_generation' => $sourceGeneration,
        ];

        if ($state->status() === EditorOwnershipStatus::Unavailable) {
            $this->logUnavailable($postId, $post->post_type);
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function coreCompatibleResponse(EditorOwnershipState $state): array
    {
        if ($state->status() === EditorOwnershipStatus::Owned && $state->token() !== null) {
            return ['new_lock' => $state->token()->raw()];
        }

        if ($state->status() === EditorOwnershipStatus::Blocked && $state->owner() !== null) {
            $owner = $state->owner();

            return [
                'lock_error' => [
                    'name'       => $owner->displayName(),
                    'text'       => sprintf(
                        /* translators: %s: display name of the current editor owner. */
                        _x('%s has taken over and is currently editing.', 'Page Builder', 'uncanny-automator'),
                        $owner->displayName(),
                    ),
                    'avatar_src' => $owner->avatarUrl(),
                ],
            ];
        }

        return [];
    }

    private function sourceGeneration(\WP_Post $post): int
    {
        return $post->post_type === 'upb_global_part'
            ? $this->sourceGenerations->globalGeneration()
            : $this->sourceGenerations->pageGeneration((int) $post->ID);
    }

    private function isEditableTarget(\WP_Post $post): bool
    {
        return $post->post_type === 'upb_global_part'
            || (
                $this->supportsPostType->isSupported($post->post_type)
                && $this->sectionRepository->isOwnedPage((int) $post->ID)
            );
    }

    private function logUnavailable(int $postId, string $scope): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Editor lock refresh unavailable target=%d actor=%d scope=%s reason=ownership_unavailable',
            $postId,
            (int) get_current_user_id(),
            $scope,
        ));
    }

    private function logSourceGenerationUnavailable(int $postId, string $scope): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] Editor lock refresh unavailable target=%d actor=%d scope=%s reason=source_generation_unavailable',
            $postId,
            (int) get_current_user_id(),
            $scope,
        ));
    }
}
