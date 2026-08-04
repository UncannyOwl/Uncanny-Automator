# total_comments_count — Total Comments Count

Renders the total number of approved comments across the entire site.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:total_comments_count -->): -->
<span data-ai-dynamic="total_comments_count" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="total_comments_count" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `total_comments_count` | Marks this element for comment count output |

## Example

```html
<p>Join the conversation — <span data-ai-dynamic="total_comments_count"></span> comments and counting!</p>
```

## Usage Notes

- Only counts approved (published) comments; pending, spam, and trashed are excluded.
- Returns a plain number. Format with CSS or wrap in additional markup as needed.
- Useful for social proof sections or site statistics displays.
