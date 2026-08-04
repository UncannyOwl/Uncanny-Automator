# if_search — Conditional: If Search Results

Shows its children only when the current page is the search results page. If the visitor is on any other page, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_search"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_search` | Marks this as a search results conditional wrapper |

## How It Works

- The wrapper checks `is_search()` to determine if the current page is the search results page.
- If the current page is displaying search results, the wrapper and its children render normally.
- On all other pages, the wrapper produces no output.

## Example

```html
<div data-ai-dynamic="if_search">
  <div class="search-results-header">
    <p>Search results are displayed below. Refine your search using the form above.</p>
  </div>
</div>
```

## Usage Notes

- Use this for search-specific content like result count messages, search tips, or refined search forms within a global layout.
- This matches the search results page regardless of whether results were found or not.
