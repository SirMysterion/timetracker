<?php
// Command line tool to manage personal API tokens. It cannot be run over HTTP.
//
//   php WEB-INF/tools/api_token.php create <login> <name> [--scopes=time:read,time:write] [--days=90]
//   php WEB-INF/tools/api_token.php list
//   php WEB-INF/tools/api_token.php revoke <id>

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit();
}

define('TT_STATELESS', true);
chdir(dirname(__DIR__, 2));
$_SERVER['SCRIPT_FILENAME'] = getcwd().'/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once('initialize.php');
require_once(LIBRARY_DIR.'/api/ttApiToken.class.php');

function usage() {
  fwrite(STDERR, "Usage:\n".
    "  api_token.php create <login> <name> [--scopes=time:read,time:write] [--days=90]\n".
    "  api_token.php list\n".
    "  api_token.php revoke <id>\n");
  exit(2);
}

$args = array_slice($argv, 1);
$options = array('scopes'=>'time:read,time:write', 'days'=>'90');
$positional = array();
foreach ($args as $arg) {
  if (preg_match('/^--(scopes|days)=(.*)$/', $arg, $m)) $options[$m[1]] = $m[2];
  elseif (strncmp($arg, '--', 2) === 0) usage();
  else $positional[] = $arg;
}
$command = array_shift($positional);

if ($command === 'create' && count($positional) === 2) {
  [$login, $name] = $positional;
  $scopes = array_values(array_unique(explode(',', $options['scopes'])));
  if (array_diff($scopes, ttApiToken::SCOPES)) {
    fwrite(STDERR, 'Valid scopes: '.implode(', ', ttApiToken::SCOPES)."\n");
    exit(2);
  }
  if (!ctype_digit($options['days']) || (int)$options['days'] < 1 || (int)$options['days'] > 365) {
    fwrite(STDERR, "--days must be from 1 to 365.\n");
    exit(2);
  }
  if (strlen($name) > 80) {
    fwrite(STDERR, "Name must be 80 characters or fewer.\n");
    exit(2);
  }

  $target = new ttUser($login);
  if (!$target->initialized || !$target->id) {
    fwrite(STDERR, "Active user not found.\n");
    exit(1);
  }
  $token = ttApiToken::create($target->id, $name, $scopes, (int)$options['days']);
  if (!$token) {
    fwrite(STDERR, "Could not create token. Has migration 002 been applied?\n");
    exit(1);
  }
  fwrite(STDERR, "Token created. Store it now, because it cannot be shown again.\n");
  echo $token."\n";
  exit(0);
}

if ($command === 'list' && !$positional) {
  foreach (ttApiToken::all() as $t) {
    printf("%d\t%s\t%s\t%s...\t%s\texpires=%s\tlast_used=%s\t%s\n", $t['id'], $t['login'], $t['name'],
      $t['token_prefix'], $t['scopes'], $t['expires'], $t['last_used'] ?: 'never', $t['revoked'] ? 'REVOKED' : 'active');
  }
  exit(0);
}

if ($command === 'revoke' && count($positional) === 1 && ctype_digit($positional[0])) {
  if (ttApiToken::revoke($positional[0])) {
    echo "Token revoked.\n";
    exit(0);
  }
  fwrite(STDERR, "Active token not found.\n");
  exit(1);
}

usage();
