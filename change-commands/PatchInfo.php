<?php
class commands_PatchInfo extends commands_AbstractChangeCommand
{
	/**
	 * @return String
	 */
	function getUsage()
	{
		return "<framework|[modules/]moduleName> <patchNumber>";
	}
	
	function getAlias()
	{
		return "pi";
	}

	/**
	 * @return String
	 */
	function getDescription()
	{
		return "Displays informations about a patch";
	}
	
	/**
	 * @param Integer $completeParamCount the parameters that are already complete in the command line
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @return String[] or null
	 */
	function getParameters($completeParamCount, $params, $options, $current)
	{
		if ($completeParamCount == 0)
		{
			$components = array();
			foreach ($this->getBootStrap()->getProjectDependencies() as $package)
			{
				/* @var $package c_Package */
				if (is_readable(f_util_FileUtils::buildPath($package->getPath(), 'patch', 'lastpatch')))
				{
					$components[] = $package->getKey();
				}
			}
			return $components;
		}
		elseif ($completeParamCount == 1)
		{
			$package = $this->getPackageByName($params[0]);
			if ($package)
			{
				$patches = PatchService::getInstance()->getPatchList($package->getName());
				if (count($patches)) 
				{
					return $patches;
				}
			}
		}
		return null;
	}

	/**
	 * @see c_ChangescriptCommand::validateArgs()
	 */
	protected function validateArgs($params, $options)
	{
		if (count($params) == 2)
		{
			$package = $this->getPackageByName($params[0]);	
			if ($package->isValid() && $package->isInProject() && ($package->isFramework() || $package->isModule()))
			{
				return true;
			}
			$this->errorMessage('Invalid package: ' . $params[0]);
		}
		return false;
	}
	
	
	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Patch information ==");
		$this->loadFramework();	
		$package = $this->getPackageByName($params[0]);	
		$moduleName = $package->getName();
		$patchNumber = $params[1];
		$this->message(PatchService::getInstance()->patchInfo($moduleName, $patchNumber));
	}
}