<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\Export\PageJavaScriptExportRendererInterface;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Application\Rendering\PublicRuntimeAssetCatalog;
use UncannyPageBuilder\Application\Settings\ToolSettingsAccess;
use UncannyPageBuilder\Domain\Settings\ToolSettings;

/**
 * Renders Page Builder-owned custom JavaScript through explicit footer seams.
 *
 * The renderer only emits scripts saved through the dedicated JavaScript lane.
 * It never inspects section HTML for executable content.
 */
final class PageJavaScriptRuntimeRenderer implements PageJavaScriptExportRendererInterface
{
    private readonly WorkingJavaScriptSource $runtime;

    public function __construct(
        PageJavaScriptRuntimeService|WorkingJavaScriptSource $runtime,
        private readonly ?ToolSettingsAccess $toolSettingsAccess = null,
    ) {
        $this->runtime = $runtime instanceof WorkingJavaScriptSource
            ? $runtime
            : new WorkingJavaScriptSource($runtime);
    }

    /**
     * @param array<string, mixed>|null $headerData
     * @param array<string, mixed>|null $footerData
     */
    public function renderStandaloneCanvasScripts(int $postId, ?array $headerData = null, ?array $footerData = null): string
    {
        if (get_post_type($postId) === 'upb_global_part') {
            return $this->renderRuntimeBundle([
                $this->globalPartScriptTag($postId),
            ], $this->pluginLibraryPublicPaths());
        }

        return $this->renderRuntimeBundle([
            $this->globalPartScriptTag((int) ($headerData['post_id'] ?? 0)),
            $this->pageScriptTag($postId),
            $this->globalPartScriptTag((int) ($footerData['post_id'] ?? 0)),
        ], $this->pluginLibraryPublicPaths());
    }

    /**
     * Render one immutable page-source JavaScript value while keeping reusable
     * header/footer runtimes on their independent current lifecycle.
     *
     * @param array<string, mixed>|null $headerData
     * @param array<string, mixed>|null $footerData
     */
    public function renderStandaloneCanvasScriptsFromPageSource(
        int $pageId,
        string $pageJavaScript,
        ?array $headerData = null,
        ?array $footerData = null,
    ): string {
        return $this->renderRuntimeBundle([
            $this->globalPartScriptTag((int) ($headerData['post_id'] ?? 0)),
            $this->pageSourceScriptTag($pageId, $pageJavaScript),
            $this->globalPartScriptTag((int) ($footerData['post_id'] ?? 0)),
        ], $this->pluginLibraryPublicPaths());
    }

    public function renderThemeCompositionPageScript(int $pageId): string
    {
        return $this->renderRuntimeBundle([
            $this->pageScriptTag($pageId),
        ], $this->pluginLibraryPublicPaths());
    }

    /**
     * @param array<string, mixed>|null $headerData
     * @param array<string, mixed>|null $footerData
     */
    public function renderExportScripts(int $pageId, ?array $headerData = null, ?array $footerData = null): string
    {
        return $this->renderRuntimeBundle([
            $this->globalPartScriptTag((int) ($headerData['post_id'] ?? 0)),
            $this->pageScriptTag($pageId),
            $this->globalPartScriptTag((int) ($footerData['post_id'] ?? 0)),
        ], $this->exportLibraryPublicPaths());
    }

    /**
     * @return list<array{name: string, export_path: string, plugin_path: string, mime_type: string}>
     */
    public function approvedLibraryAssets(string $javascript = ''): array
    {
        $assets = [];

        foreach ($this->requestedEnabledLibrarySlugs($javascript) as $slug) {
            foreach (PublicRuntimeAssetCatalog::forLibrary($slug) as $name => $specification) {
                $assets[] = [
                    'name' => $name,
                    'export_path' => $specification['reference'],
                    'plugin_path' => $specification['export_source_path'],
                    'mime_type' => $specification['mime_type'],
                ];
            }
        }

        return $assets;
    }

    private function pageScriptTag(int $pageId): string
    {
        if ($pageId <= 0) {
            return '';
        }

        if ($this->toolSettingsAccess instanceof ToolSettingsAccess && !$this->toolSettingsAccess->pageCustomJavaScriptEnabled()) {
            return '';
        }

        return $this->scriptTag(
            $this->runtime->page($pageId),
            'page',
            $pageId,
        );
    }

