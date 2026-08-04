# if_category — Conditional: If Category Archive

Shows its children only when the current page is a category archive page. If the visitor is on any other page type, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_category"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_category` | Marks this as a category archive conditional wrapper |

## How It Works

- The wrapper checks `is_category()` to determine if the current page is a category archive.
- If the current page is a category archive, the wrapper and its children render normally.
- On all other page types, the wrapper produces no output.
- This matches any category archive page regardless of which category is being viewed.

## Example

```html
<div data-ai-dynamic="if_category">
  <div class="category-intro">
    <p>You are browsing posts in this category.</p>
  </div>
</div>
```

## Usage Notes

- `if_category` matches all category archive pages. It does not filter by specific category.
- For broader archive matching that includes tags, authors, and dates, use `if_archive` instead.
- Use this for category-specific layout elements like category descriptions or featured category images within a global template.
