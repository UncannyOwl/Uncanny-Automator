# if_has_menu — Conditional: If Has Menu

Shows its children only when the specified menu location has an assigned menu. If no menu is assigned to the location, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_has_menu"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_has_menu` | Marks this as a menu-assigned conditional wrapper |
| `data-menu-location` | e.g. `primary`, `footer`, `social` | Theme menu location slug to check |

## How It Works

- The wrapper checks `has_nav_menu()` with the specified location to determine if a menu is assigned.
- If a menu is assigned to the location, the wrapper and its children render normally.
- If no menu is assigned to the location, the wrapper produces no output.

## Example

```html
<div data-ai-dynamic="if_has_menu" data-menu-location="primary">
  <nav class="main-navigation">
    <p>Primary navigation is available.</p>
  </nav>
</div>
```

## Usage Notes

- Use theme-defined menu location slugs (e.g. `primary`, `footer`, `social`). These are registered by the active theme. The attribute name `data-menu-location` is shared with `wp_menu` — same concept, same slug.
- Useful for showing fallback content or alternative navigation when no menu is assigned to a location.
- Only one location can be checked per wrapper. For multiple locations, use multiple wrappers.