    private function pageSourceScriptTag(int $pageId, string $javascript): string
    {
        if (
            $pageId <= 0
            || ($this->toolSettingsAccess instanceof ToolSettingsAccess
                && !$this->toolSettingsAccess->pageCustomJavaScriptEnabled())
        ) {
            return '';
        }

        return $this->scriptTag($javascript, 'page', $pageId);
    }

    private function globalPartScriptTag(int $globalPartId): string
    {
        if ($globalPartId <= 0) {
            return '';
        }

        if ($this->toolSettingsAccess instanceof ToolSettingsAccess && !$this->toolSettingsAccess->globalPartCustomJavaScriptEnabled()) {
            return '';
        }

        return $this->scriptTag(
            $this->runtime->globalPart($globalPartId),
            'global_part',
            $globalPartId,
        );
    }

    private function scriptTag(string $javascript, string $scope, int $ownerId): string
    {
        if (trim($javascript) === '') {
            return '';
        }

        return sprintf(
            '<script data-uncanny-page-builder-custom-javascript="1" data-upb-runtime-scope="%s" data-upb-runtime-owner-id="%d">%s</script>',
            self::escapeAttribute($scope),
            $ownerId,
            $this->escapeInlineScript($javascript),
        );
    }

    /**
     * @param list<string> $scripts
     */
    private function renderRuntimeBundle(array $scripts, array $libraryPublicPaths): string
    {
        $scripts = array_values(array_filter(
            $scripts,
            static fn(string $script): bool => $script !== '',
        ));

        if ($scripts === []) {
            return '';
        }

        $libraryBootstrap = $this->libraryBootstrapTag($libraryPublicPaths, implode("\n", $scripts));
        if ($libraryBootstrap !== '') {
            array_unshift($scripts, $libraryBootstrap);
        }

        return implode("\n", $scripts);
    }

    private function libraryBootstrapTag(array $libraryPublicPaths, string $javascript): string
    {
        $libraries = $this->enabledLibraryConfigs($libraryPublicPaths, $this->requestedEnabledLibrarySlugs($javascript));
        if ($libraries === []) {
            return '';
        }

        return sprintf(
            '<script data-uncanny-page-builder-custom-javascript="1" data-upb-runtime-libraries="1">%s</script>',
            $this->escapeInlineScript($this->libraryBootstrapSource($libraries)),
        );
    }

