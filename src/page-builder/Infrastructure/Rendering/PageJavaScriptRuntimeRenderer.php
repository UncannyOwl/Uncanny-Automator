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
 * Runtime tags opt out of aggregation because their parser order establishes
 * library ownership before the external assets execute.
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
            '<script data-noptimize="1" data-uncanny-page-builder-custom-javascript="1" data-upb-runtime-scope="%s" data-upb-runtime-owner-id="%d">%s</script>',
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

        $javascript = implode("\n", $scripts);
        $libraries = $this->enabledLibraryConfigs(
            $libraryPublicPaths,
            $this->requestedEnabledLibrarySlugs($javascript),
        );
        if ($libraries === []) {
            return $javascript;
        }

        $libraryBootstrap = $this->libraryBootstrapTag($libraries);
        $libraryRegistration = $this->libraryRegistrationTag(array_keys($libraries));
        if ($libraryBootstrap === '' || $libraryRegistration === '') {
            return $javascript;
        }

        return implode("\n", [
            $libraryBootstrap,
            ...$this->libraryAssetTags($libraries),
            $libraryRegistration,
            ...$scripts,
        ]);
    }

    /**
     * @param array<string, array<string, string>> $libraries
     */
    private function libraryBootstrapTag(array $libraries): string
    {
        $source = $this->libraryBootstrapSource($libraries);
        if ($source === '') {
            return '';
        }

        return sprintf(
            '<script data-noptimize="1" data-uncanny-page-builder-custom-javascript="1" data-upb-runtime-libraries="1">%s</script>',
            $this->escapeInlineScript($source),
        );
    }

    /**
     * @param list<string> $librarySlugs
     */
    private function libraryRegistrationTag(array $librarySlugs): string
    {
        $labels = self::encodeJson($librarySlugs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($labels)) {
            return '';
        }

        $source = sprintf(
            '(function(runtime){if(runtime&&typeof runtime.registerPreloaded==="function"){runtime.registerPreloaded(%s);}})(window.UPBJavaScriptLibraries);',
            $labels,
        );

        return sprintf(
            '<script data-noptimize="1" data-uncanny-page-builder-custom-javascript="1" data-upb-runtime-library-registration="1">%s</script>',
            $this->escapeInlineScript($source),
        );
    }

    /**
     * @param array<string, array<string, string>> $libraries
     * @return list<string>
     */
    private function libraryAssetTags(array $libraries): array
    {
        $tags = [];

        foreach ($libraries as $slug => $library) {
            $styleUrl = trim((string) ($library['styleUrl'] ?? ''));
            if ($styleUrl !== '') {
                $tags[] = sprintf(
                    '<link rel="stylesheet" href="%s" media="print" data-noptimize="1" data-upb-runtime-library-style="%s" data-upb-runtime-library-style-preloaded="1">',
                    self::escapeAttribute($styleUrl),
                    self::escapeAttribute($slug),
                );
            }

            $scriptUrl = trim((string) ($library['url'] ?? ''));
            if ($scriptUrl === '') {
                continue;
            }

            $tags[] = sprintf(
                '<script defer src="%s" data-noptimize="1" data-uncanny-page-builder-custom-javascript="1" data-upb-runtime-library="%s" data-upb-runtime-library-preloaded="1"></script>',
                self::escapeAttribute($scriptUrl),
                self::escapeAttribute($slug),
            );
        }

        return $tags;
    }

    /**
     * @param array<string, array<string, string>> $libraries
     */
    private function libraryBootstrapSource(array $libraries): string
    {
        $config = self::encodeJson($libraries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($config)) {
            return '';
        }

        return <<<JS
(function () {
  var config = {$config};
  var runtime = window.UPBJavaScriptLibraries = window.UPBJavaScriptLibraries || {};

  if (runtime.__upbLibraryLoaderVersion >= 5) {
    return;
  }

  runtime.__upbLibraryLoaderVersion = 5;
  runtime.enabled = Object.keys(config);
  var libraryPromises = {};
  var scriptPromises = {};
  var scriptNodes = {};
  var stylePromises = {};
  var styleNodes = {};
  var providedExports = {};
  var readyExports = {};
  var earlyAssetFailures = {};

  function logFailure(label, error) {
    if (window.console && typeof window.console.error === 'function') {
      window.console.error('[UPB JavaScript libraries] Failed to load ' + label + '.', error);
    }
  }

  function assetFailureKey(type, label) {
    return type + ':' + label;
  }

  function captureEarlyAssetFailure(event) {
    var target = event.target;
    if (!target || typeof target.getAttribute !== 'function') {
      return;
    }

    var label = target.getAttribute('data-upb-runtime-library-style');
    var type = 'style';
    if (!label) {
      label = target.getAttribute('data-upb-runtime-library');
      type = 'script';
    }
    if (label) {
      earlyAssetFailures[assetFailureKey(type, label)] = event;
    }
  }

  window.addEventListener('error', captureEarlyAssetFailure, true);

  function stopEarlyAssetFailureCapture() {
    window.removeEventListener('error', captureEarlyAssetFailure, true);
  }

  function stylePromise(label, link) {
    return new Promise(function (resolve) {
      var settled = false;

      function finish(ready, error) {
        if (settled) {
          return;
        }

        settled = true;
        if (!ready) {
          logFailure(label + ' styles', error);
          resolve(false);
          return;
        }

        resolve(true);
      }

      link.addEventListener('load', function () {
        finish(true);
      }, { once: true });
      link.addEventListener('error', function (event) {
        finish(false, event);
      }, { once: true });

      var earlyFailure = earlyAssetFailures[assetFailureKey('style', label)];
      if (earlyFailure) {
        finish(false, earlyFailure);
        return;
      }

      if (link.sheet) {
        finish(true);
      }
    });
  }

  function registerPreloadedStyle(label) {
    if (stylePromises[label]) {
      return stylePromises[label];
    }

    var link = document.querySelector('[data-upb-runtime-library-style="' + label + '"][data-upb-runtime-library-style-preloaded]');
    if (!link) {
      return null;
    }

    styleNodes[label] = link;
    stylePromises[label] = stylePromise(label, link);
    return stylePromises[label];
  }

  function loadStyle(label, url) {
    if (stylePromises[label]) {
      return stylePromises[label];
    }

    var existing = document.querySelector('[data-upb-runtime-library-style="' + label + '"]');
    if (existing) {
      styleNodes[label] = existing;
      stylePromises[label] = stylePromise(label, existing);
      return stylePromises[label];
    }

    if (!url) {
      logFailure(label + ' styles', new Error('The Page Builder stylesheet URL is unavailable.'));
      return Promise.resolve(false);
    }

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = url;
    link.media = 'print';
    link.setAttribute('data-noptimize', '1');
    link.setAttribute('data-upb-runtime-library-style', label);
    styleNodes[label] = link;
    stylePromises[label] = stylePromise(label, link);
    (document.head || document.body || document.documentElement).appendChild(link);
    return stylePromises[label];
  }

  function scriptPromise(label, script) {
    return new Promise(function (resolve) {
      var settled = false;

      function finish(library, error) {
        if (settled) {
          return;
        }

        settled = true;
        if (!library) {
          logFailure(label, error);
        }
        resolve(library);
      }

      script.addEventListener('load', function () {
        var library = providedExports[label] || null;
        finish(library, new Error('The loaded script did not register its Page Builder export.'));
      }, { once: true });
      script.addEventListener('error', function (event) {
        finish(null, event);
      }, { once: true });

      var earlyFailure = earlyAssetFailures[assetFailureKey('script', label)];
      if (earlyFailure) {
        finish(null, earlyFailure);
      }
    });
  }

  function registerPreloadedScript(label) {
    if (scriptPromises[label]) {
      return scriptPromises[label];
    }

    var script = document.querySelector('script[data-upb-runtime-library="' + label + '"][data-upb-runtime-library-preloaded]');
    if (!config[label] || !script) {
      return null;
    }

    scriptNodes[label] = script;
    scriptPromises[label] = scriptPromise(label, script);
    return scriptPromises[label];
  }

  function loadScript(label, url) {
    if (scriptPromises[label]) {
      return scriptPromises[label];
    }

    if (!url) {
      logFailure(label, new Error('The Page Builder script URL is unavailable.'));
      return Promise.resolve(null);
    }

    var existing = document.querySelector('script[data-upb-runtime-library="' + label + '"]');
    if (existing) {
      scriptNodes[label] = existing;
      scriptPromises[label] = scriptPromise(label, existing);
      return scriptPromises[label];
    }

    var script = document.createElement('script');
    script.src = url;
    script.async = true;
    script.setAttribute('data-noptimize', '1');
    script.setAttribute('data-uncanny-page-builder-custom-javascript', '1');
    script.setAttribute('data-upb-runtime-library', label);
    scriptNodes[label] = script;
    scriptPromises[label] = scriptPromise(label, script);
    (document.body || document.documentElement).appendChild(script);

    return scriptPromises[label];
  }

  function loadLibrary(label) {
    var entry = config[label];
    if (!entry) {
      return Promise.resolve(null);
    }

    if (libraryPromises[label]) {
      return libraryPromises[label];
    }

    var stylesReady = Object.prototype.hasOwnProperty.call(entry, 'styleUrl')
      ? loadStyle(label, entry.styleUrl)
      : Promise.resolve(true);
    var scriptReady = loadScript(label, entry.url);

    stopEarlyAssetFailureCapture();

    libraryPromises[label] = new Promise(function (resolve) {
      var settled = false;
      var scriptResult;
      var stylesLoaded = false;

      function fail() {
        if (settled) {
          return;
        }
        settled = true;
        delete readyExports[label];
        resolve(null);
      }

      function complete() {
        if (settled || !stylesLoaded || !scriptResult) {
          return;
        }

        settled = true;
        var style = styleNodes[label];
        if (style) {
          style.media = 'all';
        }
        readyExports[label] = scriptResult;
        resolve(scriptResult);
      }

      stylesReady.then(function (ready) {
        if (!ready) {
          fail();
          return;
        }
        stylesLoaded = true;
        complete();
      }, function (error) {
        logFailure(label + ' styles', error);
        fail();
      });

      scriptReady.then(function (library) {
        if (!library) {
          fail();
          return;
        }
        scriptResult = library;
        complete();
      }, function (error) {
        logFailure(label, error);
        fail();
      });
    });

    return libraryPromises[label];
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

  runtime.registerPreloaded = function (labels) {
    normalizeLabels(labels).forEach(function (label) {
      registerPreloadedStyle(label);
      registerPreloadedScript(label);
      loadLibrary(label);
    });
  };

  runtime.provide = function (label, library) {
    var script = scriptNodes[label];
    var validOwner = script
      && document.currentScript === script
      && script.getAttribute('data-upb-runtime-library') === label;
    var validExport = label === 'anime'
      ? library && typeof library.animate === 'function'
      : label === 'swiper' && typeof library === 'function';

    if (!config[label] || !validOwner || !validExport) {
      logFailure(label || 'unknown library', new Error('The asset provided an invalid Page Builder export.'));
      return false;
    }

    providedExports[label] = library;
    return true;
  };

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
    var animeLibrary = readyExports.anime;

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
    var SwiperLibrary = readyExports.swiper;

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

    return Promise.allSettled(requested.map(loadLibrary)).then(function (results) {
      var libraries = {};
      requested.forEach(function (label, index) {
        var result = results[index];
        libraries[label] = result.status === 'fulfilled' ? result.value : null;
        if (result.status === 'rejected') {
          logFailure(label, result.reason);
        }
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
        $anime = PublicRuntimeAssetCatalog::get('anime_page_builder');
        $swiper = PublicRuntimeAssetCatalog::get('swiper_page_builder');
        $swiperStyles = PublicRuntimeAssetCatalog::get('swiper_styles_page_builder');

        foreach ($librarySlugs as $slug) {
            if ($slug === ToolSettings::LIBRARY_ANIME && $anime !== null) {
                $libraries[$slug] = [
                    'kind' => 'script',
                    'url' => (string) ($libraryPublicPaths[$anime['reference']] ?? ''),
                ];
                continue;
            }

            if ($slug === ToolSettings::LIBRARY_SWIPER && $swiper !== null && $swiperStyles !== null) {
                $libraries[$slug] = [
                    'kind' => 'script',
                    'url' => (string) ($libraryPublicPaths[$swiper['reference']] ?? ''),
                    'styleUrl' => (string) ($libraryPublicPaths[$swiperStyles['reference']] ?? ''),
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

    private static function encodeJson(mixed $value, int $flags = 0): string|false
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value, $flags);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone rendering tests run without WordPress functions.
        return json_encode($value, $flags);
    }
}
