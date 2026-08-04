# wp_pages_list — WordPress Pages List

Renders a hierarchical list of published pages. This is a self-rendering binding — you do NOT use `data-ai-bind` keys.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<ul class="upb-pages-list">
<li class="page_item page-item-2"><a href="https://example.com/sample-page/">Sample Page</a></li>
<li class="page_item page-item-3 page_item_has_children"><a href="https://example.com/about/">About</a></li>
<!-- …one li.page_item per published page; child pages nest inside ul.children… -->
</ul>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_pages_list` | Marks this as a pages list region |
| `data-depth` | e.g. `0`, `1`, `2` | Nesting depth. `0` = unlimited (optional, defaults to `0`) |

## Bind Keys

**None.** `wp_pages_list` is self-rendering. Do not use `data-ai-bind` inside this region.

## Example

```html
<aside class="pages-widget py-4">
  <h4>Pages</h4>
  <div data-ai-dynamic="wp_pages_list" data-depth="0">
    <!-- Renderer replaces this with a nested <ul> of page links -->
  </div>
</aside>
```

## Notes

- Renders as a `<ul>` list with `<li>` items, each containing an `<a>` link
- Child pages are nested inside parent `<li>` elements according to the page hierarchy
- `data-depth="1"` shows only top-level pages, `data-depth="2"` shows one level of children, etc.
