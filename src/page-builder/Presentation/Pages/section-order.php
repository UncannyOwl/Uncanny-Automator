<?php
/**
 * Section order meta box template.
 *
 * @var int $pageId
 * @var int $workingGeneration
 * @var \UncannyPageBuilder\Domain\Section\SectionCollection $sections
 */

use UncannyPageBuilder\Infrastructure\WordPress\SectionOrderMetaBox;

defined('ABSPATH') || exit;

if ($sections->count() === 0) : ?>
    <p class="description"><?php echo esc_html_x('No sections on this page yet.', 'Page Builder', 'uncanny-automator'); ?></p>
    <?php return; ?>
<?php endif; ?>

<?php
wp_nonce_field(
    SectionOrderMetaBox::nonceActionForPage($pageId),
    SectionOrderMetaBox::nonceKey(),
);
?>
<input
    type="hidden"
    id="upb-section-order-ids"
    name="<?php echo esc_attr(SectionOrderMetaBox::orderField()); ?>"
    value="<?php echo esc_attr(implode(',', array_map(
        static fn($section): int => (int) $section->id(),
        $sections->all(),
    ))); ?>"
/>
<input
    type="hidden"
    id="upb-section-order-changed"
    name="<?php echo esc_attr(SectionOrderMetaBox::changedField()); ?>"
    value="0"
/>
<input
    type="hidden"
    name="<?php echo esc_attr(SectionOrderMetaBox::generationField()); ?>"
    value="<?php echo esc_attr((string) $workingGeneration); ?>"
/>

<ul id="upb-section-order" data-page-id="<?php echo esc_attr((string) $pageId); ?>" style="margin: 0; padding: 0;">
    <?php foreach ($sections->all() as $section) :
        $sectionId = $section->id();
        $name = esc_html($section->name() ?: _x('(unnamed)', 'Page Builder', 'uncanny-automator'));
    ?>
        <li data-section-id="<?php echo esc_attr((string) $sectionId); ?>"
            style="padding: 8px 10px; margin: 4px 0; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; cursor: grab; display: flex; align-items: center; gap: 8px;">
            <span class="dashicons dashicons-menu" style="color: #8c8f94; flex-shrink: 0;"></span>
            <span style="flex: 1; font-size: 13px;"><?php echo $name; ?></span>
            <span style="color: #8c8f94; font-size: 11px;">#<?php echo esc_html((string) $sectionId); ?></span>
        </li>
    <?php endforeach; ?>
</ul>

<p class="description" style="margin-top: 8px;">
    <?php echo esc_html_x('Drag sections to reorder. Changes save when you update this page.', 'Page Builder', 'uncanny-automator'); ?>
</p>
<div id="upb-section-order-status" style="margin-top: 6px;"></div>
