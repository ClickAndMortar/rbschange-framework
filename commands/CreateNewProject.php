<?php
class commands_CreateNewProject extends c_ChangescriptCommand
{
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "";
	}
	
	/**
	 * @return String
	 */
	function getDescription()
	{
		return "Create a new project";
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Create new project ==");
		
		$this->executeCommand("init");
		$this->executeCommand("generate-database");
		$this->executeCommand("compile-all");
		$this->executeCommand("import-init-data");
		$this->executeCommand("init-patch-db");
		f_util_System::execScript('changecron.php');
		$this->quitOk("Project is ready to use");
	}
}