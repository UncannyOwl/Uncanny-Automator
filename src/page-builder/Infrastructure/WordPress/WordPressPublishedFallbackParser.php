<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Publishing\PageDeactivationFallback;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Parses only the versioned fallback block emitted by the composer.
 */
final class WordPressPublishedFallbackParser
{
    public const SEPARATOR = "\n\n";

    private const START = '<!-- wp:html -->' . "\n";

    private const END = "\n" . '<!-- /wp:html -->';

    public function parse(string $postContent): ?WordPressPublishedFallbackContent
    {
        $hasRelatedSignal = str_contains($postContent, 'data-uncanny-page-builder-artifact=')
            || str_contains($postContent, 'data-upb-fallback-')
            || str_contains($postContent, 'data-upb-artifact-')
            || str_contains($postContent, 'data-upb-shell-mode=');

        if (!$hasRelatedSignal) {
            return null;
        }

        $pattern = '~(?<block>'
            . preg_quote(self::START, '~')
            . '<div data-uncanny-page-builder-artifact="1"'
            . ' data-upb-fallback-version="(?<version>[1-9][0-9]*)"'
            . ' data-upb-artifact-id="(?<artifact_id>[1-9][0-9]*)"'
            . ' data-upb-artifact-hash="(?<artifact_hash>[a-f0-9]{64})"'
            . ' data-upb-fallback-hash="(?<fallback_hash>[a-f0-9]{64})"'
            . ' data-upb-shell-mode="(?<shell_mode>[a-z_]+)"'
            . ' data-upb-fallback-suffix-hash="(?<suffix_hash>[a-f0-9]{64})">'
            . '(?<payload>[\s\S]*)'
            . '</div>'
            . preg_quote(self::END, '~')
            . ')\z~D';

        if (preg_match($pattern, $postContent, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            throw new InvalidPublishedFallbackContent('The Page Builder fallback is malformed or is not the trailing WordPress content.');
        }

        $blockOffset = (int) $matches['block'][1];
        if ($blockOffset === 0) {
            $originalContent = '';
        } else {
            if ($blockOffset < strlen(self::SEPARATOR)) {
                throw new InvalidPublishedFallbackContent('The Page Builder fallback separator is invalid.');
            }
            $separatorOffset = $blockOffset - strlen(self::SEPARATOR);
            if (substr($postContent, $separatorOffset, strlen(self::SEPARATOR)) !== self::SEPARATOR) {
                throw new InvalidPublishedFallbackContent('The Page Builder fallback separator is invalid.');
            }
            $originalContent = substr($postContent, 0, $separatorOffset);
        }
        if (
            str_contains($originalContent, 'data-uncanny-page-builder-artifact=')
            || str_contains($originalContent, 'data-upb-fallback-')
            || str_contains($originalContent, 'data-upb-artifact-')
            || str_contains($originalContent, 'data-upb-shell-mode=')
        ) {
            throw new InvalidPublishedFallbackContent('The WordPress page body contains duplicate or partial Page Builder fallbacks.');
        }

        $version = (int) $matches['version'][0];
        if ($version !== PageDeactivationFallback::FORMAT_VERSION) {
            throw new InvalidPublishedFallbackContent('The Page Builder fallback format is not supported.');
        }

        $shellMode = ShellMode::tryFrom((string) $matches['shell_mode'][0]);
        if (!$shellMode instanceof ShellMode || $shellMode === ShellMode::None) {
            throw new InvalidPublishedFallbackContent('The Page Builder fallback shell mode is invalid.');
        }

        $signingContent = WordPressPublishedFallbackComposer::signingContent(
            $version,
            (int) $matches['artifact_id'][0],
            (string) $matches['artifact_hash'][0],
            (string) $matches['fallback_hash'][0],
            $shellMode,
            (string) $matches['payload'][0],
        );
        $suffixHash = (string) $matches['suffix_hash'][0];
        if (!hash_equals($suffixHash, hash('sha256', $signingContent))) {
            throw new InvalidPublishedFallbackContent('The Page Builder fallback checksum is invalid.');
        }

        return new WordPressPublishedFallbackContent(
            originalContent: $originalContent,
            formatVersion: $version,
            artifactId: (int) $matches['artifact_id'][0],
            artifactHash: (string) $matches['artifact_hash'][0],
            fallbackHash: (string) $matches['fallback_hash'][0],
            shellMode: $shellMode,
            suffixHash: $suffixHash,
        );
    }

    /**
     * Recognize only the complete unversioned body emitted by older releases.
     */
    public function isLegacyArtifact(string $postContent): bool
    {
        return preg_match(
            '/\A\s*<!-- wp:html -->\s*<div\b[^>]*\bdata-uncanny-page-builder-artifact="1"[^>]*>[\s\S]*<\/div>\s*<!-- \/wp:html -->\s*\z/',
            $postContent,
        ) === 1
            && !str_contains($postContent, 'data-upb-fallback-')
            && !str_contains($postContent, 'data-upb-artifact-')
            && !str_contains($postContent, 'data-upb-shell-mode=');
    }
}
