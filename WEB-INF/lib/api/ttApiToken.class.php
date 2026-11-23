<?php
// ttApiToken manages personal API tokens. Only a SHA-256 hash of each token is stored.
class ttApiToken {
  const PREFIX = 'tt_pat_';
  const SCOPES = array('time:read', 'time:write', 'time:delete');
  const MAX_ACTIVE_PER_USER = 20;

  // create returns the plaintext token, which cannot be recovered later.
  static function create($user_id, $name, $scopes, $expires_days) {
    $mdb2 = getConnection();
    $token = self::PREFIX.bin2hex(random_bytes(32));
    $user_id = (int)$user_id;
    $expires_days = (int)$expires_days;

    $sql = "insert into tt_api_tokens (user_id, name, token_hash, token_prefix, scopes, expires, created) values (".
      "$user_id, ".$mdb2->quote($name).", ".$mdb2->quote(hash('sha256', $token)).", ".
      $mdb2->quote(substr($token, 0, 14)).", ".$mdb2->quote(implode(',', $scopes)).
      ", now() + interval $expires_days day, now())";
    $affected = $mdb2->exec($sql);
    return is_a($affected, 'PEAR_Error') ? false : $token;
  }

  // authenticate returns the active token row with its scopes, or null.
  static function authenticate($token) {
    if (!preg_match('/^'.self::PREFIX.'[0-9a-f]{64}$/', $token)) return null;

    $mdb2 = getConnection();
    $sql = "select t.id, t.user_id, t.name, t.scopes, t.last_used from tt_api_tokens t".
      " inner join tt_users u on (u.id = t.user_id and u.status = 1)".
      " where t.token_hash = ".$mdb2->quote(hash('sha256', $token)).
      " and t.revoked is null and t.expires > now()";
    $res = $mdb2->query($sql);
    if (is_a($res, 'PEAR_Error')) return null;
    $row = $res->fetchRow();
    if (!$row) return null;

    $id = (int)$row['id'];
    $mdb2->exec("update tt_api_tokens set last_used = now() where id = $id".
      " and (last_used is null or last_used < now() - interval 1 minute)");
    $row['id'] = $id;
    $row['user_id'] = (int)$row['user_id'];
    $row['scopes'] = explode(',', $row['scopes']);
    return $row;
  }

  static function all() {
    $mdb2 = getConnection();
    $sql = "select t.id, u.login, t.name, t.token_prefix, t.scopes, t.created, t.expires, t.last_used, t.revoked".
      " from tt_api_tokens t inner join tt_users u on (u.id = t.user_id) order by t.id";
    $res = $mdb2->query($sql);
    $rows = array();
    if (!is_a($res, 'PEAR_Error')) {
      while ($row = $res->fetchRow()) $rows[] = $row;
    }
    return $rows;
  }

  // forUser returns one user's tokens for display. Token hashes are never selected.
  static function forUser($user_id) {
    $mdb2 = getConnection();
    $user_id = (int)$user_id;
    $sql = "select id, name, token_prefix, scopes, created, expires, last_used, revoked, (expires <= now()) as expired".
      " from tt_api_tokens where user_id = $user_id order by revoked is not null, id desc limit 100";
    $res = $mdb2->query($sql);
    $rows = array();
    if (!is_a($res, 'PEAR_Error')) {
      while ($row = $res->fetchRow()) $rows[] = $row;
    }
    return $rows;
  }

  static function activeCountForUser($user_id) {
    $mdb2 = getConnection();
    $user_id = (int)$user_id;
    $res = $mdb2->query("select count(*) as cnt from tt_api_tokens where user_id = $user_id".
      " and revoked is null and expires > now()");
    if (is_a($res, 'PEAR_Error')) return 0;
    $row = $res->fetchRow();
    return (int)$row['cnt'];
  }

  // revokeForUser revokes a token only if it belongs to the given user.
  static function revokeForUser($id, $user_id) {
    $mdb2 = getConnection();
    $id = (int)$id;
    $user_id = (int)$user_id;
    $affected = $mdb2->exec("update tt_api_tokens set revoked = now() where id = $id and user_id = $user_id and revoked is null");
    return !is_a($affected, 'PEAR_Error') && $affected > 0;
  }

  static function revoke($id) {
    $mdb2 = getConnection();
    $id = (int)$id;
    $affected = $mdb2->exec("update tt_api_tokens set revoked = now() where id = $id and revoked is null");
    return !is_a($affected, 'PEAR_Error') && $affected > 0;
  }
}
