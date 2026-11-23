<?php
import('ttTimeHelper');
import('ttConfigHelper');
import('ttDate');

// ttApiTimeService exposes the token owner's time entries. It applies the same rules as
// time.php, time_edit.php and time_delete.php, and only ever acts on the token owner.
class ttApiTimeService {
  const MAX_NOTE_CHARS = 4000;
  const MAX_RANGE_DAYS = 366;
  const IDEMPOTENCY_HOURS = 24;

  private $token;
  private $user;
  private $config;

  function __construct($token) {
    global $user;
    $this->token = $token;
    $this->user = $user;
    $this->config = new ttConfigHelper($user->getConfig());
  }

  function hasScope($scope) {
    return in_array($scope, $this->token['scopes'], true);
  }

  private function requireScope($scope) {
    if (!$this->hasScope($scope))
      throw new ttApiError(403, 'insufficient_scope', "This token does not have the $scope scope.");
    if (!$this->user->can('track_own_time'))
      throw new ttApiError(403, 'forbidden', 'This user cannot track time.');
  }

  function context() {
    $this->requireScope('time:read');
    $modes = array(MODE_TIME=>'time', MODE_PROJECTS=>'projects', MODE_PROJECTS_AND_TASKS=>'projects_and_tasks');
    $types = array(TYPE_ALL=>'start_finish_or_duration', TYPE_START_FINISH=>'start_finish', TYPE_DURATION=>'duration');
    return array(
      'user'=>array('id'=>(int)$this->user->id, 'login'=>$this->user->login, 'name'=>$this->user->name),
      'group'=>array('id'=>(int)$this->user->getGroup(), 'name'=>$this->user->getGroupName()),
      'token'=>array('name'=>$this->token['name'], 'scopes'=>$this->token['scopes']),
      'today'=>date('Y-m-d'),
      'timezone'=>date_default_timezone_get(),
      'rules'=>array(
        'tracking_mode'=>$modes[$this->user->getTrackingMode()],
        'record_type'=>$types[$this->user->getRecordType()],
        'project_required'=>$this->showProject(),
        'task_required'=>$this->taskRequired(),
        'client_required'=>$this->showClient() && $this->user->isOptionEnabled('client_required'),
        'future_entries_allowed'=>(bool)$this->user->isOptionEnabled('future_entries'),
        'overlap_allowed'=>(bool)$this->user->allow_overlap,
      ),
    );
  }

  function projects() {
    $this->requireScope('time:read');
    $tasks = array();
    foreach (($this->user->getAssignedTasks() ?: array()) as $task) {
      $tasks[(int)$task['id']] = array('id'=>(int)$task['id'], 'name'=>$task['name'], 'description'=>$task['description']);
    }

    $projects = array();
    foreach ($this->user->getAssignedProjects() as $project) {
      $project_tasks = array();
      foreach ($this->idList($project['tasks']) as $task_id) {
        if (isset($tasks[$task_id])) $project_tasks[] = $tasks[$task_id];
      }
      $projects[] = array('id'=>(int)$project['id'], 'name'=>$project['name'],
        'description'=>$project['description'], 'tasks'=>$project_tasks);
    }

    $clients = array();
    if ($this->showClient()) {
      foreach (($this->user->getAssignedClients() ?: array()) as $client) {
        $clients[] = array('id'=>(int)$client['id'], 'name'=>$client['name'],
          'project_ids'=>$this->idList($client['projects']));
      }
    }
    return array('projects'=>$projects, 'clients'=>$clients);
  }

  function listEntries($params) {
    $this->requireScope('time:read');
    $today = date('Y-m-d');
    $from = isset($params['from']) ? $params['from'] : $today;
    $to = isset($params['to']) ? $params['to'] : $from;
    if (!is_string($from) || !ttValidDbDateFormatDate($from) || !is_string($to) || !ttValidDbDateFormatDate($to))
      throw new ttApiError(422, 'validation_failed', 'from and to must be dates in YYYY-MM-DD format.');
    if ($from > $to)
      throw new ttApiError(422, 'validation_failed', 'from must not be after to.');
    if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > self::MAX_RANGE_DAYS)
      throw new ttApiError(422, 'validation_failed', 'The date range cannot exceed '.self::MAX_RANGE_DAYS.' days.');

