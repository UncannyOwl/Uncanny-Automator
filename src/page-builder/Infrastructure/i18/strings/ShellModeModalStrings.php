<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class ShellModeModalStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            /* translators: %s: Number of detected footer columns. */
            'analysis_columns' => _x('%s columns', 'Page Builder', 'uncanny-automator'),
            'analysis_copyright_detected' => _x('Copyright detected', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Number of detected header CTA buttons. */
            'analysis_cta_buttons' => _x('%s CTA button(s)', 'Page Builder', 'uncanny-automator'),
            'analysis_detected_elements' => _x('We detected the following elements:', 'Page Builder', 'uncanny-automator'),
            'analysis_footer' => _x('Footer', 'Page Builder', 'uncanny-automator'),
            'analysis_header' => _x('Header', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Logo text detected in the header. */
            'analysis_logo' => _x('Logo: %s', 'Page Builder', 'uncanny-automator'),
            'analysis_logo_image_detected' => _x('Logo image detected', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Number of detected navigation links. */
            'analysis_nav_links' => _x('%s nav links', 'Page Builder', 'uncanny-automator'),
            /* translators: %s: Number of detected social links. */
            'analysis_social_links' => _x('%s social link(s)', 'Page Builder', 'uncanny-automator'),
            'analysis_unsupported_patterns' => _x('Some parts may look different after import:', 'Page Builder', 'uncanny-automator'),
            'analysis_failed' => _x('We couldn\'t review the theme header and footer. Start the import again.', 'Page Builder', 'uncanny-automator'),
            'busy_updating_page_setup' => _x('Updating your page setup…', 'Page Builder', 'uncanny-automator'),
            'button_cancel' => _x('Cancel', 'Page Builder', 'uncanny-automator'),
            'button_import_confirm' => _x('Import & switch to Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
            'choose_build_entire_page' => _x('Build the entire page with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
            'choose_build_entire_page_desc' => _x('Uncanny Agent creates the complete page, including the header and footer.', 'Page Builder', 'uncanny-automator'),
            'choose_change_later' => _x('You can change this later.', 'Page Builder', 'uncanny-automator'),
            'choose_recommended' => _x('Recommended', 'Page Builder', 'uncanny-automator'),
            'choose_use_existing_shell' => _x('Use my theme header and footer', 'Page Builder', 'uncanny-automator'),
            'choose_use_existing_shell_desc' => _x('Use your theme\'s current header and footer while Uncanny Agent builds the page content. Because your theme controls some styles, the final design may look slightly different than in the preview.', 'Page Builder', 'uncanny-automator'),
            'import_analyzing' => _x('Analyzing your current header and footer...', 'Page Builder', 'uncanny-automator'),
            'import_failed' => _x('We couldn\'t import the theme header and footer. Your page setup is unchanged; try again.', 'Page Builder', 'uncanny-automator'),
            'native_intro' => _x('Your site already has a saved header and footer in Uncanny Page Builder. You can use them on this page or start this page without them.', 'Page Builder', 'uncanny-automator'),
            'native_standalone' => _x('Stand-alone page. No header and footer.', 'Page Builder', 'uncanny-automator'),
            'native_standalone_desc' => _x('Start this page without a saved header or footer. You can choose them later.', 'Page Builder', 'uncanny-automator'),
            'native_use_saved_parts' => _x('Use the saved default header and default footer', 'Page Builder', 'uncanny-automator'),
            'native_use_saved_parts_desc' => _x('Use the header and footer you have already created in Uncanny Page Builder.', 'Page Builder', 'uncanny-automator'),
            'setup_update_failed' => _x('We couldn\'t update the page setup. No partial change was kept; try again.', 'Page Builder', 'uncanny-automator'),
            'shell_not_found' => _x('We couldn\'t find a theme header or footer on this page. Choose another page setup option.', 'Page Builder', 'uncanny-automator'),
            'title_choose' => _x('How should this page fit into your website?', 'Page Builder', 'uncanny-automator'),
            'title_import_preview' => _x('Import current shell into Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
            'title_native_setup' => _x('Set up your page header and footer', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
