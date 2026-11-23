<?php
/* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt */

/**
* Auth_db class is used to authenticate users against internal DB
* @package TimeTracker
*/
class Auth_db extends Auth {

  /**
   * Authenticate user against internal users DB
   *
   * @param string $login
   * @param string $password
   * @return mixed
   */
  function authenticate($login, $password)
  {
    $mdb2 = getConnection();

    $sql = "SELECT id, password FROM tt_users WHERE login = ".$mdb2->quote($login)." AND status = 1";
    $res = $mdb2->query($sql);
    if (is_a($res, 'PEAR_Error')) {
      die($res->getMessage());
    }
    $val = $res->fetchRow();

    if ($val) {
      if (ttVerifyPassword($password, $val['password'])) {
        ttUpgradePasswordHash($val['id'], $password, $val['password']);
        return array('login'=>$login,'id'=>$val['id']);
      }

      if (!ttIsModernHash($val['password'])) {
        // If the OLD_PASSWORDS option is defined - set it.
        if (isTrue('OLD_PASSWORDS')) {
          $sql = "SET SESSION old_passwords = 1";
          $res = $mdb2->query($sql);
          if (is_a($res, 'PEAR_Error')) {
            die($res->getMessage());
          }
        }
        // Try legacy password match. This is needed for compatibility with older versions of TT.
        $sql = "SELECT id FROM tt_users
          WHERE login = ".$mdb2->quote($login)." AND password = old_password(".$mdb2->quote($password).") AND status = 1";
        $res = $mdb2->query($sql);
        if (is_a($res, 'PEAR_Error')) {
          return false; // old_password() function is removed in MySQL 5.7.5.
        }
        $legacy = $res->fetchRow();
        if (isset($legacy['id']) && $legacy['id'] > 0) {
          ttUpgradePasswordHash($legacy['id'], $password, $val['password']);
          return array('login'=>$login,'id'=>$legacy['id']);
        }
      }
    } else {
      // Spend similar time for unknown logins so that response time does not reveal them.
      password_hash($password, PASSWORD_DEFAULT);
    }

    // Special handling for admin@localhost - search for an account with admin role with a matching password.
    if ($login == 'admin@localhost') {
      $sql = "SELECT u.id, u.login, u.password FROM tt_users u".
        " LEFT JOIN tt_roles r on (u.role_id = r.id)".
        " WHERE r.rank = 1024 AND u.status = 1";
      $res = $mdb2->query($sql);
      if (is_a($res, 'PEAR_Error')) {
        die($res->getMessage());
      }
      while ($admin = $res->fetchRow()) {
        if (ttVerifyPassword($password, $admin['password'])) {
          ttUpgradePasswordHash($admin['id'], $password, $admin['password']);
          return array('login'=>$admin['login'],'id'=>$admin['id']);
        }
      }
    }

    return false;
  }

  function isPasswordExternal() {
    return false;
  }
}