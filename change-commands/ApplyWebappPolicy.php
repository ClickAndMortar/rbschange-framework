<?php
class commands_ApplyWebappPolicy extends commands_AbstractChangeCommand
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
		return "awp";
	}

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "Apply webapp policy: ownership & permissions";
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Apply webapp policy ==");
		
		$apacheGroup = $this->getApacheGroup();
		$user = $this->getUser();
		$dirs = array(WEB_CACHE_DIR, PROJECT_OVERRIDE,  
				f_util_FileUtils::buildWebeditPath('bin'),
				f_util_FileUtils::buildChangeBuildPath('apache'),
				f_util_FileUtils::buildChangeBuildPath('seo'));
		
		foreach ($dirs as $dir)
		{
			$this->message("Apply '$dir' dir policy");
			// Be sure the folder is here
			f_util_FileUtils::mkdir($dir);
			f_util_FileUtils::chown($dir, $user, $apacheGroup, true);
			// Strange behaviour when SGID on files: unable to write "directly", so use
			// different mode for files
			f_util_FileUtils::chmod($dir, "2775", true, "775");
		}
		
		$this->quitOk("Webapp files policy applied");
	}
}