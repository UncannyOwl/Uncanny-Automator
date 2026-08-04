# registration_form — Registration Form

Renders a WordPress user registration form. If registration is disabled in Settings > General, outputs a message instead.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<form method="post" action="https://example.com/wp-login.php?action=register"><p><label>Username<br /><input type="text" name="user_login" required /></label></p><p><label>Email<br /><input type="email" name="user_email" required /></label></p><input type="hidden" id="_wpnonce" name="_wpnonce" value="PLACEHOLDER_NONCE" /><input type="hidden" name="_wp_http_referer" value="" /><p><input type="submit" value="Register" /></p></form>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `registration_form` | Marks this as a registration form region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<div data-ai-dynamic="registration_form">
  <!-- WordPress registration form renders here -->
</div>
```

## Notes

- Only works when "Anyone can register" is enabled in WordPress Settings > General
- If the user is already logged in, shows a welcome message with logout link
- Outputs standard WordPress registration fields (username, email)
