# if_author — Conditional: If Author Archive

Shows its children only when the current page is an author archive page. If the visitor is on any other page type, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_author"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_author` | Marks this as an author archive conditional wrapper |

## How It Works

- The wrapper checks `is_author()` to determine if the current page is an author archive.
- If the current page is an author archive, the wrapper and its children render normally.
- On all other page types, the wrapper produces no output.
- This matches any author archive page regardless of which author is being viewed.

## Example

```html
<div data-ai-dynamic="if_author">
  <div class="author-archive-header">
    <p>You are viewing all posts by this author.</p>
  </div>
</div>
```

## Usage Notes

- `if_author` matches all author archive pages. It does not filter by specific author.
- For broader archive matching that includes categories, tags, and dates, use `if_archive` instead.
- Use this for author-specific layout elements like extended bios or author contact information within a global template.
