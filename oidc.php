<?php
/* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt */

// oidc.php drives the OpenID Connect "authorization code" login flow.
//
//   Phase 1 (no code in the request): generate state and nonce, remember them
//           in the session, and redirect the browser to the provider.
//   Phase 2 (provider redirected back with ?code=...): verify state, exchange
//           the code for an ID token, map a claim to a tt_users login, and
//           establish the session for that (pre-existing) user.

require_once('initialize.php');
import('ttUser');

// This endpoint is only active when OIDC authentication is configured.
if (AUTH_MODULE != 'oidc') {
  header('Location: login.php');
  exit();
}

// --- Phase 2: handle the provider's redirect back to us. ---
if ($request->getParameter('code') || $request->getParameter('error')) {

  // Consume the state and nonce we stored when starting the flow.
  $expectedState = isset($_SESSION['oidc_state']) ? $_SESSION['oidc_state'] : null;
  $expectedNonce = isset($_SESSION['oidc_nonce']) ? $_SESSION['oidc_nonce'] : null;
  unset($_SESSION['oidc_state']);
  unset($_SESSION['oidc_nonce']);

  // The provider may report an error (e.g. the user declined consent).
  if ($request->getParameter('error')) {
    header('Location: login.php');
    exit();
  }

  $code  = $request->getParameter('code');
  $state = $request->getParameter('state');

  // Verify state to protect against cross site request forgery.
  if (!$expectedState || !$state || !hash_equals($expectedState, $state)) {
    header('Location: login.php');
    exit();
  }

  // Exchange the code and validate the returned ID token.
  $claims = $auth->exchangeCode($code, $expectedNonce);
  if ($claims === false) {
    header('Location: login.php');
    exit();
  }

  // Map the configured claim to a Time Tracker login.
  $claimName = $auth->loginClaim();
  $login = isset($claims[$claimName]) ? $claims[$claimName] : null;
  if (!$login) {
    header('Location: login.php');
    exit();
  }

  // Pre-provisioned model: the user must already exist and be active.
  // (No account is created here.)
  if (!$auth->setAuthByLogin($login)) {
    header('Location: access_denied.php');
    exit();
  }

  // Redirect depending on role, mirroring login.php. Keycloak handles MFA, so
  // Time Tracker's own 2FA step is intentionally skipped for OIDC logins.
  $user = new ttUser(null, $auth->getUserId());
  if ($user->can('administer_site')) {
    header('Location: admin_groups.php');
  } elseif ($user->isClient()) {
    header('Location: reports.php');
  } else {
    header('Location: time.php');
  }
  exit();
}

// --- Phase 1: start the login by redirecting to the provider. ---
$cryptographically_strong = true;
$state = bin2hex(openssl_random_pseudo_bytes(16, $cryptographically_strong));
$nonce = bin2hex(openssl_random_pseudo_bytes(16, $cryptographically_strong));
if ($state === false || $nonce === false) die('openssl_random_pseudo_bytes function call failed...');

$_SESSION['oidc_state'] = $state;
$_SESSION['oidc_nonce'] = $nonce;

$authUrl = $auth->getAuthUrl($state, $nonce);
if (!$authUrl) {
  // Misconfiguration, or the provider's discovery document is unreachable.
  header('Location: login.php');
  exit();
}

header('Location: '.$authUrl);
exit();
