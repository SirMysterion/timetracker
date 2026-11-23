<?php
// ttApiMcp implements a stateless Model Context Protocol server over Streamable HTTP.
class ttApiMcp {
  const PROTOCOL_VERSIONS = array('2025-11-25', '2025-06-18', '2025-03-26');

  private $service;

  function __construct($service) {
    $this->service = $service;
  }

  function handle($method) {
    $this->checkOrigin();

    if ($method !== 'POST') {
      // This server does not offer a server-to-client event stream.
      header('Allow: POST');
      throw new ttApiError(405, 'method_not_allowed', 'This MCP endpoint accepts POST only.');
    }

    if (isset($_SERVER['HTTP_MCP_PROTOCOL_VERSION'])
      && !in_array($_SERVER['HTTP_MCP_PROTOCOL_VERSION'], self::PROTOCOL_VERSIONS, true)) {
      throw new ttApiError(400, 'unsupported_protocol_version', 'Unsupported MCP protocol version.');
    }

    $message = json_decode(ttApi::readBody(), true);
    if (!is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
      ttApi::send(400, $this->errorResponse(null, -32600, 'Invalid JSON-RPC request.'));
      return;
    }

    // Client responses and notifications need no reply.
    if (!isset($message['method']) || !array_key_exists('id', $message)) {
      http_response_code(202);
      header_remove('Content-Type');
      return;
    }

    ttApi::send(200, $this->dispatch($message));
  }

