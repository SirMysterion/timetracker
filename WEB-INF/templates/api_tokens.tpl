{* Personal API tokens and MCP setup for the signed-in user. *}

<div class="page-hint">{$i18n.api.hint|escape}</div>

{if $new_token}
<div class="section-header">{$i18n.api.new_token|escape}</div>
<div class="page-hint"><b>{$i18n.api.copy_now|escape}</b></div>
<textarea id="new_token" readonly rows="2" autocomplete="off" spellcheck="false" style="width:100%;max-width:640px;box-sizing:border-box;font-family:monospace">{$new_token|escape}</textarea>
{/if}

<div class="section-header">{$i18n.api.create|escape}</div>
<form method="post" action="api_tokens.php" autocomplete="off">
<table class="centered-table">
  <tr class="small-screen-label"><td><label for="name">{$i18n.api.token_name|escape} (*):</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="name">{$i18n.api.token_name|escape} (*):</label></td>
    <td class="td-with-input"><input type="text" id="name" name="name" maxlength="80" required placeholder="claude-code" value="{$cl_name|escape}"></td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class="small-screen-label"><td><label for="access">{$i18n.api.access|escape}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="access">{$i18n.api.access|escape}:</label></td>
    <td class="td-with-input">
      <select id="access" name="access">
        <option value="read"{if $cl_access == 'read'} selected{/if}>{$i18n.api.access_read|escape}</option>
        <option value="write"{if $cl_access == 'write'} selected{/if}>{$i18n.api.access_write|escape}</option>
        <option value="delete"{if $cl_access == 'delete'} selected{/if}>{$i18n.api.access_delete|escape}</option>
      </select>
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class="small-screen-label"><td><label for="days">{$i18n.api.expires|escape}:</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="days">{$i18n.api.expires|escape}:</label></td>
    <td class="td-with-input">
      <select id="days" name="days">
        {foreach $expiry_options as $days}<option value="{$days}"{if $cl_days == $days} selected{/if}>{$days} {$i18n.api.days|escape}</option>{/foreach}
      </select>
    </td>
  </tr>
  <tr><td><div class="small-screen-form-control-separator"></div></td></tr>
  <tr class="small-screen-label"><td><label for="password">{$i18n.api.current_password|escape} (*):</label></td></tr>
  <tr>
    <td class="large-screen-label"><label for="password">{$i18n.api.current_password|escape} (*):</label></td>
    <td class="td-with-input"><input type="password" id="password" name="password" maxlength="120" required autocomplete="current-password"></td>
  </tr>
</table>
<div class="button-set"><input type="submit" name="btn_create" value="{$i18n.api.create|escape}"></div>
</form>

<div class="section-header">{$i18n.api.your_tokens|escape}</div>
{if $tokens}
<table class="x-scrollable-table">
  <tr>
    <th>{$i18n.api.token_name|escape}</th>
    <th>{$i18n.api.prefix|escape}</th>
    <th>{$i18n.api.access|escape}</th>
    <th>{$i18n.api.created|escape}</th>
    <th>{$i18n.api.expires|escape}</th>
    <th>{$i18n.api.last_used|escape}</th>
    <th>{$i18n.api.status|escape}</th>
    <th></th>
  </tr>
  {foreach $tokens as $t}
  <tr>
    <td>{$t.name|escape}</td>
    <td><code>{$t.token_prefix|escape}…</code></td>
    <td>{$t.scopes|escape}</td>
    <td>{$t.created|escape}</td>
    <td>{$t.expires|escape}</td>
    <td>{if $t.last_used}{$t.last_used|escape}{else}{$i18n.api.never|escape}{/if}</td>
    <td>{if $t.revoked}{$i18n.api.revoked|escape}{elseif $t.expired}{$i18n.api.expired|escape}{else}{$i18n.api.active|escape}{/if}</td>
    <td>{if !$t.revoked && !$t.expired}
      <form method="post" action="api_tokens.php" onsubmit="return confirm('{$i18n.api.revoke_confirm|escape:'javascript'|escape}');">
        <input type="hidden" name="token_id" value="{$t.id|escape}">
        <input type="submit" name="btn_revoke" value="{$i18n.api.revoke|escape}">
      </form>{/if}</td>
  </tr>
  {/foreach}
</table>
{else}
<div class="page-hint">{$i18n.api.no_tokens|escape}</div>
{/if}

<div class="section-header">{$i18n.api.mcp_setup|escape}</div>
<div style="max-width:760px;margin:0 auto;text-align:left">
  <p>{$i18n.api.mcp_url|escape}: <code>{$mcp_url|escape}</code></p>
  <p><b>1.</b> {$i18n.api.step_env|escape}</p>
  <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{$env_command|escape}</pre>
  <p><b>2.</b> {$i18n.api.step_claude|escape}</p>
  <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{$claude_command|escape}</pre>
  <p><b>{$i18n.api.or|escape}</b> {$i18n.api.step_opencode|escape}</p>
  <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{$opencode_config|escape}</pre>
  <p><b>3.</b> {$i18n.api.step_test|escape}</p>
  <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{$curl_command|escape}</pre>
  <p>{$i18n.api.rest_url|escape}: <code>{$rest_url|escape}</code></p>
</div>
