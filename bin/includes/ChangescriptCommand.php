<?php
abstract class c_ChangescriptCommand
{
	
	const FG_RED = 31;
	const FG_GREEN = 32;
	const FG_MAGENTA = 35;
	
	/**
	 * @var c_ChangeBootStrap
	 */	
	private $bootStrap;
	
	
	/**
	 * @var Boolean
	 */
	private $devMode = false;
	
	/**
	 * @var String
	 */
	private $callName;
	
	/**
	 * @var String
	 */
	private $sectionName;	
	
	/**
	 * @var Boolean
	 */
	private $httpOutput  = false;
	
	/**
	 * @param c_ChangeBootStrap $bootStrap
	 * @param string $sectionName
	 * @param boolean $devMode
	 */
	public function __construct($bootStrap, $sectionName, $devMode)
	{
		$this->httpOutput = defined('HTTP_MODE');
		$this->bootStrap = $bootStrap;
		$this->sectionName = $sectionName;
		$cmdNamePrefix = ($sectionName === 'framework') ? '' : $sectionName . '.'; 
		$this->callName = $cmdNamePrefix . $this->getName();
		$this->devMode = $devMode;
	}
		
	/**
	 * @return String
	 */
	function getName()
	{
		$shortClassName = preg_replace('/^commands_(?:[^_]*_){0,1}(.*)$/', '${1}', get_class($this));
		return strtolower($shortClassName[0].preg_replace('/([A-Z])/', '-${0}', substr($shortClassName, 1)));
	}
	
	/**
	 * @return Boolean default false
	 */
	function httpOutput($httpOutput = null)
	{
		$result = $this->httpOutput;
		if ($httpOutput !== null)
		{
			$this->httpOutput = ($httpOutput == true);
		}
		return $result;
	}
		
	/**
	 * @return Boolean default false
	 */
	function isHidden()
	{
		return false;
	}
	
	/**
	 * @return String
	 */
	function getCallName()
	{
		return $this->callName;
	}
	
	/**
	 * @return string
	 */
	public function getSectionName()
	{
		return $this->sectionName;
	}

	/**
	 * @return String
	 */
	function getAlias()
	{
		return null;
	}
	
	
	/**
	 * @param boolean $devMode
	 * @return boolean
	 */
	function devMode($devMode = null)
	{
		$result = $this->devMode;
		if ($devMode !== null)
		{
			$this->devMode = ($devMode == true);
		}
		return $result;
	}
	
	/**
	 * @return c_ChangeBootStrap
	 */
	protected function getBootStrap()
	{
		return $this->bootStrap;
	}
	
	/**
	 * @return String
	 */
	abstract function getUsage();

	/**
	 * @return String
	 */
	function getDescription()
	{
		return null;
	}

	private $listeners;
	
	/**
	 * @param array<String, String[]> $listeners pointcut => commandName[]
	 */
	function setListeners($listeners)
	{
		$this->listeners = $listeners;	
	}
	
	/**
	 * @var array<String, Boolean>
	 */
	private $reachedPointCuts;
	