    $limit = $this->intParam($params, 'limit', 100, 1, 500);
    $offset = $this->intParam($params, 'offset', 0, 0, 1000000);

    $mdb2 = getConnection();
    $where = $this->ownerWhere()." and l.date between ".$mdb2->quote($from)." and ".$mdb2->quote($to);
    $res = $mdb2->query("select count(*) as cnt, coalesce(sum(time_to_sec(l.duration)), 0) as secs from tt_log l where $where");
    if (is_a($res, 'PEAR_Error')) throw new ttApiError(500, 'internal_error', 'The request could not be completed.');
    $totals = $res->fetchRow();

    $res = $mdb2->query($this->entrySql()." where $where order by l.date, l.start, l.id limit $limit offset $offset");
    if (is_a($res, 'PEAR_Error')) throw new ttApiError(500, 'internal_error', 'The request could not be completed.');
    $entries = array();
    while ($row = $res->fetchRow()) $entries[] = $this->formatEntry($row);

    return array('from'=>$from, 'to'=>$to, 'total'=>(int)$totals['cnt'],
      'total_minutes'=>(int)round($totals['secs'] / 60), 'limit'=>$limit, 'offset'=>$offset, 'entries'=>$entries);
  }

  function getEntry($id) {
    $this->requireScope('time:read');
    return $this->formatEntry($this->fetchRow($id));
  }

  // createEntry returns array('entry'=>..., 'replayed'=>bool).
  function createEntry($input, $idempotency_key = null) {
    $this->requireScope('time:write');

    $reservation = null;
    if ($idempotency_key !== null) {
      $reservation = $this->reserveIdempotency($idempotency_key, $input);
      if (isset($reservation['entry_id']))
        return array('entry'=>$this->formatEntry($this->fetchRow($reservation['entry_id'])), 'replayed'=>true);
    }

    try {
      $fields = $this->normalize($input, null);
      $this->checkRules($fields, null);
      $id = ttTimeHelper::insert(array(
        'date'=>$fields['date'], 'client'=>$fields['client_id'], 'project'=>$fields['project_id'],
        'task'=>$fields['task_id'], 'start'=>$fields['start'] ?? '', 'finish'=>$fields['finish'] ?? '',
        'duration'=>$fields['duration'], 'note'=>$fields['note'], 'billable'=>$fields['billable'] ? 1 : 0));
      if (!$id) throw new ttApiError(500, 'internal_error', 'The time entry could not be saved.');
    } catch (Throwable $e) {
      if ($reservation) $this->releaseIdempotency($reservation['key_hash']);
      throw $e;
    }

    if ($reservation) $this->completeIdempotency($reservation['key_hash'], $id);
    $entry = $this->formatEntry($this->fetchRow($id));
    $this->audit('create', $id, null, $entry);
    return array('entry'=>$entry, 'replayed'=>false);
  }

  function updateEntry($id, $input) {
    $this->requireScope('time:write');
    if (!$input) throw new ttApiError(422, 'validation_failed', 'Provide at least one field to update.');

    $row = $this->fetchRow($id);
    $this->requireEditable($row);
    $before = $this->formatEntry($row);
    $fields = $this->normalize($input, $row);
    $this->checkRules($fields, $row);

    $saved = ttTimeHelper::update(array(
      'id'=>(int)$row['id'], 'date'=>$fields['date'], 'client'=>$fields['client_id'],
      'project'=>$fields['project_id'], 'task'=>$fields['task_id'], 'start'=>$fields['start'] ?? '',
      'finish'=>$fields['finish'] ?? '', 'duration'=>$fields['duration'], 'note'=>$fields['note'],
      'billable'=>$fields['billable'] ? 1 : 0, 'paid'=>$row['paid']));
    if (!$saved) throw new ttApiError(500, 'internal_error', 'The time entry could not be saved.');

    $after = $this->formatEntry($this->fetchRow($id));
    $this->audit('update', $id, $before, $after);
    return $after;
  }

  function deleteEntry($id) {
    $this->requireScope('time:delete');
    $row = $this->fetchRow($id);
    $this->requireEditable($row);
    $uncompleted = !$row['duration_seconds'];
    if (!$uncompleted && $this->user->isDateLocked($this->date($row['date'])))
      throw new ttApiError(409, 'date_locked', 'This date is locked.');

    $before = $this->formatEntry($row);
    if (!ttTimeHelper::delete((int)$row['id']))
      throw new ttApiError(500, 'internal_error', 'The time entry could not be deleted.');
    $this->audit('delete', $id, $before, null);
  }

  private function normalize($input, $row) {
    $allowed = array('date', 'start', 'finish', 'duration', 'project_id', 'task_id', 'client_id', 'note', 'billable');
    $unknown = array_diff(array_keys($input), $allowed);
    if ($unknown)
      throw new ttApiError(422, 'validation_failed', 'Unknown fields: '.implode(', ', $unknown).'.');

    $errors = array();
    $fields = array(
      'date'=>$row ? $row['date'] : null,
      'project_id'=>$row && $row['project_id'] ? (int)$row['project_id'] : null,
      'task_id'=>$row && $row['task_id'] ? (int)$row['task_id'] : null,
      'client_id'=>$row && $row['client_id'] ? (int)$row['client_id'] : null,
      'note'=>$row ? (string)$row['comment'] : '',
      'billable'=>$row ? (bool)$row['billable'] : true,
    );

    if (array_key_exists('date', $input)) {
      if (is_string($input['date']) && ttValidDbDateFormatDate($input['date'])) $fields['date'] = $input['date'];
      else $errors['date'] = 'Must be a date in YYYY-MM-DD format.';
    } elseif (!$row) {
      $errors['date'] = 'Required.';
    }

    foreach (array('project_id', 'task_id', 'client_id') as $key) {
      if (!array_key_exists($key, $input)) continue;
      if ($input[$key] === null) $fields[$key] = null;
      elseif (is_int($input[$key]) && $input[$key] > 0) $fields[$key] = $input[$key];
      else $errors[$key] = 'Must be a positive integer or null.';
    }

    if (array_key_exists('note', $input)) {
      $note = $input['note'];
      if ($note !== null && !is_string($note)) $errors['note'] = 'Must be a string.';
      elseif (!ttValidString($note, true, self::MAX_NOTE_CHARS)) $errors['note'] = 'Contains unsupported content or is too long.';
      elseif ($this->user->isPluginEnabled('tp') && !ttValidTemplateText($note)) $errors['note'] = 'Does not match the required template.';
      else $fields['note'] = trim((string)$note);
    }

    if (array_key_exists('billable', $input)) {
      if (is_bool($input['billable'])) $fields['billable'] = $input['billable'];
      else $errors['billable'] = 'Must be true or false.';
    }

    $this->normalizeTimes($input, $row, $fields, $errors);
    $this->checkAssignments($input, $row, $fields, $errors);

    if ($errors)
      throw new ttApiError(422, 'validation_failed', 'The time entry is not valid.', $errors);
    return $fields;
  }

  private function normalizeTimes($input, $row, &$fields, &$errors) {
    $has_duration = isset($input['duration']);
    $has_times = isset($input['start']) || isset($input['finish']);
    $record_type = $this->user->getRecordType();

    if ($has_duration && $has_times) {
      $errors['duration'] = 'Provide either duration, or start and finish, not both.';
      return;
    }

    $fields['start'] = $fields['finish'] = $fields['duration'] = null;
    if ($has_duration) {
      if ($record_type == TYPE_START_FINISH) $errors['duration'] = 'This group requires start and finish times.';
      elseif (!is_string($input['duration']) || !preg_match('/^([0-9]|1[0-9]|2[0-3]):[0-5][0-9]$|^24:00$/', $input['duration'])
        || $input['duration'] === '0:00' || $input['duration'] === '00:00')
        $errors['duration'] = 'Must be a positive duration in H:MM format, up to 24:00.';
      else $fields['duration'] = $input['duration'];
      return;
    }

    if ($has_times) {
      if ($record_type == TYPE_DURATION) {
        $errors['start'] = 'This group requires a duration.';
        return;
      }
      $start = array_key_exists('start', $input) ? $input['start'] : ($row ? $row['start_time'] : null);
      $finish = array_key_exists('finish', $input) ? $input['finish'] : ($row ? $row['finish_time'] : null);
      if (!is_string($start) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $start))
        $errors['start'] = 'Must be a time in HH:MM 24-hour format.';
      if (!is_string($finish) || !preg_match('/^(([01][0-9]|2[0-3]):[0-5][0-9]|24:00)$/', $finish))
        $errors['finish'] = 'Must be a time in HH:MM 24-hour format.';
      if (!isset($errors['start']) && !isset($errors['finish']) && !ttTimeHelper::isValidInterval($start, $finish))
        $errors['finish'] = 'Must be after start.';
      $fields['start'] = $start;
      $fields['finish'] = $finish;
      return;
    }

    if (!$row) {
      $errors['duration'] = 'Provide either duration, or start and finish.';
    } elseif ($row['start_time'] !== null) {
      $fields['start'] = $row['start_time'];
      $fields['finish'] = $row['duration_seconds'] ? $row['finish_time'] : null;
    } else {
      $fields['duration'] = $row['duration_text'];
    }
  }

  private function checkAssignments($input, $row, &$fields, &$errors) {
    $changed = function($key) use ($input, $row) {
      return !$row || array_key_exists($key, $input);
    };

    $project = null;
    if ($this->showProject()) {
      if (!$fields['project_id']) {
        $errors['project_id'] = 'Required.';
      } else {
        foreach ($this->user->getAssignedProjects() as $assigned) {
          if ((int)$assigned['id'] === $fields['project_id']) $project = $assigned;
        }
        if (!$project && $changed('project_id')) $errors['project_id'] = 'Project is not assigned to this user.';
      }
    } elseif (isset($input['project_id'])) {
      $errors['project_id'] = 'This group does not track projects.';
    }

    if ($this->showTask()) {
      if (!$fields['task_id'] && $this->taskRequired()) {
        $errors['task_id'] = 'Required.';
      } elseif ($fields['task_id'] && ($changed('task_id') || $changed('project_id'))) {
        if (!$project || !in_array($fields['task_id'], $this->idList($project['tasks']), true))
          $errors['task_id'] = 'Task is not available for this project.';
      }
    } elseif (isset($input['task_id'])) {
      $errors['task_id'] = 'This group does not track tasks.';
    }

    if ($this->showClient()) {
      if (!$fields['client_id'] && $this->user->isOptionEnabled('client_required')) {
        $errors['client_id'] = 'Required.';
      } elseif ($fields['client_id'] && ($changed('client_id') || $changed('project_id'))) {
        $client = null;
        foreach (($this->user->getAssignedClients() ?: array()) as $assigned) {
          if ((int)$assigned['id'] === $fields['client_id']) $client = $assigned;
        }
        if (!$client) $errors['client_id'] = 'Client is not available to this user.';
        elseif ($fields['project_id'] && !in_array($fields['project_id'], $this->idList($client['projects']), true))
          $errors['client_id'] = 'Client is not linked to this project.';
      }
    } elseif (isset($input['client_id'])) {
      $errors['client_id'] = 'This group does not track clients.';
    }
  }

  private function checkRules($fields, $row) {
    if (!ttTimeHelper::canAdd())
      throw new ttApiError(403, 'expired', 'This installation no longer accepts time entries.');

    // Entries with required custom fields cannot be created until the API supports them.
    if (!$row && $this->user->isPluginEnabled('cf')) {
      require_once(APP_DIR.'/plugins/CustomFields.class.php');
      $custom_fields = new CustomFields();
      foreach (($custom_fields->timeFields ?: array()) as $field) {
        if ($field['required'])
          throw new ttApiError(422, 'custom_fields_required', 'This group requires custom fields that the API does not support yet.');
      }
    }

    $date = $this->date($fields['date']);
    if (!$this->user->isOptionEnabled('future_entries')) {
      $tomorrow = new ttDate();
      $tomorrow->incrementDay();
      if ($date->after($tomorrow))
        throw new ttApiError(409, 'future_date', 'This group does not allow future entries.');
    }
    if ($row && $this->user->isDateLocked($this->date($row['date'])))
      throw new ttApiError(409, 'date_locked', 'This entry is on a locked date.');
    if ($this->user->isDateLocked($date))
      throw new ttApiError(409, 'date_locked', 'This date is locked.');

    if ($fields['start'] && ttTimeHelper::overlaps($this->user->getUser(), $fields['date'],
      $fields['start'], $fields['finish'], $row ? (int)$row['id'] : null)) {
      throw new ttApiError(409, 'overlap', 'This time overlaps an existing entry.');
    }
  }

  private function requireEditable($row) {
    if ($row['approved'] || $row['timesheet_id'] || $row['invoice_id'])
      throw new ttApiError(409, 'entry_not_editable', 'Approved, invoiced, or timesheet entries cannot be changed.');
  }

  private function reserveIdempotency($key, $input) {
    if (!is_string($key) || !preg_match('/^[\x21-\x7e]{8,128}$/', $key))
      throw new ttApiError(422, 'validation_failed', 'Idempotency key must be 8 to 128 printable characters.');

    $mdb2 = getConnection();
    $token_id = $this->token['id'];
    $key_hash = hash('sha256', $key);
    ksort($input);
    $request_hash = hash('sha256', json_encode($input));

    $mdb2->exec("delete from tt_api_idempotency where created < now() - interval ".self::IDEMPOTENCY_HOURS." hour");
    $affected = $mdb2->exec("insert ignore into tt_api_idempotency (token_id, key_hash, request_hash, created) values (".
      "$token_id, ".$mdb2->quote($key_hash).", ".$mdb2->quote($request_hash).", now())");
    if (is_a($affected, 'PEAR_Error')) throw new ttApiError(500, 'internal_error', 'The request could not be completed.');
    if ($affected == 1) return array('key_hash'=>$key_hash);

    $res = $mdb2->query("select request_hash, entry_id from tt_api_idempotency where token_id = $token_id and key_hash = ".$mdb2->quote($key_hash));
    $existing = is_a($res, 'PEAR_Error') ? null : $res->fetchRow();
    if (!$existing || $existing['request_hash'] !== $request_hash)
      throw new ttApiError(409, 'idempotency_conflict', 'This idempotency key was used with a different request.');
    if (!$existing['entry_id'])
      throw new ttApiError(409, 'request_in_progress', 'A request with this idempotency key is in progress.');
    return array('key_hash'=>$key_hash, 'entry_id'=>$existing['entry_id']);
  }

  private function completeIdempotency($key_hash, $entry_id) {
    $mdb2 = getConnection();
    $mdb2->exec("update tt_api_idempotency set entry_id = ".(int)$entry_id." where token_id = ".$this->token['id'].
      " and key_hash = ".$mdb2->quote($key_hash));
  }

  private function releaseIdempotency($key_hash) {
    $mdb2 = getConnection();
    $mdb2->exec("delete from tt_api_idempotency where token_id = ".$this->token['id']." and key_hash = ".$mdb2->quote($key_hash));
  }

  // audit records which token changed an entry, with before and after values.
  private function audit($action, $entry_id, $before, $after) {
    $mdb2 = getConnection();
    $encode = function($value) use ($mdb2) {
      return $value === null ? 'null' : $mdb2->quote(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    };
    $sql = "insert into tt_api_audit (token_id, user_id, action, entry_id, before_json, after_json, ip, created) values (".
      $this->token['id'].", ".(int)$this->user->id.", ".$mdb2->quote($action).", ".(int)$entry_id.", ".
      $encode($before).", ".$encode($after).", ".$mdb2->quote($_SERVER['REMOTE_ADDR']).", now())";
    $affected = $mdb2->exec($sql);
    if (is_a($affected, 'PEAR_Error')) error_log('Time Tracker API: could not write audit record for entry '.(int)$entry_id);
  }

  private function ownerWhere() {
    return "l.user_id = ".(int)$this->user->getUser()." and l.group_id = ".(int)$this->user->getGroup().
      " and l.org_id = ".(int)$this->user->org_id." and l.status = 1";
  }

  private function entrySql() {
    return "select l.id, l.date, TIME_FORMAT(l.start, '%H:%i') as start_time,".
      " TIME_FORMAT(sec_to_time(time_to_sec(l.start) + time_to_sec(l.duration)), '%H:%i') as finish_time,".
      " TIME_FORMAT(l.duration, '%k:%i') as duration_text, time_to_sec(l.duration) as duration_seconds,".
      " l.project_id, p.name as project_name, l.task_id, t.name as task_name, l.client_id, c.name as client_name,".
      " l.comment, l.billable, l.approved, l.paid, l.timesheet_id, l.invoice_id from tt_log l".
      " left join tt_projects p on (p.id = l.project_id)".
      " left join tt_tasks t on (t.id = l.task_id)".
      " left join tt_clients c on (c.id = l.client_id)";
  }

  private function fetchRow($id) {
    if (!preg_match('/^[1-9][0-9]{0,18}$/', (string)$id))
      throw new ttApiError(404, 'not_found', 'Time entry not found.');

    $mdb2 = getConnection();
    $res = $mdb2->query($this->entrySql()." where l.id = $id and ".$this->ownerWhere());
    if (is_a($res, 'PEAR_Error')) throw new ttApiError(500, 'internal_error', 'The request could not be completed.');
    $row = $res->fetchRow();
    if (!$row) throw new ttApiError(404, 'not_found', 'Time entry not found.');
    return $row;
  }

  private function formatEntry($row) {
    $seconds = (int)$row['duration_seconds'];
    $locked = $this->user->isDateLocked($this->date($row['date']));
    return array(
      'id'=>(int)$row['id'],
      'date'=>$row['date'],
      'start'=>$row['start_time'],
      'finish'=>$seconds ? $row['finish_time'] : null,
      'duration'=>$row['duration_text'],
      'duration_minutes'=>(int)round($seconds / 60),
      'project'=>$row['project_id'] ? array('id'=>(int)$row['project_id'], 'name'=>$row['project_name']) : null,
      'task'=>$row['task_id'] ? array('id'=>(int)$row['task_id'], 'name'=>$row['task_name']) : null,
      'client'=>$row['client_id'] ? array('id'=>(int)$row['client_id'], 'name'=>$row['client_name']) : null,
      'note'=>$row['comment'],
      'billable'=>(bool)$row['billable'],
      'approved'=>(bool)$row['approved'],
      'paid'=>(bool)$row['paid'],
      'editable'=>!$row['approved'] && !$row['timesheet_id'] && !$row['invoice_id'] && !$locked,
    );
  }

  private function date($value) {
    return new ttDate($value, DB_DATEFORMAT);
  }

  private function intParam($params, $name, $default, $min, $max) {
    if (!isset($params[$name])) return $default;
    $value = $params[$name];
    if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int)$value < $min || (int)$value > $max)
      throw new ttApiError(422, 'validation_failed', "$name must be an integer from $min to $max.");
    return (int)$value;
  }

  private function idList($value) {
    if (!$value) return array();
    return array_values(array_map('intval', array_filter(explode(',', $value), 'ctype_digit')));
  }

  private function showProject() {
    $mode = $this->user->getTrackingMode();
    return $mode == MODE_PROJECTS || $mode == MODE_PROJECTS_AND_TASKS;
  }

  private function showTask() {
    return $this->user->getTrackingMode() == MODE_PROJECTS_AND_TASKS;
  }

  private function taskRequired() {
    return $this->showTask() && (bool)$this->config->getDefinedValue('task_required');
  }

  private function showClient() {
    return $this->user->isPluginEnabled('cl');
  }
}
