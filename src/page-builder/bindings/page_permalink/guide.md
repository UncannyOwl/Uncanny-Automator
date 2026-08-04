# page_permalink — Page Permalink

Render the current page/post permanent URL.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="page_permalink" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="page_permalink" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `page_permalink` | Marks this as a page_permalink region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="page_permalink">Placeholder</span>
```

## Notes

- Uses get_permalink(). Must be used within The Loop or on a page/post context.
