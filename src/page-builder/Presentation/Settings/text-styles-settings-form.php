<?php
/**
 * Plain WordPress text styles settings form.
 *
 * Thin wrapper around the shared text-styles partials: this file owns the
 * global <form>, nonce, and submit button; the field markup and
 * behavior script are shared with the page-level overrides metabox.
 *
 * @var bool $updated
 * @var string $error
 * @var string $warning
 * @var array<string, array<string, string>> $typographyRoles
 * @var array<string, array<string, string>> $typographyDefaults
 * @var array<int, array{
 *     key: string,
 *     label: string,
 *     value: string,
 *     default: string,
 *     control: string,
 *     isColor: bool,
 *     options?: array<int, array{value: string, label: string}>
 * }> $linkFields
 * @var array<int, array{key: string, label: string, description: string, preview: string, fields: array<int, array{key: string, label: string, control: string}>}> $roleDefinitions
 * @var array<int, array{key: string, label: string, options: array<int, array{label: string, value: string, source: string}>}> $fontFamilyCatalog
 * @var array{name: string, value: string} $nonce
 */

defined('ABSPATH') || exit;

$containerId = 'uncanny-page-builder-text-styles-form';

/*
 * Each text style group is its own sidebar section, so only the active group
 * renders. Saving posts just that group's roles; the branding page merges them
 * over the stored profile so the other sections keep their values.
 *
 * @var string $activeSection
 */
$activeSection = isset($activeSection) && '' !== $activeSection ? $activeSection : 'headings';

$sectionTitles = [
    'headings' => _x('Headings', 'Page Builder', 'uncanny-automator'),
    'body' => _x('Body text', 'Page Builder', 'uncanny-automator'),
    'small-text' => _x('Small text', 'Page Builder', 'uncanny-automator'),
    'navigation' => _x('Navigation & buttons', 'Page Builder', 'uncanny-automator'),
    'links' => _x('Links', 'Page Builder', 'uncanny-automator'),
];

$sectionDescriptions = [
    'headings' => _x('Fonts, sizes, weights, and spacing for titles and section headings.', 'Page Builder', 'uncanny-automator'),
    'body' => _x('Fonts, sizes, weights, and spacing for paragraphs and main page text.', 'Page Builder', 'uncanny-automator'),
    'small-text' => _x('Fonts, sizes, weights, and spacing for captions, labels, and code.', 'Page Builder', 'uncanny-automator'),
    'navigation' => _x('Fonts, sizes, weights, and spacing for navigation and buttons.', 'Page Builder', 'uncanny-automator'),
    'links' => _x('Link color, hover color, and underline style.', 'Page Builder', 'uncanny-automator'),
];

$sectionTitle = $sectionTitles[$activeSection] ?? $sectionTitles['headings'];
$sectionDescription = $sectionDescriptions[$activeSection] ?? $sectionDescriptions['headings'];

?>
<form method="post" id="<?php echo esc_attr($containerId); ?>">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonce['name']); ?>"
        value="<?php echo esc_attr($nonce['value']); ?>"
    />

    <div class="uap-settings-panel upb-typography-settings">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html($sectionTitle); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($updated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('Text style settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <uo-alert type="error"><?php echo esc_html($error); ?></uo-alert>
                <?php endif; ?>

                <?php if ($warning !== ''): ?>
                    <uo-alert type="warning"><?php echo esc_html($warning); ?></uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html($sectionDescription); ?>
                </p>

                <?php
                // Groups are sidebar sections here, so the partial renders only the active one.
                $onlyTypographyGroup = $activeSection;
                // This screen loads Automator's bundle, so its components work here.
                $useAutomatorComponents = true;
                include __DIR__ . '/partials/text-styles-fields.php';
                ?>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit">
                    <?php echo esc_html_x('Save text styles', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/partials/text-styles-script.php'; ?>
