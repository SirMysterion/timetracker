<?php
// Front controller for the REST API (/api/v1/...) and the MCP endpoint (/api/mcp).

define('TT_STATELESS', true);
chdir(dirname(__DIR__));
require_once('initialize.php');
require_once(LIBRARY_DIR.'/api/ttApi.class.php');

ttApi::handle();
