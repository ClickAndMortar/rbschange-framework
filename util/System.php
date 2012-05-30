<?php
class f_util_System
{
	public static function escapeCmd($cmd)
	{
		$cmd = mb_ereg_replace(", ", "\\, ", $cmd);
		$cmd = mb_ereg_replace(" ", "\\ ", $cmd);
		return $cmd;
	}

	/**
	 * @param String $cmd
	 * @param String $msg
	 * @param Boolean $captureStdout
	 * @param String input
	 * @return String the output result of execution
	 */
	public static function exec($cmd, $msg = null, $captureStdout = true, $input = null)
	{
		if ($msg !== null)
		{
			echo $msg."...";
		}

		$cmd .= " 2>&1";
		
		$descriptorspec = array(
		0 => array('pipe', 'r'), // stdin
		1 => array('pipe', 'w'), // stdout
		2 => array('pipe', 'w') // stderr
		);
		$proc = proc_open($cmd, $descriptorspec, $pipes);
		if (!is_resource($proc))
		{
			throw new Exception("Can not execute $cmd");
		}
		stream_set_blocking($pipes[2], 0);
		if ($input !== null)
		{
			fwrite($pipes[0], $input);
		}
		fclose($pipes[0]);
		$output = "";
		while (!feof($pipes[1]))
		{
			$s = fread($pipes[1], 512);
			if ($s === false)
			{
				throw new Exception("Error while executing $cmd: could not read further execution result");
			}
			$output .= $s;
			if (!$captureStdout)
			{
				echo $s;
			}
		}

		$retVal = proc_close($proc);
		if (0 != $retVal)
		{
			throw new Exception("Could not execute $cmd (exit code $retVal):\n".$output);
		}
		if ($msg !== null)
		{
			echo " done\n";
		}
		return trim($output);
	}

	/**
	 * @param String $cmd
	 * @param String $msg
	 * @param Boolean $captureStdout
	 * @param String input
	 * @return array the output result of execution
	 */
	public static function execArray($cmd, $msg = null, $captureStdout = true, $input = null)
	{
		$out = self::exec($cmd, $msg, $captureStdout, $input);
		if (empty($out))
		{
			return array();
		}
		return explode("\n", $out);
	}
	
	
	/**
	 * @deprecated use f_util_System::execScript
	 */
	public static function execHTTPScript($relativeScriptPath, $arguments = array(), $noFramework = false, $baseUrl = null)
	{
		return self::execScript($relativeScriptPath, $arguments, $noFramework, $baseUrl);
	}
	
	/**
	 * @param string $relativeScriptPath to WEBEDIT_HOME
	 * @param array $arguments
	 * @param boolean $noFramework
	 * @param string $baseUrl
	 */
	public static function execScript($relativeScriptPath, $arguments = array(), $noFramework = false, $baseUrl = null)
	{	
		if (defined('PHP_CLI_PATH') && PHP_CLI_PATH != '' && !isset($_SERVER['REMOTE_ADDR']))
		{
			return self::execScriptConsole($relativeScriptPath, $arguments, $noFramework, $baseUrl);
		}
		return self::execScriptHTTP($relativeScriptPath, $arguments, $noFramework, $baseUrl);		
	}
	
	/**
	 * @param string $relativeScriptPath to WEBEDIT_HOME
	 * @param array $arguments
	 * @param boolean $noFramework
	 * @param string $baseUrl
	 * @return string | false
	 */
	protected static function execScriptConsole($relativeScriptPath, $arguments = array(), $noFramework = false, $baseUrl = null)
	{
		try 
		{
			$cmd = PHP_CLI_PATH . ' framework/bin/consoleScript.php ' . $relativeScriptPath . ' ' . ($noFramework ? '1' : '0') . ' ' . implode(" ", $arguments);
			return self::exec($cmd);
		}
		catch (Exception $e)
		{
			Framework::exception($e);
		}
		return false;
	}	
		
	/**
	 * @param string $relativeScriptPath to WEBEDIT_HOME
	 * @param array $arguments
	 * @param boolean $noFramework
	 * @param string $baseUrl
	 * @return string
	 */
	protected static function execScriptHTTP($relativeScriptPath, $arguments = array(), $noFramework = false, $baseUrl = null)
	{
		list($name, $secret) = explode('#', file_get_contents(WEBEDIT_HOME . '/build/config/oauth/script/consumer.txt'));
		$consumer = new f_web_oauth_Consumer($name, $secret);

		list($name, $secret) = explode('#', file_get_contents(WEBEDIT_HOME . '/build/config/oauth/script/token.txt'));	
		$token = new f_web_oauth_Token($name, $secret);
		
		if ($baseUrl === null) 
		{
			if (isset($_SERVER['REMOTE_ADDR']) && isset($_SERVER['HTTP_HOST']))
			{	
				$baseUrl = "http://".$_SERVER['HTTP_HOST'];
				if (strpos($_SERVER['HTTP_HOST'], ':') === false && isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80')
                {
					$baseUrl .= ':' . $_SERVER['SERVER_PORT'];
				}
			}
			else 
			{
				$baseUrl = 'http://'.Framework::getUIDefaultHost();
			}
		}
		
		$request = new f_web_oauth_Request($baseUrl .'/changescriptexec.php', $consumer, f_web_oauth_Request::METHOD_POST);
		$request->setParameter('phpscript', $relativeScriptPath);
		if ($noFramework)
		{
			$request->setParameter('noframework', 'true');
		}
		if (count($arguments) > 0)
		{
			$request->setParameter('argv', $arguments);
		}
		$request->setToken($token);
		$client = new f_web_oauth_HTTPClient($request);
		$client->getBackendClientInstance()->setTimeOut(0);
		$lp = Framework::getConfigurationValue('general/selfRequestProxy');
		if (!empty($lp)) {list($host, $port) = explode(':', $lp); $client->getBackendClientInstance()->setProxy($host, $port);}
		return $client->execute();		
	}
	
	/**
	 * @param string $relativeScriptPath to WEBEDIT_HOME
	 * @param array $arguments
	 */
	public static function execChangeCommand($commandName, $arguments = array())
	{
		if (defined('PHP_CLI_PATH') && PHP_CLI_PATH != ''&& !isset($_SERVER['REMOTE_ADDR']))
		{
			return self::execChangeConsoleCommand($commandName, $arguments);
		}
		return self::execChangeHTTPCommand($commandName, $arguments);
	}
	
	/**
	 * @param string $relativeScriptPath to WEBEDIT_HOME
	 * @param array $arguments
	 */
	public static function execChangeHTTPCommand($commandName, $arguments = array())
	{
		return self::execScriptHTTP("framework/bin/changeHTTP.php", array_merge(array($commandName), $arguments), true);
	}
	
	/**
	 * @param string $relativeScriptPath to WEBEDIT_HOME
	 * @param array $arguments
	 */
	public static function execChangeConsoleCommand($commandName, $arguments = array())
	{
		return self::exec(PHP_CLI_PATH . " framework/bin/change.php $commandName " . implode(" ", $arguments)); 
	}
}