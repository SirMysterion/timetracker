<?php
/* Copyright (c) Anuko International Ltd. https://www.anuko.com
License: See license.txt */

/**
 * Auth_oidc authenticates users via an OpenID Connect provider such as Keycloak.
 *
 * Unlike the db and ldap modules, OIDC uses a browser redirect ("authorization
 * code") flow rather than a login/password pair. The redirect handshake lives
 * in oidc.php at the webroot; this class holds the provider configuration and
 * the helper methods that oidc.php calls.
 *
 * User provisioning is intentionally NOT handled here. A user authenticated by
 * the provider must already exist in tt_users with a matching login (the claim
 * named by the 'login_claim' parameter), otherwise login is refused. This is
 * the same "external auth, local account" model that Auth_ldap uses.
 *
 * @package TimeTracker
 */
class Auth_oidc extends Auth {
  var $params;
  private $discovery = null;

  function __construct($params) {
    $this->params = $params;
  }

  /**
   * authenticate - OIDC logins do not go through login/password. The only
   * credential path kept here is a single break-glass database account, so an
   * administrator can still sign in locally if the provider is unreachable.
   *
   * The break-glass login defaults to 'admin@localhost' but is configurable via
   * the 'fallback_login' parameter, because a site's local admin account may use
   * a different login (for example a bare 'admin'). Set 'fallback_login' => null
   * to disable the local fallback entirely.
   */
  function authenticate($login, $password) {
    $fallback = $this->getParam('fallback_login', 'admin@localhost');
    if ($fallback && $login === $fallback) {
      import('auth.Auth_db');
      $dbAuth = new Auth_db();
      return $dbAuth->authenticate($login, $password);
    }
    return false;
  }

  function isPasswordExternal() {
    return true;
  }

  // getParam - config accessor with a default.
  private function getParam($key, $default = null) {
    return isset($this->params[$key]) ? $this->params[$key] : $default;
  }

  // loginClaim - the ID token claim matched against a Time Tracker account.
  function loginClaim() {
    return $this->getParam('login_claim', 'email');
  }

  /**
   * resolveLogin - map a claim value to a local tt_users login. An exact,
   * active login match wins. Failing that, if exactly one active user has that
   * value as their email, that user's login is returned. This lets an SSO
   * identity such as an email address (a common preferred_username) resolve to
   * an account whose Time Tracker login is a different short name, without any
   * account being created here. Returns the login string, or false when there
   * is no unambiguous active match.
   */
  function resolveLogin($value) {
    if ($value === null || $value === '') return false;
    $mdb2 = getConnection();
    $q = $mdb2->quote($value);

    // Prefer an exact login match.
    $res = $mdb2->query("SELECT login FROM tt_users WHERE login = $q AND status = 1");
    if (!is_a($res, 'PEAR_Error')) {
      $row = $res->fetchRow();
      if ($row && $row['login']) return $row['login'];
    }

    // Otherwise accept a unique active email match (ambiguous matches are refused).
    $res = $mdb2->query("SELECT login FROM tt_users WHERE email = $q AND status = 1");
    if (is_a($res, 'PEAR_Error')) return false;
    $first = $res->fetchRow();
    if (!$first || !$first['login']) return false;
    if ($res->fetchRow()) return false; // more than one active user shares this email
    return $first['login'];
  }

  // scopes - requested scopes; openid is always included.
  private function scopes() {
    $scope = $this->getParam('scope', 'openid email profile');
    if (strpos($scope, 'openid') === false) $scope = 'openid '.$scope;
    return $scope;
  }

  // verifyTls - whether to verify the provider's TLS certificate. Verification
  // is on unless 'insecure' is explicitly set (for dev with self-signed certs).
  private function verifyTls() {
    return !$this->getParam('insecure', false);
  }

