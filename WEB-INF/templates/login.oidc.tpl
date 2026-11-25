{* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt *}

{* Primary path: single sign-on via the OpenID Connect provider (e.g. Keycloak).
   The link starts the redirect flow handled by oidc.php. *}
<table class="centered-table">
  <tr>
    <td style="text-align: center; padding-bottom: 12px;">
      <a href="oidc.php" style="display: inline-block; padding: 8px 18px; font-weight: bold;">{$i18n.button.login} (SSO)</a>
    </td>
  </tr>
</table>

{* Fallback: local login for the admin@localhost account, used when the
   provider is unavailable or misconfigured. Regular users authenticate via SSO
   above and will not have a local password. *}
<table class="centered-table">
  <tr><td colspan="2"><hr></td></tr>
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
</table>
