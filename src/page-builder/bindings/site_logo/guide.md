# site_logo — Site Logo

Renders the site's logo image. The logo is resolved from WordPress branding settings (Engine branding → Customizer → FSE site_logo). Change the logo once in WordPress, every page updates automatically.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<img src="https://example.com/wp-content/uploads/2026/03/logo.svg" alt="Site Name" class="site-logo">
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `site_logo` | Marks this as a site logo region |

No other attributes needed. No query attributes, no bind keys.

## Bind Keys

**None.** The renderer outputs an `<img>` with the resolved logo URL automatically.

## Structure Rules

1. The dynamic region element's content is replaced entirely with the resolved `<img>` tag
2. If no logo is configured in WordPress, the region renders empty
3. The rendered `<img>` gets `class="site-logo"` and `alt` set to the site name

## Example — Logo in Header

```html
<header class="site-header">
  <nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">

      <a class="navbar-brand" href="/">
        <!-- Keep the region empty: it is replaced at render time with the
             actual logo <img>. Any placeholder children are removed when the
             section is saved, so stored source stays pure binding markup. -->
        <span data-ai-dynamic="site_logo"></span>
      </a>

      <!-- ... rest of navbar ... -->
    </div>
  </nav>
</header>
```

## CSS Tips

```css
/* Control logo size */
.site-logo {
  height: 40px;
  width: auto;
  object-fit: contain;
}

/* Responsive logo */
@media (max-width: 767px) {
  .site-logo {
    height: 32px;
  }
}
```

## Common Mistakes

- Hardcoding a logo URL in `src` — use `data-ai-dynamic="site_logo"` so it updates automatically when the user changes their logo
- Using `data-ai-bind` inside the region — site_logo has no bind keys
- Marking the logo image for inline editing — the logo is managed via WordPress settings, not inline editing
