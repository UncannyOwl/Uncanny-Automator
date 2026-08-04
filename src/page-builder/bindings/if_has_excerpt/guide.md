# if_has_excerpt — Conditional: If Has Excerpt

Shows its children only when the current post has a manually-set excerpt. If the post has no manual excerpt, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_has_excerpt"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_has_excerpt` | Marks this as an excerpt conditional wrapper |

## How It Works

- The wrapper checks `has_excerpt()` to determine if the current post has a manually-written excerpt.
- If the post has a manual excerpt, the wrapper and its children render normally.
- If the post has no manual excerpt, the wrapper produces no output.
- This only matches manually-set excerpts, not the auto-generated excerpts WordPress creates from post content.

## Example

```html
<div data-ai-dynamic="if_has_excerpt">
  <div class="custom-excerpt-block">
    <p>This post includes a custom summary written by the author.</p>
  </div>
</div>
```

## Usage Notes

- Only matches manually-set excerpts entered in the post editor's Excerpt field. Auto-generated excerpts do not trigger this condition.
- Use this to conditionally display excerpt-dependent layout sections, such as styled summary blocks or subtitle areas.
- Works on any post type that supports excerpts.
