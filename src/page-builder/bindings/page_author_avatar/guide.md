# page_author_avatar — Page Author Avatar

Render the current page or post author's avatar URL.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<img data-ai-dynamic="page_author_avatar" src="#" class="your-class" alt="Author image">

<!-- Renders as: -->
<img data-ai-dynamic="page_author_avatar" src="https://example.com/resolved" class="your-class" alt="Author image">
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `page_author_avatar` | Marks this as a page_author_avatar region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<img data-ai-dynamic="page_author_avatar" src="" alt="Placeholder" />
```

## Notes

- Use this binding on an `<img>` element. The renderer writes the avatar URL into `src`.
- If there is no current post/page context or no author avatar URL can be resolved, the element renders empty.
