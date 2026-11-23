<?php
// ttApiError is an error that is safe to return to an API client.
class ttApiError extends Exception {
  public $status;
  public $errorCode;
  public $details;

  function __construct($status, $errorCode, $message, $details = array()) {
    parent::__construct($message);
    $this->status = $status;
    $this->errorCode = $errorCode;
    $this->details = $details;
  }

  function toArray() {
    $error = array('code'=>$this->errorCode, 'message'=>$this->getMessage());
    if ($this->details) $error['details'] = $this->details;
    return $error;
  }
}
