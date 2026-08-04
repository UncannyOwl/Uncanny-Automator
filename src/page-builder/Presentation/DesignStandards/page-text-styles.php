<?php
/**
 * Page-scoped Text styles meta box template.
 *
 * Renders the same text-styles partials as the sitewide Typography settings
 * page, scoped to one page. Values are the page's sparse overrides.
 * Placeholders and previews show the inherited site values. Clearing a field
 * returns it to "inherit". This template renders inside the post edit form and
 * saves through the page's Update button.
 *
 * @var \WP_Post $post
 * @var string   $nonceKey
 * @var string   $nonceValue
 * @var array<string, array<string, string>> $typographyRoles    Page override values (sparse)
 * @var array<string, array<string, string>> $typographyDefaults Site-effective values (inherit targets)
 * @var array<int, array{key: string, label: string, description: string, preview: string, fields: array<int, array{key: string, label: string, control: string}>}> $roleDefinitions
 * @var array<int, array{key: string, label: string, options: array<int, array{label: string, value: string, source: string}>}> $fontFamilyCatalog
 * @var array<int, array{
 *     key: string,
 *     label: string,
 *     value: string,
 *     default: string,
 *     control: string,
 *     isColor: bool,
 *     options?: array<int, array{value: string, label: string}>
 * }> $linkFields
 * @var string[] $lockedTypographyKeys
 * @var string[] $lockedTokenKeys
 */

defined('ABSPATH') || exit;

$containerId = 'upb-page-text-styles';
$inheritMode = true;
$typographyNamePattern = 'upb_ds_typography[roles][%1$s][%2$s]';
$tokenNamePattern = 'upb_ds_token[%s]';
$resetButtonLabel = _x('Use site default', 'Page Builder', 'uncanny-automator');

?>
<div id="<?php echo esc_attr($containerId); ?>" class="upb-settings-panel upb-typography-settings upb-page-style-overrides">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonceKey); ?>"
        value="<?php echo esc_attr($nonceValue); ?>"
    />

    <p class="upb-settings-description">
        <?php echo esc_html_x('Only change these if this page needs different text styles from your site defaults. Empty fields use your site brand styles.', 'Page Builder', 'uncanny-automator'); ?>
    </p>

    <?php include __DIR__ . '/../Settings/partials/text-styles-fields.php'; ?>

    <?php include __DIR__ . '/../Settings/partials/text-styles-script.php'; ?>
</div>
