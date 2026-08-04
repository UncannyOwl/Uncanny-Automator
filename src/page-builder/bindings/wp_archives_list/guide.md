# wp_archives_list — WordPress Archives List

Renders monthly or yearly archive links. This is a self-rendering binding — you do NOT use `data-ai-bind` keys.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<ul class="upb-archives-list">
	<li><a href="https://example.com/2026/06/">June 2026</a></li>
	<li><a href="https://example.com/2026/05/">May 2026</a></li>
	<!-- …one li per month/year with published posts… -->
</ul>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_archives_list` | Marks this as an archives list region |
| `data-type` | `monthly`, `yearly`, `daily`, `weekly`, `postbypost`, `alpha` | Archive grouping type (optional, defaults to `monthly`) |
| `data-count` | e.g. `12`, `24` | Maximum number of archive links (optional, defaults to `12`) |

## Bind Keys

**None.** `wp_archives_list` is self-rendering. Do not use `data-ai-bind` inside this region.

## Example

```html
<aside class="archives-widget py-4">
  <h4>Archives</h4>
  <div data-ai-dynamic="wp_archives_list"
       data-type="monthly"
       data-count="12">
    <!-- Renderer replaces this with a <ul> of archive links -->
  </div>
</aside>
```

## Notes

- Renders as a `<ul>` list with `<li>` items, each linking to the corresponding archive page
- `monthly` shows links like "March 2026", "February 2026", etc.
- `yearly` shows links like "2026", "2025", etc.
- `postbypost` and `alpha` follow the corresponding WordPress archives modes.
- Most recent archives appear first
