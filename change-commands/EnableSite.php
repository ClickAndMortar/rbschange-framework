<?php
class commands_EnableSite extends c_ChangescriptCommand
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
		return "es";
	}

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "enable site";
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Enable site ==");	
		$this->loadFramework();
		
		$flagFile = "site_is_disabled"; 
		if (!file_exists($flagFile))
		{
			return $this->quitWarn("Site is already enabled");
		}
		if (!unlink($flagFile))
		{
			return $this->quitError("Unable to unlink $flagFile");
		}	
		f_util_System::execScript('changecron.php');
		return $this->quitOk("Site has been enabled. To disable it, use '" . CHANGE_COMMAND . " disable-site'.");
	}
}