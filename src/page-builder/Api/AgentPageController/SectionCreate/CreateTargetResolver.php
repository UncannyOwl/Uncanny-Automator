<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\SectionCreate;

/**
 * Resolves whether create_section targets a page or a reusable global part.
 */
final class CreateTargetResolver
{
    public function globalPartId(\WP_REST_Request $request, int $pageId): int
    {
        $globalPartId = $this->requestGlobalPartId($request);
        if ($this->isPublishedGlobalPartPostId($globalPartId)) {
            return $globalPartId;
        }

        return $this->isPublishedGlobalPartPostId($pageId) ? $pageId : 0;
    }

    private function requestGlobalPartId(\WP_REST_Request $request): int
    {
        $requestId = \absint($request->get_param('global_part_id'));
        if ($requestId > 0) {
            return $requestId;
        }

        $context = $request->get_param('page_builder_context');
        if (\is_array($context)) {
            return \absint($context['global_part_id'] ?? 0);
        }

        return 0;
    }

    private function isPublishedGlobalPartPostId(int $postId): bool
    {
        return $postId > 0
            && \get_post_type($postId) === 'upb_global_part'
            && \get_post_status($postId) === 'publish';
    }
}
