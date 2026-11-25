<?php
/* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt */

require_once('initialize.php');
$auth->doLogout();
session_unset();

// With OIDC, optionally end the session at the provider too (RP-initiated
// logout). This only happens when 'logout_from_provider' is enabled in config;
// otherwise we just clear the local session. getEndSessionUrl returns null when
// not applicable.
if (AUTH_MODULE == 'oidc' && method_exists($auth, 'getEndSessionUrl')) {
  $endSession = $auth->getEndSessionUrl();
  if ($endSession) {
    header('Location: '.$endSession);
    exit();
  }
}

header('Location: login.php');
