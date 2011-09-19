<?php
class commands_UpdateAutoload extends c_ChangescriptCommand
{
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "";
	}
	
	function getAlias()
	{
		return "ua";
	}

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "update autoload";
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Update autoload ==");
		
		$pearIncludePath = $this->getBootStrap()->getProperties()->getProperty('PEAR_INCLUDE_PATH', null);
		if ($pearIncludePath !== null)
		{
		    define('PEAR_DIR', $pearIncludePath);
		}
		
		if (f_util_ArrayUtils::isEmpty($params))
		{
			$this->message("Scanning all the project. Please wait: this can be long.");
			ClassResolver::getInstance()->update();
		}
		else
		{
			foreach ($params as $param)
			{
				$path = f_util_FileUtils::buildProjectPath($param);
				if (!file_exists($path))
				{
					$this->errorMessage("Could not resolve $param as file, ignoring");
					continue;
				}
				if (is_dir($path))
				{
					$this->message("Adding $path directory to autoload");
					ClassResolver::getInstance()->appendDir($path, true);
					continue;
				}
				$this->message("Adding $path file to autoload");
				ClassResolver::getInstance()->appendFile($path, true);
			}
		}
		
		if ($this->hasError())
		{
			return $this->quitError("Some errors: ".$this->getErrorCount());
		}
		return $this->quitOk("Autoload updated");
	}
}