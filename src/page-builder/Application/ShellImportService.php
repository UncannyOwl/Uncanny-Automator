<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Domain\Exception\ShellHtmlTooLargeException;
use UncannyPageBuilder\Domain\Shell\ShellImportAnalysis;
use UncannyPageBuilder\Infrastructure\Shell\RenderedShellAnalyzer;

final class ShellImportService
{
    public const MAX_ANALYZE_HTML_BYTES = 262144;

    public function __construct(
        private readonly RenderedShellAnalyzer $analyzer,
        private readonly GlobalPartService $globalPartService,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    /**
     * Analyze rendered header/footer HTML.
     *
     * @return array{header: ?array<string, mixed>, footer: ?array<string, mixed>}
     */
    public function analyze(?string $headerHtml, ?string $footerHtml): array
    {
        $this->assertAnalyzeHtmlWithinLimit($headerHtml, 'header_html');
        $this->assertAnalyzeHtmlWithinLimit($footerHtml, 'footer_html');

        $header = null;
        $footer = null;

        if ($headerHtml !== null && trim($headerHtml) !== '') {
            $header = $this->analyzer->analyzeHeader($headerHtml)->toArray();
        }

        if ($footerHtml !== null && trim($footerHtml) !== '') {
            $footer = $this->analyzer->analyzeFooter($footerHtml)->toArray();
        }

        return ['header' => $header, 'footer' => $footer];
    }

    private function assertAnalyzeHtmlWithinLimit(?string $html, string $field): void
    {
        if ($html === null || trim($html) === '') {
            return;
        }

        $size = strlen($html);
        if ($size > self::MAX_ANALYZE_HTML_BYTES) {
            throw new ShellHtmlTooLargeException($field, $size, self::MAX_ANALYZE_HTML_BYTES);
        }
    }

    /**
     * Import analyzed shell as clean Uncanny global parts.
     *
     * @param ?array<string, mixed> $headerAnalysis  Output of analyze()['header']
     * @param ?array<string, mixed> $footerAnalysis  Output of analyze()['footer']
     * @return array{header_id: ?int, footer_id: ?int, warnings: string[]}
     */
    public function import(?array $headerAnalysis, ?array $footerAnalysis): array
    {
        $headerId = null;
        $footerId = null;
        $warnings = [];
        $header = null;
        $footer = null;

        if ($headerAnalysis !== null) {
            $header = ShellImportAnalysis::fromArray($headerAnalysis);
            if ($header->type !== 'header') {
                throw new \InvalidArgumentException('Header analysis type must be header.');
            }
            $warnings = array_merge($warnings, $header->warnings);
        }

        if ($footerAnalysis !== null) {
            $footer = ShellImportAnalysis::fromArray($footerAnalysis);
            if ($footer->type !== 'footer') {
                throw new \InvalidArgumentException('Footer analysis type must be footer.');
            }
            $warnings = array_merge($warnings, $footer->warnings);
        }

        if ($header !== null) {
            $result = $this->globalPartService->createOrReplace(
                'Imported Header',
                [
                    'name'    => 'Imported Header',
                    'content' => [
                        'html' => $this->buildHeaderHtml($header),
                        'css'  => $this->buildHeaderCss(),
                    ],
                ],
                'header',
            );
            $headerId = $result['id'];
            $warnings = array_merge($warnings, (array) ($result['warnings'] ?? []));
        }

        if ($footer !== null) {
            try {
                $result = $this->globalPartService->createOrReplace(
                    'Imported Footer',
                    [
                        'name'    => 'Imported Footer',
                        'content' => [
                            'html' => $this->buildFooterHtml($footer),
                            'css'  => $this->buildFooterCss(),
                        ],
                    ],
                    'footer',
                );
                $footerId = $result['id'];
                $warnings = array_merge($warnings, (array) ($result['warnings'] ?? []));
            } catch (\Throwable $failure) {
                if ($headerId === null) {
                    throw $failure;
                }

                // The header exists. Return its ID and do not retry the full import.
                try {
                    $this->failureReporter?->report(
                        'shell import',
                        $headerId,
                        'footer.create',
                        $failure,
                    );
                } catch (\Throwable) {
                    // A report failure cannot change the completed header result.
                }
                $warnings[] = 'The header was saved, but Page Builder could not confirm the footer import. Inspect the reusables before another import.';
            }
        }

        return [
            'header_id' => $headerId,
            'footer_id' => $footerId,
            'warnings'  => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Build clean header HTML from analysis. Zero raw theme markup copied.
     */
    private function buildHeaderHtml(ShellImportAnalysis $analysis): string
    {
        $logoContent = htmlspecialchars($analysis->logoText ?? 'Site', ENT_QUOTES, 'UTF-8');
        $logoUrl = self::safeUrl($analysis->logoUrl, true);
        if ($logoUrl !== null) {
            $src = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
            $logoContent = '<img src="' . $src . '" alt="' . $logoContent . '" class="upb-import-logo-img" />';
        }

        $navHtml = '';
        foreach (array_slice($analysis->navLinks, 0, self::maxNavLinks()) as $i => $link) {
            $hrefValue = self::safeUrl($link['href'] ?? null);
            if ($hrefValue === null) {
                continue;
            }

            $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
            $href  = htmlspecialchars($hrefValue, ENT_QUOTES, 'UTF-8');
            $navHtml .= '      <a href="' . $href . '" class="upb-import-nav-link">' . $label . '</a>' . "\n";
        }

        $ctaHtml = '';
        if (!empty($analysis->ctaLinks)) {
            $cta   = $analysis->ctaLinks[0];
            $hrefValue = self::safeUrl($cta['href'] ?? null);
            if ($hrefValue !== null) {
                $label = htmlspecialchars($cta['label'], ENT_QUOTES, 'UTF-8');
                $href  = htmlspecialchars($hrefValue, ENT_QUOTES, 'UTF-8');
                $ctaHtml = '    <a href="' . $href . '" class="upb-import-cta">' . $label . '</a>';
            }
        }

        return <<<HTML
<header class="upb-import-header">
  <div class="upb-import-header-inner">
    <a href="/" class="upb-import-logo">{$logoContent}</a>
    <nav class="upb-import-nav">
{$navHtml}    </nav>
{$ctaHtml}
  </div>
</header>
HTML;
    }

    private function buildHeaderCss(): string
    {
        return <<<CSS
.upb-import-header {
  background: var(--bs-body-bg, #ffffff);
  border-bottom: 1px solid var(--bs-border-color, #e0e0e0);
  padding: 0 24px;
}
.upb-import-header-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 64px;
}
.upb-import-logo {
  font-family: var(--bs-font-sans-serif, sans-serif);
  font-size: 20px;
  font-weight: 700;
  color: var(--bs-primary, #1a1a1a);
  text-decoration: none;
  display: flex;
  align-items: center;
}
.upb-import-logo-img {
  max-height: 40px;
  width: auto;
}
.upb-import-nav {
  display: flex;
  gap: 24px;
}
.upb-import-nav-link {
  font-family: var(--bs-body-font-family, sans-serif);
  font-size: 14px;
  color: var(--bs-body-color, #555555);
  text-decoration: none;
  transition: color 0.15s;
}
.upb-import-nav-link:hover {
  color: var(--bs-primary, #1a1a1a);
}
.upb-import-cta {
  display: inline-block;
  padding: 8px 20px;
  background: var(--bs-primary, #3366ff);
  color: var(--bs-white, #ffffff);
  font-family: var(--bs-body-font-family, sans-serif);
  font-size: 14px;
  font-weight: 600;
  border-radius: var(--bs-border-radius, 6px);
  text-decoration: none;
  transition: opacity 0.15s;
}
.upb-import-cta:hover {
  opacity: 0.9;
}
CSS;
    }

    /**
     * Build clean footer HTML from analysis.
     *
     * Always produces a standardized 3-column layout (brand, links, social/connect).
     * The detected footerColumns count is analysis-only metadata for the preview UI
     * — the rebuild intentionally does not vary its structure based on source column count.
     */
    private function buildFooterHtml(ShellImportAnalysis $analysis): string
    {
        $brand = htmlspecialchars($analysis->logoText ?? 'Site', ENT_QUOTES, 'UTF-8');

        $linksHtml = '';
        foreach (array_slice($analysis->navLinks, 0, 6) as $i => $link) {
            $hrefValue = self::safeUrl($link['href'] ?? null);
            if ($hrefValue === null) {
                continue;
            }

            $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
            $href  = htmlspecialchars($hrefValue, ENT_QUOTES, 'UTF-8');
            $linksHtml .= '      <a href="' . $href . '" class="upb-import-footer-link">' . $label . '</a>' . "\n";
        }

        $socialHtml = '';
        foreach (array_slice($analysis->socialLinks, 0, 4) as $i => $url) {
            $hrefValue = self::safeUrl($url);
            if ($hrefValue === null) {
                continue;
            }

            $href  = htmlspecialchars($hrefValue, ENT_QUOTES, 'UTF-8');
            $label = $this->socialLabelFromUrl($hrefValue);
            $socialHtml .= '      <a href="' . $href . '" class="upb-import-footer-link">' . $label . '</a>' . "\n";
        }

        $copyright = htmlspecialchars(
            $analysis->copyrightText ?? '&copy; ' . date('Y') . ' ' . $brand . '. All rights reserved.',
            ENT_QUOTES,
            'UTF-8',
        );

        return <<<HTML
<footer class="upb-import-footer">
  <div class="upb-import-footer-inner">
    <div class="upb-import-footer-col">
      <div class="upb-import-footer-brand">{$brand}</div>
    </div>
    <div class="upb-import-footer-col">
      <div class="upb-import-footer-heading">Links</div>
{$linksHtml}    </div>
    <div class="upb-import-footer-col">
      <div class="upb-import-footer-heading">Connect</div>
{$socialHtml}    </div>
  </div>
  <div class="upb-import-footer-bottom">
    <p class="upb-import-footer-copyright">{$copyright}</p>
  </div>
</footer>
HTML;
    }

    private function buildFooterCss(): string
    {
        return <<<CSS
.upb-import-footer {
  background: var(--bs-dark, #1a1a2e);
  color: var(--bs-light, #e0e0e0);
  padding: 48px 24px 24px;
}
.upb-import-footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 32px;
}
.upb-import-footer-brand {
  font-family: var(--bs-font-sans-serif, sans-serif);
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}
.upb-import-footer-heading {
  font-family: var(--bs-font-sans-serif, sans-serif);
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.upb-import-footer-link {
  display: block;
  font-family: var(--bs-body-font-family, sans-serif);
  font-size: 14px;
  color: var(--bs-light, #e0e0e0);
  text-decoration: none;
  opacity: 0.7;
  margin-bottom: 8px;
  transition: opacity 0.15s;
}
.upb-import-footer-link:hover {
  opacity: 1;
}
.upb-import-footer-bottom {
  max-width: 1200px;
  margin: 32px auto 0;
  padding-top: 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  text-align: center;
}
.upb-import-footer-copyright {
  font-family: var(--bs-body-font-family, sans-serif);
  font-size: 13px;
  opacity: 0.5;
  margin: 0;
}
CSS;
    }

    private function socialLabelFromUrl(string $url): string
    {
        $map = [
            'facebook.com'  => 'Facebook',
            'twitter.com'   => 'Twitter',
            'x.com'         => 'X',
            'instagram.com' => 'Instagram',
            'linkedin.com'  => 'LinkedIn',
            'youtube.com'   => 'YouTube',
            'github.com'    => 'GitHub',
            'tiktok.com'    => 'TikTok',
            'pinterest.com' => 'Pinterest',
            'reddit.com'    => 'Reddit',
        ];

        foreach ($map as $domain => $label) {
            if (str_contains($url, $domain)) {
                return $label;
            }
        }

        return 'Social';
    }

    private static function maxNavLinks(): int
    {
        return 8;
    }

    private static function safeUrl(mixed $url, bool $imageUrl = false): ?string
    {
        if (!is_string($url)) {
            return null;
        }

        $url = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '');
        if ($url === '') {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $scheme) === 1) {
            $scheme = strtolower($scheme[1]);
            if ($scheme !== 'http' && $scheme !== 'https') {
                return null;
            }
        }

        if ($imageUrl && !preg_match('#^(?:https?://|/)#i', $url)) {
            return null;
        }

        return $url;
    }
}
