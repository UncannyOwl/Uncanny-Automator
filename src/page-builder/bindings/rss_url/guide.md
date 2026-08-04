# rss_url — RSS Feed URL

Render the site RSS 2.0 feed URL.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="rss_url" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="rss_url" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `rss_url` | Marks this as a rss_url region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<a data-ai-dynamic="rss_url" href="">RSS feed</a>
```

## Notes

- Uses `get_bloginfo('rss2_url')`.
- On `<a>` hosts, Page Builder writes the final `href` attribute automatically.
- Do not use this binding on `<link rel="alternate">`; the runtime does not write `href` on `<link>` elements.
- Use a text host like `<span>` only when you want the visible feed URL text.
