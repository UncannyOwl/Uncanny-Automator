# login_form — Login Form

Render a WordPress login form or welcome message if logged in.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<form name="loginform" id="loginform" action="https://example.com/wp-login.php" method="post"><p class="login-username">
				<label for="user_login">Username or Email Address</label>
				<input type="text" name="log" id="user_login" autocomplete="username" class="input" value="" size="20" />
			</p><p class="login-password">
				<label for="user_pass">Password</label>
				<input type="password" name="pwd" id="user_pass" autocomplete="current-password" spellcheck="false" class="input" value="" size="20" />
			</p><p class="login-remember"><label><input name="rememberme" type="checkbox" id="rememberme" value="forever" /> Remember Me</label></p><p class="login-submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="Log In" />
				<input type="hidden" name="redirect_to" value="https://example.com/" />
			</p></form>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `login_form` | Marks this as a login_form region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<div data-ai-dynamic="login_form">Placeholder</div>
```

## Notes

- Uses wp_login_form(). Shows login fields for guests or a welcome message for authenticated users.