    /**
     * @param array<string, array<string, string>> $libraries
     */
    private function libraryBootstrapSource(array $libraries): string
    {
        $config = json_encode($libraries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($config)) {
            return '';
        }

        return <<<JS
(function () {
  var config = {$config};
  var runtime = window.UPBJavaScriptLibraries = window.UPBJavaScriptLibraries || {};

  if (runtime.__upbLibraryLoaderVersion >= 2) {
    return;
  }

  runtime.__upbLibraryLoaderVersion = 2;
  runtime.enabled = Object.keys(config);
  runtime.promises = runtime.promises || {};

  function logFailure(label, error) {
    if (window.console && typeof window.console.error === 'function') {
      window.console.error('[UPB JavaScript libraries] Failed to load ' + label + '.', error);
    }
  }

  function loadStyle(label, url) {
    if (!url) {
      return;
    }

    if (document.querySelector('[data-upb-runtime-library-style="' + label + '"]')) {
      return;
    }

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    link.setAttribute('data-upb-runtime-library-style', label);
    (document.head || document.body || document.documentElement).appendChild(link);
  }

  function loadScript(label, url, globalName) {
    if (runtime.promises[label]) {
      return runtime.promises[label];
    }

    if (globalName && window[globalName]) {
      runtime.promises[label] = Promise.resolve(window[globalName]);
      return runtime.promises[label];
    }

    runtime.promises[label] = new Promise(function (resolve) {
      var existing = document.querySelector('script[data-upb-runtime-library="' + label + '"]');
      if (existing) {
        existing.addEventListener('load', function () { resolve(globalName ? window[globalName] || null : null); }, { once: true });
        existing.addEventListener('error', function (event) {
          logFailure(label, event);
          resolve(null);
        }, { once: true });
        return;
      }

      var script = document.createElement('script');
      script.src = url;
      script.async = false;
      script.setAttribute('data-uncanny-page-builder-custom-javascript', '1');
      script.setAttribute('data-upb-runtime-library', label);
      script.onload = function () {
        resolve(globalName ? window[globalName] || null : null);
      };
      script.onerror = function (event) {
        logFailure(label, event);
        resolve(null);
      };
      (document.body || document.documentElement).appendChild(script);
    });

    return runtime.promises[label];
  }

  function loadLibrary(label) {
    var entry = config[label];
    if (!entry) {
      return Promise.resolve(null);
    }

    if (entry.styleUrl) {
      loadStyle(label, entry.styleUrl);
    }

    return loadScript(label, entry.url, entry.globalName);
  }

  function normalizeLabels(labels) {
    if (typeof labels === 'string') {
      labels = [labels];
    }

    if (!Array.isArray(labels)) {
      labels = runtime.enabled;
    }

    return labels.filter(function (label, index) {
      return config[label] && labels.indexOf(label) === index;
    });
  }

  function resolveRoot(root) {
    if (!root) {
      return null;
    }

    if (typeof root === 'string') {
      return document.querySelector(root);
    }

    return typeof root.querySelector === 'function' ? root : null;
  }

  function resolveElement(root, target) {
    if (!target) {
      return null;
    }

    if (typeof target === 'string') {
      return root.querySelector(target);
    }

    return target.nodeType === 1 ? target : null;
  }

  function resolveTargets(root, targets) {
    if (typeof targets === 'string') {
      return Array.prototype.slice.call(root.querySelectorAll(targets));
    }

    if (targets && targets.nodeType === 1) {
      return [targets];
    }

    return targets ? Array.prototype.slice.call(targets) : [];
  }

  runtime.reveal = function (settings) {
    settings = settings || {};

    var root = resolveRoot(settings.root);
    var readyRoot = resolveRoot(settings.readyRoot) || root;
    var targets = root ? resolveTargets(root, settings.targets) : [];
    var readyClass = typeof settings.readyClass === 'string' ? settings.readyClass : '';
    var animeLibrary = window.anime;

    if (!root || !readyRoot || !targets.length || !animeLibrary || typeof animeLibrary.animate !== 'function') {
      logFailure('Anime.js reveal', new Error('A valid root, targets, and Anime.js are required.'));
      return null;
    }

    if (readyClass) {
      readyRoot.classList.add(readyClass);
    }

    try {
      var animation = Object.assign({}, settings.animation || {});
      animation.opacity = 1;
      animation.y = 0;
      return animeLibrary.animate(targets, animation);
    } catch (error) {
      if (readyClass) {
        readyRoot.classList.remove(readyClass);
      }
      logFailure('Anime.js reveal', error);
      return null;
    }
  };

  runtime.mountSwiper = function (settings) {
    settings = settings || {};

    var root = resolveRoot(settings.root);
    var track = root ? resolveElement(root, settings.track) : null;
    var previous = root ? resolveElement(root, settings.previous) : null;
    var next = root ? resolveElement(root, settings.next) : null;
    var pagination = root ? resolveElement(root, settings.pagination) : null;
    var readyClass = typeof settings.readyClass === 'string' ? settings.readyClass : '';
    var SwiperLibrary = window.Swiper;

    if (!root || !track || !previous || !next || !pagination || typeof SwiperLibrary !== 'function') {
      logFailure('Swiper', new Error('A shared root containing the track, controls, and pagination is required.'));
      return null;
    }

    try {
      var options = Object.assign({}, settings.options || {});
      options.navigation = Object.assign({}, options.navigation || {}, {
        prevEl: previous,
        nextEl: next
      });
      options.pagination = Object.assign({}, options.pagination || {}, {
        el: pagination
      });

      var instance = new SwiperLibrary(track, options);
      if (readyClass) {
        root.classList.add(readyClass);
      }
      return instance;
    } catch (error) {
      if (readyClass) {
        root.classList.remove(readyClass);
      }
      logFailure('Swiper', error);
      return null;
    }
  };

  runtime.load = loadLibrary;

  runtime.whenReady = function (labels, callback) {
    if (typeof labels === 'function') {
      callback = labels;
      labels = runtime.enabled;
    }

    var requested = normalizeLabels(labels);

    return Promise.allSettled(requested.map(loadLibrary)).then(function () {
      var libraries = {};
      requested.forEach(function (label) {
        var entry = config[label];
        libraries[label] = entry && entry.globalName ? window[entry.globalName] || null : null;
      });

      if (typeof callback === 'function') {
        callback(libraries, runtime);
      }

      return libraries;
    });
  };
})();
JS;
    }

