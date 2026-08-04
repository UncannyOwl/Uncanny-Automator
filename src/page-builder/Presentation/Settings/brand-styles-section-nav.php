<?php
/**
 * Brand styles subsection navigation.
 *
 * @var string $activeDesignSection
 * @var array<int, array{id: string, label: string, url: string}> $designSections
 */

defined('ABSPATH') || exit;

?>
<section class="upb-sub-section">
    <nav class="upb-sub-section__nav" aria-label="<?php echo esc_attr_x('Brand styles sections', 'Page Builder', 'uncanny-automator'); ?>">
        <ul>
            <?php foreach ($designSections as $section): ?>
                <?php $isActive = $section['id'] === $activeDesignSection; ?>
                <li>
                    <a
                        href="<?php echo esc_url($section['url']); ?>"
                        class="<?php echo $isActive ? 'active' : ''; ?>"
                        <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo esc_html($section['label']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</section>
