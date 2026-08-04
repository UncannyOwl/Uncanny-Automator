# if_front_page — Conditional: If Front Page

Shows its children only when the current page is the site's front page. If the visitor is on any other page, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_front_page"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_front_page` | Marks this as a front page conditional wrapper |

## How It Works

- The wrapper checks `is_front_page()` to determine if the current page is the site's designated front page.
- If the current page is the front page, the wrapper and its children render normally.
- On all other pages, the wrapper produces no output.
- This checks the front page specifically — the static page set in Settings > Reading as "Your homepage displays: A static page."

## Example

```html
<div data-ai-dynamic="if_front_page">
  <section class="hero-banner">
    <h1>Welcome to Our Site</h1>
    <p>This hero banner only appears on the front page.</p>
  </section>
</div>
```

## Usage Notes

- `if_front_page` matches the static front page set in Settings > Reading. This is different from `if_home`, which matches the blog posts listing page.
- When no static front page is set, the default blog listing page matches both `if_front_page` and `if_home`.
- Use `if_front_page` for homepage-specific hero sections, welcome messages, or landing page content within a global layout.
