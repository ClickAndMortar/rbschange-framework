<?php
class commands_AddAction extends commands_AbstractChangedevCommand
{
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "<moduleName> <actionName>";
	}

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "add an action";
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		return count($params) == 2;
	}

	/**
	 * @param Integer $completeParamCount the parameters that are already complete in the command line
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @return String[] or null
	 */
	function getParameters($completeParamCount, $params, $options)
	{
		if ($completeParamCount == 0)
		{
			$components = array();
			foreach (glob("modules/*", GLOB_ONLYDIR) as $module)
			{
				$components[] = basename($module);
			}
			return $components;
		}
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Add action ==");

		$moduleName = $params[0];
		$actionName = ucfirst($params[1]);

		$this->loadFramework();

		$actionFile = FileResolver::getInstance()->setPackageName('modules_'.$moduleName)->setDirectory('actions')->getPath($actionName.'Action.class.php');
		if(file_exists($actionFile))
		{
			$this->warnMessage('Action "'.$actionName.'" already exists in module '.$moduleName.'".');
		}

		$actionFile = 'modules/'.$moduleName.'/actions/'.$actionName.'Action.class.php';
		$moduleGenerator = new builder_ModuleGenerator($moduleName);
		$moduleGenerator->setAuthor($this->getAuthor());
		$result = $moduleGenerator->generateFrontAction($actionName);
			
		f_util_FileUtils::write($actionFile, $result);
		$class = $moduleName.'_'.$actionName . 'Action';
		ClassResolver::getInstance()->appendToAutoloadFile($class, realpath($actionFile));
		$this->quitOk("Action $actioName added in module $moduleName
Please now edit $actionFile.");
	}
}