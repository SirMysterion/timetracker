<?php
/* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt */

require_once('initialize.php');
require_once(LIBRARY_DIR.'/api/ttApiToken.class.php');

// Access checks. Tokens belong to the signed-in user, so "on behalf" sessions cannot manage them.
if (!ttAccessAllowed('manage_own_settings') || !$user->can('track_own_time') || $user->behalf_id) {
  header('Location: access_denied.php');
  exit();
}
if (!$user->exists()) {
  header('Location: access_denied.php');
  exit();
}
// End of access checks.

// This page can display a new token, so it must never be stored by a browser or proxy.
header('Cache-Control: no-store');

$access_levels = array(
  'read'=>array('time:read'),
  'write'=>array('time:read', 'time:write'),
  'delete'=>array('time:read', 'time:write', 'time:delete'),
);
$expiry_options = array(30, 90, 180, 365);

$cl_name = '';
$cl_access = 'write';
$cl_days = 90;
$new_token = null;

if ($request->isPost()) {
  if ($request->getParameter('btn_revoke')) {
    $revoke_id = $request->getParameter('token_id');
    if (is_string($revoke_id) && ctype_digit($revoke_id))
      ttApiToken::revokeForUser($revoke_id, $user->id);
    header('Location: api_tokens.php');
    exit();
  }

  $cl_name = trim((string)$request->getParameter('name'));
  $posted_access = $request->getParameter('access');
  $posted_days = (int)$request->getParameter('days');
  $password = (string)$request->getParameter('password');
  if (is_string($posted_access) && isset($access_levels[$posted_access])) $cl_access = $posted_access;
  else $err->add($i18n->get('error.field'), $i18n->get('api.access'));
  if (in_array($posted_days, $expiry_options, true)) $cl_days = $posted_days;
  else $err->add($i18n->get('error.field'), $i18n->get('api.expires'));
  if (!ttValidString($cl_name, false, 80) || strlen($cl_name) > 80)
    $err->add($i18n->get('error.field'), $i18n->get('api.token_name'));
  if (ttApiToken::activeCountForUser($user->id) >= ttApiToken::MAX_ACTIVE_PER_USER)
    $err->add($i18n->get('api.too_many'));

  if ($err->no()) {
    // Creating a credential requires the password, with the same throttle as login.
    $throttled = ttLoginThrottled($user->login);
    if (!$throttled && $auth->authenticate($user->login, $password)) {
      ttClearLoginFailures($user->login);
      $new_token = ttApiToken::create($user->id, $cl_name, $access_levels[$cl_access], $cl_days);
      if ($new_token) $cl_name = '';
      else $err->add($i18n->get('error.db'));
    } else {
      if (!$throttled) ttRecordLoginFailure($user->login);
      $err->add($i18n->get('error.auth'));
    }
  }
}

$base_url = ttAppBaseUrl();
if (!$base_url) $base_url = (ttIsHttps() ? 'https://' : 'http://').$_SERVER['HTTP_HOST'];
$mcp_url = $base_url.'/api/mcp';
$opencode_config = array(
  '$schema'=>'https://opencode.ai/config.json',
  'mcp'=>array('timetracker'=>array(
    'type'=>'remote',
    'url'=>$mcp_url,
    'enabled'=>true,
    'headers'=>array('Authorization'=>'Bearer {env:TIMETRACKER_TOKEN}'),
  )),
);

$smarty->assign('new_token', $new_token);
$smarty->assign('tokens', ttApiToken::forUser($user->id));
$smarty->assign('cl_name', $cl_name);
$smarty->assign('cl_access', $cl_access);
$smarty->assign('cl_days', $cl_days);
$smarty->assign('expiry_options', $expiry_options);
$smarty->assign('mcp_url', $mcp_url);
$smarty->assign('rest_url', $base_url.'/api/v1');
$smarty->assign('env_command', 'read -rs TIMETRACKER_TOKEN && export TIMETRACKER_TOKEN');
$smarty->assign('claude_command', 'claude mcp add --scope user --transport http timetracker '.$mcp_url.
  ' --header "Authorization: Bearer $TIMETRACKER_TOKEN"');
$smarty->assign('opencode_config', json_encode($opencode_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$smarty->assign('curl_command', 'curl -H "Authorization: Bearer $TIMETRACKER_TOKEN" '.$base_url.'/api/v1/me');
$smarty->assign('title', $i18n->get('api.title'));
$smarty->assign('content_page_name', 'api_tokens.tpl');
$smarty->display('index.tpl');
