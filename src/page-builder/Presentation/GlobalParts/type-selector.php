<?php
/**
 * Global part type selector meta box template.
 *
 * @var \UncannyPageBuilder\Domain\GlobalPart\GlobalPartType $current
 */

defined('ABSPATH') || exit;

wp_nonce_field($nonceAction, $nonceKey);
?>
<label for="upb_global_part_type" style="display:block;margin-bottom:6px;">
    <?php echo esc_html_x('Where should this reusable be used?', 'Page Builder', 'uncanny-automator'); ?>
</label>
<select name="upb_global_part_type" id="upb_global_part_type" style="width:100%;">
    <?php foreach (\UncannyPageBuilder\Domain\GlobalPart\GlobalPartType::validValues() as $value) : ?>
        <option value="<?php echo esc_attr($value); ?>" <?php selected($current->value, $value); ?>>
            <?php echo esc_html(ucfirst($value)); ?>
        </option>
    <?php endforeach; ?>
</select>
