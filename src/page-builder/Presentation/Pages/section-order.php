<?php
/**
 * Guarded form fields for the main section list.
 *
 * @var \WP_Post $post
 * @var int $workingGeneration
 * @var array<int, array{id: string, index: int, name: string}> $sectionRows
 */

use UncannyPageBuilder\Infrastructure\WordPress\SectionOrderMetaBox;

defined('ABSPATH') || exit;

wp_nonce_field(
    SectionOrderMetaBox::nonceActionForPage($post->ID),
    SectionOrderMetaBox::nonceKey(),
);
?>
<input
    type="hidden"
    id="upb-section-order-ids"
    name="<?php echo esc_attr(SectionOrderMetaBox::orderField()); ?>"
    value="<?php echo esc_attr(implode(',', array_map(
        static fn(array $row): string => $row['id'],
        $sectionRows,
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
