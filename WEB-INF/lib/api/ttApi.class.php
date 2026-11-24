<?php
require_once(__DIR__.'/ttApiError.class.php');
require_once(__DIR__.'/ttApiToken.class.php');
require_once(__DIR__.'/ttApiTimeService.class.php');
require_once(__DIR__.'/ttApiMcp.class.php');

// ttApi authenticates API requests and routes them to the time service.
class ttApi {
  const MAX_BODY_BYTES = 65536;

  static function handle() {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    // Time entry dates are relative to the deployment time zone, when one is set.
    $tz = getenv('TZ');
    if ($tz && in_array($tz, timezone_identifiers_list(), true))
      date_default_timezone_set($tz);

    try {
      $token = self::authenticate();
      $service = new ttApiTimeService($token);
      $method = $_SERVER['REQUEST_METHOD'];
      $path = self::routePath();

      if ($path === '/mcp') {
        (new ttApiMcp($service))->handle($method);
        return;
      }
      self::route($service, $method, $path);
    } catch (ttApiError $e) {
      self::sendError($e);
    } catch (Throwable $e) {
      error_log('Time Tracker API error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
      self::send(500, array('error'=>array('code'=>'internal_error', 'message'=>'The request could not be completed.')));
    }
  }

  private static function route($service, $method, $path) {
    if ($path === '/v1/me') {
      self::allow($method, array('GET'));
      self::send(200, $service->context());
      return;
    }
    if ($path === '/v1/projects') {
      self::allow($method, array('GET'));
      self::send(200, $service->projects());
      return;
    }
    if ($path === '/v1/time-entries') {
      self::allow($method, array('GET', 'POST'));
      if ($method === 'GET') {
        self::send(200, $service->listEntries($_GET));
        return;
      }
      $key = isset($_SERVER['HTTP_IDEMPOTENCY_KEY']) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null;
      $result = $service->createEntry(self::readJsonObject(), $key);
      if ($result['replayed']) header('Idempotency-Replayed: true');
      self::send($result['replayed'] ? 200 : 201, $result['entry']);
      return;
    }
    if (preg_match('#^/v1/time-entries/([1-9][0-9]{0,18})$#', $path, $m)) {
      self::allow($method, array('GET', 'PATCH', 'DELETE'));
      if ($method === 'GET') {
        self::send(200, $service->getEntry($m[1]));
      } elseif ($method === 'PATCH') {
        self::send(200, $service->updateEntry($m[1], self::readJsonObject()));
      } else {
        $service->deleteEntry($m[1]);
        http_response_code(204);
        header_remove('Content-Type');
      }
      return;
    }
    throw new ttApiError(404, 'not_found', 'Unknown API endpoint.');
  }

  // Tokens are accepted only in the Authorization header, never in URLs.
  private static function authenticate() {
    $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if ($header === '' && function_exists('apache_request_headers')) {
      foreach (apache_request_headers() as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) $header = $value;
      }
    }

    $token = null;
    if (preg_match('/^Bearer ([A-Za-z0-9_]+)$/', $header, $m))
      $token = ttApiToken::authenticate($m[1]);
    if (!$token) {
      header('WWW-Authenticate: Bearer');
      throw new ttApiError(401, 'unauthorized', 'A valid API token is required.');
    }

    // Load the token owner as the current user for the application helpers.
    $GLOBALS['user'] = new ttUser(null, $token['user_id']);
    if (!$GLOBALS['user']->initialized || !self::ipAllowed($GLOBALS['user'])) {
      throw new ttApiError(403, 'forbidden', 'This token cannot be used from this location.');
    }
    // A manager can switch off API access for the whole group with the "ak" plugin.
    if (!$GLOBALS['user']->isPluginEnabled('ak')) {
      throw new ttApiError(403, 'api_disabled', 'API access is turned off for this group.');
    }
    return $token;
  }

  // ipAllowed applies the same group IP restriction as browser logins.
  private static function ipAllowed($user) {
    if (!$user->allow_ip || $user->can('override_allow_ip')) return true;
    foreach (explode(',', $user->allow_ip) as $allowed_ip) {
      if (substr($_SERVER['REMOTE_ADDR'], 0, strlen($allowed_ip)) === $allowed_ip) return true;
    }
    return false;
  }

  private static function routePath() {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pos = is_string($path) ? strpos($path, '/api/') : false;
    if ($pos === false) throw new ttApiError(404, 'not_found', 'Unknown API endpoint.');
    $route = substr($path, $pos + 4);
    return strlen($route) > 1 ? rtrim($route, '/') : $route;
  }

  private static function allow($method, $methods) {
    if (in_array($method, $methods, true)) return;
    header('Allow: '.implode(', ', $methods));
    throw new ttApiError(405, 'method_not_allowed', 'Method not allowed for this endpoint.');
  }

  // readBody returns the request body, enforcing a JSON content type and a size limit.
  static function readBody() {
    $type = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0])) : '';
    if ($type !== 'application/json')
      throw new ttApiError(415, 'unsupported_media_type', 'Content-Type must be application/json.');

    $body = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
    if ($body === false || strlen($body) > self::MAX_BODY_BYTES)
      throw new ttApiError(413, 'payload_too_large', 'Request body is too large.');
    return $body;
  }

  private static function readJsonObject() {
    $data = json_decode(self::readBody(), true);
    if (!is_array($data) || ($data && array_is_list($data)))
      throw new ttApiError(400, 'invalid_json', 'Request body must be a JSON object.');
    return $data;
  }

  static function send($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  }

  private static function sendError($e) {
    self::send($e->status, array('error'=>$e->toArray()));
  }
}
