<?php
class commands_UpdateDependencies extends c_ChangescriptCommand
{
	/**
	 * @return string
	 */
	function getUsage()
	{
		return "";
	}
	
	function getAlias()
	{
		return "upddep";
	}
	
	/**
	 * @return string
	 */
	function getDescription()
	{
		return "Update project dependencies";
	}

	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	function _execute($params, $options)
	{
		$this->message("== Update project dependencies ==");
		$bootstrap = $this->getBootStrap();
		$dependencies = $bootstrap->getProjectDependencies();
		
		$modulePaths =  glob(PROJECT_HOME . '/modules/*/install.xml');
		foreach ($modulePaths as $path) 
		{
			$name = basename(dirname($path));
			$doc = new DOMDocument('1.0', 'UTF-8');
			$doc->load($path);
			if ($doc->documentElement)
			{
				$p = c_Package::getInstanceFromPackageElement($doc->documentElement, PROJECT_HOME);
				if ($p->getType() === 'modules' && $p->getName() === $name && $p->getVersion())
				{
					if (!isset($dependencies[$p->getKey()]))
					{
						$this->log('Add module ' . $p->__toString() . ' in project install.xml');
						$bootstrap->updateProjectPackage($p);
						$dependencies[$p->getKey()] = $p;
					}
					continue;
				}
			}
			$this->warnMessage("Invalid Module signature in: " . $path);
		}
		$updateAutoload = false;
		$checked = array();
		while (true)
		{
			$newDeps = array();
			foreach ($dependencies as $package) 
			{
				/* @var $package c_Package */
				if (isset($checked[$package->getKey()])) {continue;}
				
				$checked[$package->getKey()] = true;
				if ($this->updateDependency($package)) {$updateAutoload = true;}
				
				$installDoc = $package->getInstallDocument();
				if ($installDoc)
				{
					foreach ($bootstrap->getDependenciesFromXML($installDoc) as $depPackage) 
					{
						if (!isset($dependencies[$depPackage->getKey()]))
						{
							$newDeps[$depPackage->getKey()] = $depPackage;
						}
					}
				}
			}
			if (count($newDeps) == 0) {break;}
			
			foreach ($newDeps as $depPackage) 
			{
				$dependencies[$depPackage->getKey()] = $depPackage;
			}
		}
		
		if ($updateAutoload)
		{
			require_once PROJECT_HOME . '/framework/config/ProjectParser.class.php';
			if (config_ProjectParser::isCompiled())
			{
				$this->log('Compile autoload...');
				$this->executeCommand('compile-autoload');
			}
			else
			{
				$this->log('Compile autoload. Please wait: this can be long.');
				change_AutoloadBuilder::getInstance()->update();
				$this->rawMessage('Please execute: ' . $this->getChangeCmdName() . ' compile-config');
			}
		}
		return $this->quitOk("== project dependencies updated ==");
	}
	
	/**
	 * @param c_Package $package
	 * @return boolean Project files updated
	 */
	protected function updateDependency($package)
	{
		$bootstrap = $this->getBootStrap();
		$currentXML = $package->getInstallDocument();
		$tmpPackage = $bootstrap->getPackageFromXML($currentXML);
		
		if ($tmpPackage === null) //Package not exist in project
		{
			if ($package->isStandalone()) //Nothing
			{
				$bootstrap->removeProjectDependency($package);
				$this->warnMessage("Remove standalone " . $package->getKey() . " from project install.xml");
				return false;
			}
			
			$downloadPackage = $this->downloadPackage($package, $package->getVersion() != null);
			if ($downloadPackage === null) //Package not Downloaded
			{
				$this->warnMessage("Invalid " . $package->getKey() . " in project install.xml");
				return false;
			}
			
			if ($package->getVersion() == null) //Update version from download
			{
				$package->setVersion($downloadPackage->getVersion());
				$bootstrap->updateProjectPackage($package);
				$this->message("Update version of " . $package->getKey() . " in project install.xml");
			}
			
			$this->message('Copy ' . $package->getKey() . '-' . $package->getVersion() . ' in project...');
			f_util_FileUtils::rmdir($package->getPath());
			f_util_FileUtils::cp($downloadPackage->getTemporaryPath(), $package->getPath());
			f_util_FileUtils::rmdir($downloadPackage->getTemporaryPath());
			
			return true;
		}
		
		if ($package->getVersion() !== $tmpPackage->getVersion())
		{		
			$package->setVersion($tmpPackage->getVersion());
			$bootstrap->updateProjectPackage($package);
			$this->message("Update version of " . $package->getKey() . " in project install.xml");
		}
		
		return false;
	}
	
	/**
	 * @param c_Package $package
	 * @param boolean $usePackageVersion
	 * @return c_Package or null
	 */
	protected function downloadPackage($package, $usePackageVersion = false)
	{
		$bootstrap = $this->getBootStrap();	
		$downloadURL = $package->getDownloadURL();
		if ($downloadURL === null)
		{
			$releaseURL = $package->getReleaseURL() == null ? $bootstrap->getReleaseRepository() : $package->getReleaseURL();
			$releasePackages = $bootstrap->getReleasePackages($releaseURL);
			if (!is_array($releasePackages))
			{
				$this->warnMessage('Inavlid releaseURL: ' . $releaseURL);
				return null;
			}
			if (!isset($releasePackages[$package->getKey()]))
			{
				$this->warnMessage('Inavlid package: ' . $package->getKey() . ' in ' . $releaseURL);
				return null;
			}
			
			if ($usePackageVersion)
			{
				$downloadURL = $releaseURL . $package->getRelativeReleasePath() . '.zip';
			}
			else
			{
				$downloadURL = $releasePackages[$package->getKey()]->getDownloadURL();
			}
		}
		
		$this->message('Download '. $downloadURL . '...');		
		$tmpFile = null;
		$dr = $bootstrap->downloadRepositoryFile($downloadURL, $tmpFile);
		if ($dr !== true)
		{
			$this->warnMessage($dr[0] . ' - ' . $dr[1]);
			return null;
		}
		
		$tmpPath = $tmpFile . '.unzip';
		$tmpPackage = $bootstrap->unzipPackage($tmpFile, $tmpPath);
		if ($tmpPackage === null)
		{
			$this->warnMessage('Invalid zip archive: ' . $tmpFile);
			return null;
		}
		elseif ($tmpPackage->getKey() != $package->getKey())
		{
			$this->warnMessage('Invalid package : ' . $tmpPackage->getKey());
			f_util_FileUtils::rmdir($tmpPackage->getTemporaryPath());
			return null;
		}
		elseif ($usePackageVersion && $tmpPackage->getVersion() != $package->getVersion())
		{
			$this->warnMessage('Invalid package version: ' . $tmpPackage->getVersion());
			f_util_FileUtils::rmdir($tmpPackage->getTemporaryPath());
			return null;
		}
		
		return $tmpPackage;		
	}
}