	/**
	 * @param String $pointcut
	 */
	protected function startPointCut($pointcut)
	{
		if (isset($this->listeners[$pointcut]) && !isset($this->reachedPointCuts[$pointcut]))
		{
			$this->reachedPointCuts[$pointcut] = true;
			foreach ($this->listeners[$pointcut] as $commandName)
			{
				$this->executeCommand($commandName);
			}
		}
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_Changescript::parseArgs($args)
	 */
	abstract function _execute($params, $options);

	/**
	 * @return String[]
	 */
	function getOptions()
	{
		return null;
	}

	/**
	 * @return string
	 */
	protected function getChangeCmdName()
	{
		if ($this->httpOutput)
		{
			return '';
		}
		return $this->getBootStrap()->getProperties()->getProperty("CHANGE_COMMAND", 'change.php');
	}

	
	/**
	 * @param Integer $completeParamCount the parameters that are already complete in the command line
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @param String $current current parameter that is being completed (can be empty)
	 * @return String[] or null
	 */
	function getParameters($completeParamCount, $params, $options, $current)
	{
		return null;
	}
	
	
	/**
	 * @param string $message
	 * @param integer $color FG_GREEN = 32 FG_MAGENTA = 35  FG_RED = 31;
	 */
	protected function echoMessage($message, $color = null)
	{
		if ($this->httpOutput)
		{
			if ($color === -1)
			{
				echo $message;				
			}
			else
			{
				$class = ($color === null) ? "row_std" : "row_" . $color;
				echo "<span class=\"$class\">", nl2br(htmlspecialchars($message)), "</span>";	
			}			
		}
		else
		{
			if ($color === null || $color === -1)
			{
				echo $message;
			}
			else
			{
				echo "\x1b[" , 2 . ';', $color, 'm' . $message. "\x1b[m";
			}
		}
	}
	
	/**
	 * @param string $message
	 * @param string $type
	 */
	public function log($message, $type = "info")
	{
		switch ($type)
		{
			case "error":
				$this->echoMessage($message . PHP_EOL, 31);
				break;
			case "warn":
				$this->echoMessage($message . PHP_EOL, 35);
				break;
			case "validation":
				$this->echoMessage($message . PHP_EOL, 32);
				break;
			case "raw":
				$this->echoMessage($message, -1);
				break;
			default:
				$this->echoMessage($message . PHP_EOL);
		}
	}
	
	/**
	 * @return string
	 */
	public function commandUsage()
	{
		$description = $this->getDescription();
		if ($description !== null)
		{
			$this->log(ucfirst($this->getCallName()).": ".$description);
		}
		$this->log("Usage: ".basename($this->getChangeCmdName())." ".$this->getCallName()." ".$this->getUsage());
	}

	/**
	 * @param String $message
	 * @param const $color one of the constants, optional
	 */
	protected function message($message, $color = null)
	{
		switch ($color) 
		{

			case 31:
			case 32:
			case 35:
				$this->echoMessage($message . PHP_EOL, $color);
				break;
			case -1:
				$this->echoMessage($message, $color);
				break;	
			default:
				$this->echoMessage($message . PHP_EOL);
				break;
		}
	}
	
	private $errorCount = 0;
	
	protected function hasError()
	{
		return $this->errorCount > 0;
	}

	protected function getErrorCount()
	{
		return $this->errorCount;
	}
	
	/**
	 * print colorized message (red)
	 * @param String $message
	 */
	protected function errorMessage($message, $increment = true)
	{
		if ($increment)
		{
			$this->errorCount = $this->errorCount +1;
		}
		$this->log($message, 'error');
	}
	
	protected function debugMessage($message)
	{
		$this->log($message);
	}
	
	/**
	 * print colorized message (magenta)
	 * @param String $message
	 */
	protected function warnMessage($message)
	{
		$this->log($message, 'warn');
	}
	
	/**
	 * print colorized message (green)
	 * @param String $message
	 */
	protected function okMessage($message)
	{
		$this->log($message, 'validation');
	}
	
	/**
	 * print colorized message (green)
	 * @param String $message
	 */
	protected function rawMessage($message)
	{
		$this->log($message, 'raw');
	}
	
	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @return boolean
	 */
	protected function validateArgs($params, $options)
	{
		return true;
	}

	/**
	 * @param String $question
	 * @param String $defaultValue
	 * @param Boolean $toLowerCase
	 * @return String the answer the user typed in, lowercase or the defaultValue
	 */
	protected final function question($question, $defaultValue = null, $toLowerCase = true)
	{
		$this->rawMessage($question." ");
		$answer = trim(fgets(STDIN));
		if ($answer == "")
		{
			return ($toLowerCase ? strtolower($defaultValue) : $defaultValue);
		}
		return ($toLowerCase ? strtolower($answer) : $answer);
	}

	/**
	 * @param String $question
	 * @return Boolean true if setAutoY or if the user entered y
	 */
	protected final function yesNo($question)
	{
		return $this->question($question." (y/N)", "n") == "y";
	}

	/**
	 * @param String[] $args
	 * @return Boolean true if the command runned correctly
	 */
	final function execute($params, $options)
	{
		if (!$this->validateArgs($params, $options))
		{
			$this->errorMessage('Inavlid argrument for command: ' . $this->callName);
			return false;
		}
		$this->reachedPointCuts = array();
		$this->startPointCut("before");
		$ret = $this->_execute($params, $options);
		$res = ($ret === null || $ret === true);
		if ($res)
		{
			$this->startPointCut("after");
		}
		return $res;
	}

	/**
	 * @return null
	 */
	protected function quit($msg = "Exiting...")
	{
		$this->message("=> ".$msg . PHP_EOL);
		return null;
	}

	/**
	 * @return null
	 */
	protected final function quitOk($msg = "Exiting...")
	{
		$this->startPointCut("after");
		$this->okMessage("=> ".$msg . PHP_EOL);
		return null;
	}

	/**
	 * @return false
	 */
	protected final function quitError($msg)
	{
		$this->errorMessage("=> ".$msg . PHP_EOL);
		return false;
	}

	/**
	 * @return false
	 */
	protected final function quitWarn($msg)
	{
		$this->warnMessage("=> ".$msg . PHP_EOL);
		return null;
	}

	/**
	 * @return String
	 */
	protected function getAuthor()
	{
		$user = getenv("USER");
		if (empty($user))
		{
			return null;
		}
		return $user;
	}

	/**
	 * @return String
	 */
	protected function getUser()
	{
		return $this->getAuthor();
	}

	/**
	 * @return String
	 */
	protected function getApacheGroup()
	{
		$cdeps = $this->getComputedDeps();
		return $cdeps["WWW_GROUP"];
	}

	/**
	 * @return array
	 */
	protected function getComputedDeps()
	{
		return $this->getBootStrap()->getComputedDependencies();
	}

	/**
	 * @return void
	 */
	protected function loadFramework()
	{
		if (!class_exists("Framework", false))
		{
			foreach (spl_autoload_functions() as $fct) 
			{
				if (is_array($fct) && ($fct[0] instanceof c_ChangeBootStrap))
				{
					spl_autoload_unregister($fct);
				}
			}
			require_once(realpath(PROJECT_HOME."/framework/Framework.php"));
		}
	}

	/**
	 * @return String
	 */
	protected function getProfile()
	{
		if (file_exists("profile"))
		{
			return trim(f_util_FileUtils::read("profile"));
		}
		// Define profile
		$profile = trim($this->getAuthor());
		$this->warnMessage("No profile file, using user name as profile (".$profile.")");
		f_util_FileUtils::write("profile", $profile);
		return $profile;
	}

	/**
	 * @return util_Properties
	 */
	protected function getProperties()
	{
		return $this->getBootStrap()->getProperties("change");
	}
	
	
	/**
	 * Public executeCommand for other commands using
	 * @param String $cmdName
	 * @param String[] $args
	 */
	protected function executeCommand($cmdName, $args = array())
	{
		ob_start();
	    $this->loadFramework();
	    $fixedCommandName = strtolower($cmdName[0].preg_replace('/([A-Z])/', '-${0}', substr($cmdName, 1)));
	    echo f_util_System::execChangeCommand($fixedCommandName, $args);
	    $this->rawMessage(trim(ob_get_clean()));
	}
	

	/**
	 * @deprecated use $this
	 * @return c_ChangescriptCommand
	 */
	protected final function getParent()
	{
		return $this;
	}
	
	/**
	 * @deprecated use executeCommand
	 */
	protected final function forward($cmdName, $args)
	{
		$this->executeCommand($cmdName, $args);
	}
	
	/**
	 * @deprecated
	 */
	protected function systemExec($cmd, $msg = null)
	{
		ob_start();
		echo f_util_System::exec($cmd, $msg);
		$this->rawMessage(trim(ob_get_clean()));
	}
}

abstract class commands_AbstractChangeCommand extends c_ChangescriptCommand
{
	
}

abstract class commands_AbstractChangedevCommand extends c_ChangescriptCommand
{
	/**
	 * @deprecated use executeCommand
	 */
	protected function changecmd($cmdName, $params = array())
	{
		$this->executeCommand($cmdName, $params);
	}
}