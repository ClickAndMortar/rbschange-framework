<?php
$start = microtime(true);
define('WEBEDIT_HOME', dirname(realpath(__FILE__)));

// Set exception handler, called when an exception is not caught.
function KO_exception_handler($exception)
{
    if (strncasecmp(PHP_SAPI, 'cgi', 3))
	{
        header('HTTP/' . substr($_SERVER['SERVER_PROTOCOL'], - 3) . ' 500 Internal Server Error');
    } 
    else
	{
	    header('Status: 500 Internal Server Error');
	}

    Framework::exception($exception);
    $renderer = new exception_HtmlRenderer();
    $renderer->printStackTrace($exception);
}

if (get_magic_quotes_gpc()) {
    $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    while (list($key, $val) = each($process)) {
        foreach ($val as $k => $v) {
            unset($process[$key][$k]);
            if (is_array($v)) {
                $process[$key][stripslashes($k)] = $v;
                $process[] = &$process[$key][stripslashes($k)];
            } else {
                $process[$key][stripslashes($k)] = stripslashes($v);
            }
        }
    }
    unset($process);
}

set_exception_handler('KO_exception_handler');

// Starts the framework
require_once WEBEDIT_HOME . "/framework/Framework.php";

// Log request time and remote_addr.
if (Framework::isDebugEnabled())
{
	$intitilized = microtime(true);
    $requestId = $_SERVER['REMOTE_ADDR'] . " - " . $_SERVER['REQUEST_URI'];
    Framework::debug('|BENCH|'.($intitilized-$start).'|=== START ADMIN request |'.$requestId);
}


// Instantiate controller_XulController and dispatch the request
$controller = Controller::newInstance("controller_XulController");
$controller->dispatch();

if (Framework::isDebugEnabled())
{
    $end = microtime(true);
	Framework::debug('|BENCH|'.($end-$intitilized).'|=== END ADMIN request |'.$requestId);
	Framework::debug('|BENCH|'.(MysqlStatment::$time['exec'] + MysqlStatment::$time['read']).'|=== SQL Time |'. str_replace("\n", '', var_export(MysqlStatment::$time, true)));  
}

f_persistentdocument_PersistentProvider::getInstance()->closeConnection();