  // Browsers send Origin. Reject cross-site requests to prevent DNS rebinding.
  private function checkOrigin() {
    if (empty($_SERVER['HTTP_ORIGIN'])) return;
    $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    $port = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_PORT);
    if ($port) $origin .= ':'.$port;
    $target = defined('HTTP_TARGET') ? HTTP_TARGET : $_SERVER['HTTP_HOST'];
    if (!is_string($origin) || strcasecmp($origin, $target) !== 0)
      throw new ttApiError(403, 'forbidden', 'Cross-origin requests are not allowed.');
  }

  private function dispatch($message) {
    $id = $message['id'];
    $params = isset($message['params']) && is_array($message['params']) ? $message['params'] : array();

    switch ($message['method']) {
      case 'initialize':
        $requested = $params['protocolVersion'] ?? null;
        $version = in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0];
        return $this->result($id, array(
          'protocolVersion'=>$version,
          'capabilities'=>array('tools'=>new stdClass()),
          'serverInfo'=>array('name'=>'anuko-time-tracker', 'version'=>APP_VERSION),
          'instructions'=>'Manage the authenticated user\'s time entries. Call get_context first to learn today\'s date '.
            'and the required fields. Call list_projects to find project and task ids, and never guess them. '.
            'Check existing entries before creating one, and reuse the same idempotency_key when retrying a create.',
        ));
      case 'ping':
        return $this->result($id, new stdClass());
      case 'tools/list':
        return $this->result($id, array('tools'=>$this->tools()));
      case 'tools/call':
        return $this->callTool($id, $params);
      default:
        return $this->errorResponse($id, -32601, 'Method not found.');
    }
  }

  private function callTool($id, $params) {
    $name = $params['name'] ?? null;
    $args = $params['arguments'] ?? array();
    if (!is_string($name) || !is_array($args) || ($args && array_is_list($args)))
      return $this->errorResponse($id, -32602, 'Invalid tool call.');

    $available = array_column($this->tools(), 'name');
    if (!in_array($name, $available, true))
      return $this->errorResponse($id, -32602, 'Unknown tool: '.$name);

    try {
      return $this->result($id, $this->toolResult($this->runTool($name, $args), false));
    } catch (ttApiError $e) {
      return $this->result($id, $this->toolResult(array('error'=>$e->toArray()), true));
    }
  }

  private function runTool($name, $args) {
    switch ($name) {
      case 'get_context':
        return $this->service->context();
      case 'list_projects':
        return $this->service->projects();
      case 'list_time_entries':
        return $this->service->listEntries($args);
      case 'get_time_entry':
        return $this->service->getEntry($this->entryId($args));
      case 'create_time_entry':
        $key = $args['idempotency_key'] ?? null;
        unset($args['idempotency_key']);
        if ($key === null)
          throw new ttApiError(422, 'validation_failed', 'idempotency_key is required.');
        return $this->service->createEntry($args, $key);
      case 'update_time_entry':
        $entry_id = $this->entryId($args);
        unset($args['id']);
        return $this->service->updateEntry($entry_id, $args);
      case 'delete_time_entry':
        $entry_id = $this->entryId($args);
        $this->service->deleteEntry($entry_id);
        return array('deleted'=>true, 'id'=>(int)$entry_id);
    }
    throw new ttApiError(404, 'not_found', 'Unknown tool.');
  }

  private function entryId($args) {
    if (!isset($args['id']) || !is_int($args['id']) || $args['id'] <= 0)
      throw new ttApiError(422, 'validation_failed', 'id must be a positive integer.');
    return (string)$args['id'];
  }

  private function toolResult($data, $is_error) {
    $text = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $result = array('content'=>array(array('type'=>'text', 'text'=>$text)), 'structuredContent'=>$data);
    if ($is_error) $result['isError'] = true;
    return $result;
  }

  private function tools() {
    $entry_properties = array(
      'date'=>array('type'=>'string', 'description'=>'Date in YYYY-MM-DD format.'),
      'start'=>array('type'=>'string', 'description'=>'Start time in HH:MM 24-hour format. Use with finish, not duration.'),
      'finish'=>array('type'=>'string', 'description'=>'Finish time in HH:MM 24-hour format. Use with start, not duration.'),
      'duration'=>array('type'=>'string', 'description'=>'Duration in H:MM format, such as 1:30. Use instead of start and finish.'),
      'project_id'=>array('type'=>array('integer', 'null'), 'description'=>'Project id from list_projects.'),
      'task_id'=>array('type'=>array('integer', 'null'), 'description'=>'Task id from list_projects.'),
      'client_id'=>array('type'=>array('integer', 'null'), 'description'=>'Client id from list_projects.'),
      'note'=>array('type'=>'string', 'description'=>'Description of the work.'),
      'billable'=>array('type'=>'boolean', 'description'=>'Whether the time is billable.'),
    );
    $id_property = array('id'=>array('type'=>'integer', 'description'=>'Time entry id.'));

    $tools = array();
    if ($this->service->hasScope('time:read')) {
      $tools[] = $this->tool('get_context', 'Get the current user, today\'s date, and the rules for time entries.',
        array(), array(), array('readOnlyHint'=>true));
      $tools[] = $this->tool('list_projects', 'List projects, tasks, and clients available to the user.',
        array(), array(), array('readOnlyHint'=>true));
      $tools[] = $this->tool('list_time_entries', 'List the user\'s time entries in a date range, with totals.', array(
        'from'=>array('type'=>'string', 'description'=>'First date, YYYY-MM-DD. Defaults to today.'),
        'to'=>array('type'=>'string', 'description'=>'Last date, YYYY-MM-DD. Defaults to from.'),
        'limit'=>array('type'=>'integer', 'minimum'=>1, 'maximum'=>500),
        'offset'=>array('type'=>'integer', 'minimum'=>0),
      ), array(), array('readOnlyHint'=>true));
      $tools[] = $this->tool('get_time_entry', 'Get one of the user\'s time entries.',
        $id_property, array('id'), array('readOnlyHint'=>true));
    }
    if ($this->service->hasScope('time:write')) {
      $create_properties = $entry_properties + array('idempotency_key'=>array('type'=>'string',
        'description'=>'Unique key of 8 to 128 characters for this entry. Reuse it when retrying so no duplicate is created.'));
      $tools[] = $this->tool('create_time_entry', 'Create a time entry. Provide duration, or start and finish.',
        $create_properties, array('date', 'idempotency_key'), array('readOnlyHint'=>false, 'destructiveHint'=>false, 'idempotentHint'=>true));
      $tools[] = $this->tool('update_time_entry', 'Update fields of a time entry. Omitted fields stay unchanged.',
        $id_property + $entry_properties, array('id'), array('readOnlyHint'=>false, 'destructiveHint'=>true, 'idempotentHint'=>true));
    }
    if ($this->service->hasScope('time:delete')) {
      $tools[] = $this->tool('delete_time_entry', 'Delete a time entry. Confirm with the user first.',
        $id_property, array('id'), array('readOnlyHint'=>false, 'destructiveHint'=>true, 'idempotentHint'=>false));
    }
    return $tools;
  }

  private function tool($name, $description, $properties, $required, $annotations) {
    $schema = array('type'=>'object', 'properties'=>$properties ?: new stdClass(), 'additionalProperties'=>false);
    if ($required) $schema['required'] = $required;
    return array('name'=>$name, 'description'=>$description, 'inputSchema'=>$schema, 'annotations'=>$annotations);
  }

  private function result($id, $result) {
    return array('jsonrpc'=>'2.0', 'id'=>$id, 'result'=>$result);
  }

  private function errorResponse($id, $code, $message) {
    return array('jsonrpc'=>'2.0', 'id'=>$id, 'error'=>array('code'=>$code, 'message'=>$message));
  }
}
