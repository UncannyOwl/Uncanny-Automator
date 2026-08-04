<?php
/**
 * Plain WordPress personalization settings form.
 *
 * @var bool $updated
 * @var string $customInstructions
 * @var int $maxCharacters
 * @var string $fieldCustomInstructions
 * @var array{name: string, value: string} $nonce
 */

defined('ABSPATH') || exit;

?>
<form method="post" id="uncanny-page-builder-personalization-form">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonce['name']); ?>"
        value="<?php echo esc_attr($nonce['value']); ?>"
    />

    <div class="uap-settings-panel">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html_x('Design direction', 'Page Builder', 'uncanny-automator'); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($updated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('Design direction settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Set the design direction Uncanny Agent should follow when building your pages.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <div class="uap-spacing-top">
                    <div>
                        <label for="uncanny-page-builder-custom-instructions">
                            <strong><?php echo esc_html_x('Page design notes', 'Page Builder', 'uncanny-automator'); ?></strong>
                        </label>
                    </div>
                    <textarea
                        id="uncanny-page-builder-custom-instructions"
                        name="<?php echo esc_attr($fieldCustomInstructions); ?>"
                        class="uap-field-text uap-spacing-top--small"
                        rows="9"
                        data-upb-custom-instructions="true"
                        data-upb-max-characters="<?php echo esc_attr((string) $maxCharacters); ?>"
                        placeholder="<?php echo esc_attr_x('Example: Clean, modern, and minimal. Use large headings, generous spacing, simple sections, soft neutral colors, subtle borders, and minimal shadows. Avoid cluttered layouts, loud gradients, and overly busy designs.', 'Page Builder', 'uncanny-automator'); ?>"
                    ><?php echo esc_textarea($customInstructions); ?></textarea>
                    <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                        <?php echo esc_html_x('Include layout style, spacing, colors, typography, visual details, and anything Uncanny Agent should keep in mind when designing pages.', 'Page Builder', 'uncanny-automator'); ?>
                    </p>
                    <p
                        id="uncanny-page-builder-custom-instructions-count"
                        class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle"
                        data-upb-custom-instructions-count="true"
                        aria-live="polite"
                    ></p>
                </div>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit">
                    <?php echo esc_html_x('Save design preferences', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>

<script>
    (function () {
        var textarea = document.querySelector('[data-upb-custom-instructions="true"]');
        var counter = document.querySelector('[data-upb-custom-instructions-count="true"]');
        if (!textarea || !counter) {
            return;
        }

        var maxCharacters = Number(textarea.getAttribute('data-upb-max-characters') || textarea.maxLength || 2000);
        if (!isFinite(maxCharacters) || maxCharacters <= 0) {
            maxCharacters = 2000;
        }

        function syncCount() {
            var characters = Array.from(textarea.value);
            if (characters.length > maxCharacters) {
                characters = characters.slice(0, maxCharacters);
                textarea.value = characters.join('');
            }
            counter.textContent = characters.length + ' / ' + maxCharacters;
        }

        textarea.addEventListener('input', syncCount);
        syncCount();
    })();
</script>
