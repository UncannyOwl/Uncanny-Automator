<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\SourcePackage\PageSourceArchiveDownloadUrlInterface;

final class WordPressPageSourceArchiveDownloadUrl implements PageSourceArchiveDownloadUrlInterface
{
    public function forPage(int $pageId, string $artifactToken): string
    {
        if ($pageId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $artifactToken)) {
            throw new \InvalidArgumentException('A page archive download token is required.');
        }

        $url = add_query_arg(
            'action',
            PageSourceArchiveDownloadAction::ACTION,
            admin_url('admin-post.php'),
        );
        $url = add_query_arg('page_id', (string) $pageId, $url);

        // wp_nonce_url() returns HTML-escaped separators, which are correct in
        // markup but become literal "amp;page_id" keys in a JSON control
        // response. Build the raw transport URL explicitly.
        $url = add_query_arg(
            '_wpnonce',
            wp_create_nonce(PageSourceArchiveDownloadAction::nonceAction($pageId)),
            $url,
        );

        return add_query_arg('artifact', $artifactToken, $url);
    }
}
