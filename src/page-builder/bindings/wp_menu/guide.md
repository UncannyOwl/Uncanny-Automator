# wp_menu - WordPress Navigation Menu

Renders a real WordPress navigation menu inside agent-authored markup. Use this when a page needs live menu items from WordPress instead of hard-coded links.

## Rendered HTML

The renderer outputs clean WordPress menu markup. It does not inject Bootstrap classes, Bootstrap dropdown attributes, or Bootstrap fallback CSS.

Target these stable WordPress classes in your CSS:

- `.menu` - the rendered menu list when no custom list class is supplied.
- `.menu-item` - each menu item.
- `.menu-item > a` - menu links.
- `.menu-item-has-children` - items with child menus.
- `.sub-menu` - nested menu lists.
- `.current-menu-item` and `.current-menu-ancestor` - active page state.

If the dynamic region contains a placeholder `<ul>`, the renderer copies that `<ul>` class and id onto the real WordPress menu list. This lets the agent name the menu surface without forcing Bootstrap.

```html
<nav class="site-header__nav" aria-label="Primary navigation"
     data-ai-dynamic="wp_menu"
     data-menu-id="238">
  <ul class="site-header__menu">
    <li><a href="#">Menu item</a></li>
  </ul>
</nav>
```

At runtime, WordPress replaces the placeholder list with the actual menu tree.

## Authoring vs Rendering

- Use `manage_navigation` to create menus, add or reorder items, and read the menu id.
- Use `data-menu-id` when the user picked or created a specific menu. This is stable even if theme location assignments change later.
- Use `data-menu-location` only when the user explicitly wants the menu assigned to a theme location such as the site primary or footer menu.
- Use `edit_part` to style the wrapper, spacing, responsive behavior, hover states, and dropdown behavior.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_menu` | Marks this as a menu region. |
| `data-menu-id` | e.g. `238` | Exact WordPress menu id. Preferred when rendering a specific menu. |
| `data-menu-location` | e.g. `primary` | Optional WordPress menu location slug. Use when rendering the current menu assigned to that theme location. |

At least one of `data-menu-id` or `data-menu-location` is required. If both are present, `data-menu-id` wins and `data-menu-location` is retained only as helpful source metadata.

## Bind Keys

None. Do not use `data-ai-bind` inside a `wp_menu` region. WordPress generates the menu links, labels, current-page classes, and hierarchy.

## Where Styles Belong

The renderer replaces the children inside the `data-ai-dynamic` region. Do not place `<style>` tags inside that region.

Place menu CSS outside the dynamic region, usually in the parent `<header>` or section:

Dropdown hover must stay attached to the parent list item. Do not position a submenu at `top: calc(100% + gap)` without a hover bridge; the pointer crosses empty space, the parent loses `:hover`, and the submenu disappears. Use `.menu-item:hover > .sub-menu` plus either a transparent bridge on the parent item or padding inside the submenu box.

```html
<header class="glass-header">
  <style>
    .glass-header {
      padding: 1rem;
      background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.76));
      backdrop-filter: blur(18px);
    }

    .glass-header__shell {
      display: grid;
      grid-template-columns: auto minmax(0, 1fr) auto;
      align-items: center;
      gap: 1rem;
      max-width: 1180px;
      margin: 0 auto;
      border: 1px solid rgba(15, 23, 42, .08);
      border-radius: 999px;
      padding: .75rem .9rem;
      box-shadow: 0 24px 60px rgba(15, 23, 42, .08);
    }

    .glass-header__menu {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .25rem;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .glass-header__menu .menu-item {
      position: relative;
      list-style: none;
      margin: 0;
    }

    .glass-header__menu .menu-item-has-children::after {
      content: "";
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      height: .7rem;
      display: block;
    }

    .glass-header__menu .menu-item > a {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      border-radius: 999px;
      padding: .65rem .85rem;
      color: #111827;
      font-weight: 700;
      text-decoration: none;
      transition: background .18s ease, color .18s ease, transform .18s ease;
    }

    .glass-header__menu .menu-item > a:hover,
    .glass-header__menu .menu-item > a:focus-visible,
    .glass-header__menu .current-menu-item > a,
    .glass-header__menu .current-menu-ancestor > a {
      background: #111827;
      color: #fff;
      transform: translateY(-1px);
    }

    .glass-header__menu .menu-item-has-children > a::after {
      content: "";
      width: .42rem;
      height: .42rem;
      border-right: 2px solid currentColor;
      border-bottom: 2px solid currentColor;
      transform: rotate(45deg) translateY(-1px);
    }

    .glass-header__menu .sub-menu {
      position: absolute;
      top: calc(100% + .65rem);
      left: 50%;
      z-index: 50;
      min-width: 14rem;
      margin: 0;
      padding: .55rem;
      list-style: none;
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .1);
      border-radius: 1rem;
      box-shadow: 0 24px 80px rgba(15, 23, 42, .16);
      opacity: 0;
      pointer-events: none;
      transform: translate(-50%, .5rem);
      transition: opacity .16s ease, transform .16s ease;
    }

    .glass-header__menu .menu-item:hover > .sub-menu,
    .glass-header__menu .menu-item:focus-within > .sub-menu {
      opacity: 1;
      pointer-events: auto;
      transform: translate(-50%, 0);
    }

    .glass-header__menu .sub-menu .menu-item > a {
      display: flex;
      justify-content: space-between;
      width: 100%;
      border-radius: .75rem;
      padding: .7rem .8rem;
      white-space: nowrap;
    }

    @media (max-width: 760px) {
      .glass-header__shell {
        grid-template-columns: 1fr;
        border-radius: 1.25rem;
      }

      .glass-header__menu {
        align-items: stretch;
        flex-direction: column;
      }

      .glass-header__menu .menu-item > a {
        justify-content: space-between;
      }

      .glass-header__menu .sub-menu {
        position: static;
        min-width: 0;
        margin: .35rem 0 0 1rem;
        box-shadow: none;
        opacity: 1;
        pointer-events: auto;
        transform: none;
      }
    }
  </style>

  <div class="glass-header__shell">
    <a class="glass-header__brand" href="/">Brand</a>

    <nav class="glass-header__nav" aria-label="Primary navigation"
         data-ai-dynamic="wp_menu"
         data-menu-id="238"
         data-menu-location="primary">
      <ul class="glass-header__menu">
        <li><a href="#">Menu item</a></li>
      </ul>
    </nav>

    <a class="glass-header__cta" href="/contact">Get started</a>
  </div>
</header>
```

## Common Mistakes

- Treating `wp_menu` as a menu authoring tool. Create and edit menu items with `manage_navigation`.
- Binding a user-selected menu only by `data-menu-location`. If the user picked a concrete menu from `manage_navigation`, use `data-menu-id`.
- Placing `<style>` inside the `data-ai-dynamic="wp_menu"` region. It will be removed when the menu renders.
- Styling Bootstrap selectors like `.nav-link`, `.nav-item`, `.dropdown-menu`, or relying on `data-bs-toggle`. Page Builder does not add those classes for menus.
- Using `data-ai-bind="title"` or `data-ai-bind="url"` inside `wp_menu`. Menu labels and URLs come from WordPress.
- Hard-coding menu links when the request asks for a real WordPress menu.
