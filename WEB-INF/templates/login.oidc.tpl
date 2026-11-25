{* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt *}

{* The login/password form is the admin break-glass path, used when the SSO
   provider is unavailable; regular users have no local password. Single
   sign-on is the primary path and is offered below via the SSO button, which
   starts the redirect flow handled by oidc.php. The "or" row and SSO button
   live in the same table as the form so they share its width and alignment. *}
<table class="centered-table">
  <tr class="small-screen-label"><td><label for="login">{$i18n.label.login}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="login">{$i18n.label.login}:</label></td>
    <td class="td-with-input">{$forms.loginForm.login.control}</td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class="small-screen-label"><td><label for="password">{$i18n.label.password}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="password">{$i18n.label.password}:</label></td>
    <td class="td-with-input">{$forms.loginForm.password.control}</td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr>
    <td colspan="2">{$forms.loginForm.btn_login.control}</td>
  </tr>
  <tr><td colspan="2" class="login-or-cell">{$i18n.label.or|default:'or'}</td></tr>
  <tr>
    <td colspan="2" class="sso-cell"><a href="oidc.php" class="sso-button">Sign in with SSO</a></td>
  </tr>
</table>
