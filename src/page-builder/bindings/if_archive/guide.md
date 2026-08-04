# if_archive — Conditional: If Archive

Shows its children only when the current page is an archive page. If the visitor is on any other page type, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_archive"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_archive` | Marks this as an archive conditional wrapper |

## How It Works

- The wrapper checks `is_archive()` to determine if the current page is an archive.
- If the current page is an archive, the wrapper and its children render normally.
- On all other page types, the wrapper produces no output.
- This matches category archives, tag archives, author archives, date archives, and custom taxonomy archives.

## Example

```html
<div data-ai-dynamic="if_archive">
  <div class="archive-header">
    <p>You are browsing an archive. Use the filters below to narrow results.</p>
  </div>
</div>
```

## Usage Notes

- `if_archive` is a broad match — it covers category, tag, author, date, and custom taxonomy archives.
- For more specific archive types, use `if_category`, `if_tag`, or `if_author` instead.
- Use this for archive-wide elements like filter bars, listing headers, or pagination wrappers within a global layout.
