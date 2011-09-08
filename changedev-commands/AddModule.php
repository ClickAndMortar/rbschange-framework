<?php
class commands_AddModule extends commands_AbstractChangedevCommand
{
	/**
	 * @return String
	 */
	public function getUsage()
	{
		$usage = "<moduleName> [--icon=<iconName>] [--hidden] [--category=<e-commerce|admin>]";
		return $usage;
	}

	public function getOptions()
	{
		return array('icon', 'hidden', 'category');
	}
	
	/**
	 * @return String
	 */
	public function getDescription()
	{
		return "add an empty module to your project.";
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 */
	protected function validateArgs($params, $options)
	{
		if (count($params) != 1)
		{
			return false;
		}
		$moduleName = $params[0];
		if (!preg_match('/^[a-z][a-z0-9]+$/', $moduleName))
		{
			$this->errorMessage("Invalid module name ([a-z][a-z0-9]+): " . $moduleName);
			return false;
		}
		if (isset($options['icon']) && !is_string($options['icon']))
		{
			$this->errorMessage("Invalid icon name : " . $options['icon']);
			return false;			
		}
		if (isset($options['category']) && !in_array($options['category'], array('e-commerce', 'admin')))
		{
			$this->errorMessage("Invalid category (e-commerce, admin): " . $options['category']);
			return false;			
		}
		return true;
	}

	/**
	 * @param String[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Add module ==");

		$moduleName = $params[0];
		$icon = (isset($options['icon'])) ? $options['icon'] : 'package';
		$hidden = (isset($options['hidden']) && $options['hidden'] == true);
		$category = null;
		if (isset($options['category']))
		{
			$category = $options['category'];
		}

		$this->loadFramework();
		$modulePath = f_util_FileUtils::buildModulesPath($moduleName);
		if (file_exists($modulePath))
		{
			return $this->quitError("Module $moduleName already exists");
		}
		$this->log('Create module dir: ' . $modulePath);
		f_util_FileUtils::mkdir($modulePath);

		// Make auto generated file
		$moduleGenerator = new builder_ModuleGenerator($moduleName);
		$moduleGenerator->setAuthor($this->getAuthor());
		$moduleGenerator->setVersion(FRAMEWORK_VERSION);
		$moduleGenerator->setTitle(ucfirst($moduleName) . ' module');
		$moduleGenerator->setIcon($icon);
		$moduleGenerator->setCategory($category);
		$moduleGenerator->setVisibility(!$hidden);
		$moduleGenerator->generateAllFile();
		
		$p = c_Package::getNewInstance('modules', $moduleName, PROJECT_HOME);
		$p->setDownloadURL('none');
		$p->setVersion(FRAMEWORK_VERSION);
		$this->getBootStrap()->updateProjectPackage($p);
		
		// Generate locale for new module
		LocaleService::getInstance()->regenerateLocalesForModule($moduleName);

		$this->executeCommand("clear-webapp-cache");
		$this->executeCommand("compile-config");
		$this->executeCommand("compile-documents");
		$this->executeCommand("compile-editors-config");
		$this->executeCommand("compile-roles");
		 
		return $this->quitOk('Module ' . $moduleName . ' ready');
	}
}