    /**
     * @return list<string>
     */
    private function enabledLibrarySlugs(): array
    {
        if (!$this->toolSettingsAccess instanceof ToolSettingsAccess) {
            return [];
        }

        $enabled = [];

        foreach (ToolSettings::knownLibrarySlugs() as $slug) {
            if ($this->toolSettingsAccess->libraryEnabled($slug)) {
                $enabled[] = $slug;
            }
        }

        return $enabled;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function enabledLibraryConfigs(array $libraryPublicPaths, array $librarySlugs): array
    {
        $libraries = [];
        $anime = PublicRuntimeAssetCatalog::get('anime');
        $swiper = PublicRuntimeAssetCatalog::get('swiper');
        $swiperStyles = PublicRuntimeAssetCatalog::get('swiper_styles');

        foreach ($librarySlugs as $slug) {
            if ($slug === ToolSettings::LIBRARY_ANIME && $anime !== null) {
                $libraries[$slug] = [
                    'kind' => 'script',
                    'url' => (string) ($libraryPublicPaths[$anime['reference']] ?? ''),
                    'globalName' => 'anime',
                ];
                continue;
            }

            if ($slug === ToolSettings::LIBRARY_SWIPER && $swiper !== null && $swiperStyles !== null) {
                $libraries[$slug] = [
                    'kind' => 'script',
                    'url' => (string) ($libraryPublicPaths[$swiper['reference']] ?? ''),
                    'styleUrl' => (string) ($libraryPublicPaths[$swiperStyles['reference']] ?? ''),
                    'globalName' => 'Swiper',
                ];
                continue;
            }
        }

        return $libraries;
    }

    /**
     * @return list<string>
     */
    private function requestedEnabledLibrarySlugs(string $javascript): array
    {
        if (trim($javascript) === '') {
            return [];
        }

        $enabled = array_fill_keys($this->enabledLibrarySlugs(), true);
        if ($enabled === []) {
            return [];
        }

        $requested = [];
        if (
            preg_match_all(
                '/(?:window\.)?UPBJavaScriptLibraries\s*\.\s*whenReady\s*\(\s*\[([^\]]*)\]/',
                $javascript,
                $matches
            ) < 1
        ) {
            return [];
        }

        foreach ($matches[1] as $libraryList) {
            if (preg_match_all('/[\'"]([a-z0-9_-]+)[\'"]/i', (string) $libraryList, $libraryMatches) < 1) {
                continue;
            }

            foreach ($libraryMatches[1] as $slug) {
                $slug = strtolower((string) $slug);
                if (isset($enabled[$slug])) {
                    $requested[$slug] = true;
                }
            }
        }

        return array_values(array_intersect(ToolSettings::knownLibrarySlugs(), array_keys($requested)));
    }

    /**
     * @return array<string, string>
     */
    private function pluginLibraryPublicPaths(): array
    {
        $baseUrl = '';

        if (defined('UNCANNY_PB_URL')) {
            $baseUrl = rtrim((string) UNCANNY_PB_URL, '/') . '/';
        }

        $paths = [];
        foreach (PublicRuntimeAssetCatalog::all() as $asset) {
            if ($asset['library_slug'] !== '') {
                $paths[$asset['reference']] = $baseUrl . $asset['plugin_path'];
            }
        }

        return $paths;
    }

    /**
     * @return array<string, string>
     */
    private function exportLibraryPublicPaths(): array
    {
        $paths = [];
        foreach (PublicRuntimeAssetCatalog::all() as $asset) {
            if ($asset['library_slug'] !== '') {
                $paths[$asset['reference']] = $asset['reference'];
            }
        }

        return $paths;
    }

    /**
     * Prevent the inline body from terminating its own script element.
     */
    private function escapeInlineScript(string $javascript): string
    {
        return preg_replace('/<\/script/iu', '<\\/script', $javascript) ?? $javascript;
    }

    private static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
