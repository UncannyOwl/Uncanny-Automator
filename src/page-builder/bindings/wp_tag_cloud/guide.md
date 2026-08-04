# wp_tag_cloud — WordPress Tag Cloud

Renders a tag cloud where tag font size scales with post count. This is a self-rendering binding — you do NOT use `data-ai-bind` keys.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<a href="https://example.com/tag/sample-tag/" class="tag-cloud-link tag-link-12 tag-link-position-1" style="font-size: 8pt" aria-label="Sample Tag (1 item)">Sample Tag</a>
<a href="https://example.com/tag/another-tag/" class="tag-cloud-link tag-link-15 tag-link-position-2" style="font-size: 14pt" aria-label="Another Tag (4 items)">Another Tag</a>
<!-- …one a.tag-cloud-link per tag; inline font-size scales with post count (8pt–22pt by default) — override it in your CSS with !important or style the wrapper… -->
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_tag_cloud` | Marks this as a tag cloud region |
| `data-taxonomy` | e.g. `post_tag`, `category` | Taxonomy to display (optional, defaults to `post_tag`) |
| `data-count` | e.g. `45`, `20` | Maximum number of tags to show (optional, defaults to `45`) |

## Bind Keys

**None.** `wp_tag_cloud` is self-rendering. Do not use `data-ai-bind` inside this region.

## Example

```html
<aside class="tag-cloud-widget py-4">
  <h4>Tags</h4>
  <div data-ai-dynamic="wp_tag_cloud"
       data-taxonomy="post_tag"
       data-count="30">
    <!-- Renderer replaces this with linked tags at varying font sizes -->
  </div>
</aside>
```

## Notes

- Tags with more posts appear in a larger font size
- Each tag is a link to its archive page
- Works with any taxonomy, not just `post_tag`
