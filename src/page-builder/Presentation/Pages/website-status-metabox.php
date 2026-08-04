<?php
/**
 * Website status meta box — working draft versus exact published pointer.
 *
 * @var \WP_Post $post
 * @var \UncannyPageBuilder\Domain\Publishing\PageLiveState $pageLiveStatus
 * @var \UncannyPageBuilder\Application\Rendering\PublishedPageReadResult $publicationRead
 */

defined('ABSPATH') || exit;

$statusLabel = $pageLiveStatus->label();
$hasPublicationIssue = isset($publicationRead)
    && $publicationRead instanceof \UncannyPageBuilder\Application\Rendering\PublishedPageReadResult
    && $publicationRead->status()->requiresOperatorAttentionForManagedPage();

?>
<p>
    <?php echo esc_html_x('Website status', 'Page Builder', 'uncanny-automator'); ?>:
    <strong><?php echo esc_html($statusLabel); ?></strong>
</p>

<?php if ($hasPublicationIssue): ?>
    <?php
    $diagnosticCode = $publicationRead->diagnosticCode() !== ''
        ? $publicationRead->diagnosticCode()
        : $publicationRead->status()->value;
    ?>
    <div class="notice notice-warning inline">
        <p><strong><?php echo esc_html_x('This page needs attention.', 'Page Builder', 'uncanny-automator'); ?></strong></p>
        <p>
            <?php echo esc_html_x(
                'Visitors are seeing the preserved WordPress page while Page Builder protects the published version. Make sure Page Builder is up to date, then open the Manual editor and publish changes again.',
                'Page Builder',
                'uncanny-automator',
            ); ?>
        </p>
        <p class="description">
            <?php
            echo esc_html(sprintf(
                /* translators: %s: Diagnostic code returned by publication check. */
                _x('If this keeps happening, share error code %s with support.', 'Page Builder', 'uncanny-automator'),
                $diagnosticCode,
            ));
            ?>
        </p>
    </div>
<?php endif; ?>

<p class="description">
    <?php if ($hasPublicationIssue): ?>
        <?php echo esc_html_x('Page Builder could not verify the current published version.', 'Page Builder', 'uncanny-automator'); ?>
    <?php elseif ($pageLiveStatus === \UncannyPageBuilder\Domain\Publishing\PageLiveState::Draft): ?>
        <?php echo esc_html_x('This working draft has not been published.', 'Page Builder', 'uncanny-automator'); ?>
    <?php elseif ($pageLiveStatus === \UncannyPageBuilder\Domain\Publishing\PageLiveState::ChangesNotLive): ?>
        <?php echo esc_html_x('The live page still uses its published artifact. Open the Manual editor to review and update it.', 'Page Builder', 'uncanny-automator'); ?>
    <?php else: ?>
        <?php echo esc_html_x('The live page matches the current saved working draft.', 'Page Builder', 'uncanny-automator'); ?>
    <?php endif; ?>
</p>
