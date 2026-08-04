# post_prev_next — Post Previous/Next Navigation

Renders previous and next post links for the current post context.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<a class="upb-post-prev" href="https://example.com/previous-post/">Previous Post</a>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_prev_next` | Marks this element for prev/next navigation output |

## Example

```html
<nav data-ai-dynamic="post_prev_next"></nav>
```

## Usage Notes

- Use this binding on a wrapper element like `<nav>` or `<div>`.
- The renderer preserves that host element and injects one or two inner `<a>` elements.
- It does not emit a second outer `<nav>` wrapper.
- If there is no current post context, or no adjacent posts exist, the element renders empty.
- Previous and next are based on WordPress adjacent-post behavior for the current post type and chronology.