  /**
   * getDiscovery - fetch (and cache for this request) the provider's OIDC
   * discovery document. Endpoints may instead be set explicitly in config to
   * skip the network round trip. Returns the discovery array or false.
   */
  function getDiscovery() {
    if ($this->discovery !== null) return $this->discovery;

    // Explicit endpoints bypass discovery.
    if ($this->getParam('authorization_endpoint') && $this->getParam('token_endpoint')) {
      $this->discovery = array(
        'issuer' => $this->getParam('issuer'),
        'authorization_endpoint' => $this->getParam('authorization_endpoint'),
        'token_endpoint' => $this->getParam('token_endpoint'),
        'end_session_endpoint' => $this->getParam('end_session_endpoint'));
      return $this->discovery;
    }

    $provider = rtrim((string)$this->getParam('provider_url'), '/');
    if (!$provider) return false;
    $body = $this->httpGet($provider.'/.well-known/openid-configuration');
    if ($body === false) return false;
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['authorization_endpoint']) || empty($data['token_endpoint']))
      return false;
    $this->discovery = $data;
    return $this->discovery;
  }

  // getRedirectUri - where the provider sends the user back. Prefer an explicit
  // config value; otherwise derive it from the current request.
  function getRedirectUri() {
    $uri = $this->getParam('redirect_uri');
    if ($uri) return $uri;
    $scheme = ttIsHttps() ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
    return $scheme.'://'.$host.$dir.'/oidc.php';
  }

  // getAuthUrl - the provider authorization URL to redirect the user to.
  function getAuthUrl($state, $nonce) {
    $d = $this->getDiscovery();
    if (!$d) return false;
    $query = http_build_query(array(
      'response_type' => 'code',
      'client_id'     => $this->getParam('client_id'),
      'redirect_uri'  => $this->getRedirectUri(),
      'scope'         => $this->scopes(),
      'state'         => $state,
      'nonce'         => $nonce));
    $sep = (strpos($d['authorization_endpoint'], '?') === false) ? '?' : '&';
    return $d['authorization_endpoint'].$sep.$query;
  }

  /**
   * exchangeCode - swap an authorization code for tokens and return the
   * validated ID token claims, or false on any failure.
   *
   * The ID token is received directly from the token endpoint over a
   * TLS-protected back channel. Per OIDC Core section 3.1.3.7, TLS server
   * validation may be used in place of verifying the token signature in this
   * case, which is why no JWKS/signature check is performed here. Issuer,
   * audience, expiry and nonce are still validated.
   */
  function exchangeCode($code, $expectedNonce) {
    $d = $this->getDiscovery();
    if (!$d) return false;

    $post = array(
      'grant_type'   => 'authorization_code',
      'code'         => $code,
      'redirect_uri' => $this->getRedirectUri(),
      'client_id'    => $this->getParam('client_id'));

    // Confidential client: authenticate to the token endpoint with the secret
    // using HTTP Basic (client_secret_basic).
    $secret = $this->getParam('client_secret');
    $userpwd = $secret ? ($this->getParam('client_id').':'.$secret) : null;

    $body = $this->httpPost($d['token_endpoint'], $post,
      array('Content-Type: application/x-www-form-urlencoded'), $userpwd);
    if ($body === false) return false;
    $token = json_decode($body, true);
    if (!is_array($token) || empty($token['id_token'])) return false;

    $claims = $this->decodeJwtPayload($token['id_token']);
    if (!is_array($claims)) return false;

    // Validate the ID token claims.
    $issuer = isset($d['issuer']) ? $d['issuer'] : $this->getParam('issuer');
    if ($issuer && isset($claims['iss']) && $claims['iss'] !== $issuer) return false;

    $clientId = $this->getParam('client_id');
    $aud = isset($claims['aud']) ? $claims['aud'] : null;
    if (is_array($aud)) {
      if (!in_array($clientId, $aud)) return false;
    } elseif ($aud !== $clientId) {
      return false;
    }

    if (!isset($claims['exp']) || time() >= (int)$claims['exp']) return false;

    if ($expectedNonce) {
      if (!isset($claims['nonce']) || !hash_equals((string)$expectedNonce, (string)$claims['nonce']))
        return false;
    }

    return $claims;
  }

  // getEndSessionUrl - RP-initiated logout URL, or null when the provider does
  // not advertise one, or when provider logout is not enabled in config. Enable
  // it by setting 'logout_from_provider' => true in the module parameters.
  function getEndSessionUrl() {
    if (!$this->getParam('logout_from_provider', false)) return null;
    $d = $this->getDiscovery();
    if (!$d || empty($d['end_session_endpoint'])) return null;
    $query = array('client_id' => $this->getParam('client_id'));
    $post_logout = $this->getParam('post_logout_redirect_uri');
    if ($post_logout) $query['post_logout_redirect_uri'] = $post_logout;
    $sep = (strpos($d['end_session_endpoint'], '?') === false) ? '?' : '&';
    return $d['end_session_endpoint'].$sep.http_build_query($query);
  }

  // decodeJwtPayload - base64url-decode a JWT's payload segment into an array.
  // NOTE: this does not verify the signature; see exchangeCode() for why that
  // is acceptable for a code exchanged over the TLS back channel.
  private function decodeJwtPayload($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return false;
    $payload = strtr($parts[1], '-_', '+/');
    $pad = strlen($payload) % 4;
    if ($pad) $payload .= str_repeat('=', 4 - $pad);
    $json = base64_decode($payload, true);
    if ($json === false) return false;
    $data = json_decode($json, true);
    return is_array($data) ? $data : false;
  }

  // httpGet / httpPost - minimal curl wrappers returning the body or false.
  private function httpGet($url) {
    return $this->httpRequest($url, null, array(), null);
  }

  private function httpPost($url, $fields, $headers, $userpwd) {
    return $this->httpRequest($url, $fields, $headers, $userpwd);
  }

  private function httpRequest($url, $postFields, $headers, $userpwd) {
    if (!function_exists('curl_init'))
      die('The php curl extension is required for OIDC authentication.');

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifyTls());
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifyTls() ? 2 : 0);
    if ($postFields !== null) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($userpwd) {
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
    }
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $status < 200 || $status >= 300) {
      if (isTrue('DEBUG')) echo 'OIDC HTTP request failed: '.$url.' status='.$status.'<br />';
      return false;
    }
    return $resp;
  }
}
