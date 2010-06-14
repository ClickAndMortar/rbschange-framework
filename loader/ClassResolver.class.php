<?php
class ClassResolver implements ResourceResolver
{
	/**
	 * the singleton instance
	 * @var ClassResolver
	 */
	private static $instance = null;

	/**
	 * Array of class path
	 * @var array
	 */
	private $classes = null;
	private $cacheDir = null;

	private $keys = array('%AG_LIB_DIR%', '%AG_MODULE_DIR%', '%FRAMEWORK_HOME%', '%PROJECT_OVERRIDE%', '%WEBEDIT_HOME%', '%PROFILE%');
	private $reps = array(AG_LIB_DIR, AG_MODULE_DIR, FRAMEWORK_HOME, PROJECT_OVERRIDE, WEBEDIT_HOME, PROFILE);

	private function __construct()
	{
		require_once(FRAMEWORK_HOME . '/util/FileUtils.class.php');
		require_once(FRAMEWORK_HOME . '/util/StringUtils.class.php');
		$this->cacheDir = f_util_FileUtils::buildCachePath('autoload');
		if (!is_dir($this->cacheDir))
		{
			//			if (php_sapi_name() == "cli")
			//			{
			//				echo "Initialize framework autoload. Please wait: can be long\n";
			//			}
			$this->initialize();
		}
	}

	/**
	 * Return the current ClassResolver
	 *
	 * @return ClassResolver
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance) )
		{
			self::$instance = new ClassResolver();
		}
		return self::$instance;
	}

	/**
	 * @var f_AOP
	 */
	private $aop;

	/**
	 * @return f_AOP
	 */
	private function getAOP()
	{
		if ($this->aop === null)
		{
			$this->aop = new f_AOP();
			if (AG_DEVELOPMENT_MODE)
			{
				$this->loadInjection();
			}
		}
		return $this->aop;
	}

	private function loadInjection()
	{
		// read config and get document injections
		$injections = Framework::getConfiguration("injection");
		if (isset($injections["document"]))
		{
			foreach ($injections["document"] as $injectedDoc => $replacerDoc)
			{
				//echo "AOP: replace document $injectedDoc => $replacerDoc\n";
				list($injectedModule, $injectedDoc) = explode("/", $injectedDoc);
				list($replacerModule, $replacerDoc) = explode("/", $replacerDoc);

				$this->aop->addReplaceClassAlteration($injectedModule."_persistentdocument_".$injectedDoc,
				$replacerModule."_persistentdocument_".$replacerDoc);

				$this->aop->addReplaceClassAlteration($injectedModule."_".ucfirst($injectedDoc)."Service",
				$replacerModule."_".ucfirst($replacerDoc)."Service");
			}
		}
	}

	function getAOPPath($className)
	{
		return f_util_FileUtils::buildChangeCachePath("aop", str_replace("_", DIRECTORY_SEPARATOR, $className), "to_include");
	}

	/**
	 * Return the path of the researched resource
	 *
	 * @param string $className Name of researched class
	 * @return string Path of resource
	 * @throws ClassNotFoundException if not found
	 */
	public function getPath($className)
	{
		$path = $this->getRessourcePath($className);
		if ($path === null)
		{
			$matches = array();
			if (AG_DEVELOPMENT_MODE && preg_match('/(.*)_replaced[0-9]+$/', $className, $matches))
			{
				$className = $matches[1];
				$path = $this->getRessourcePath($className);
			}

			if ($path === null)
			{
				throw new Exception("Could not find $className");
			}
		}

		if (AG_DEVELOPMENT_MODE)
		{
			$path = $this->rewritePathIfAOP($className, $path);
		}

		return $path;
	}

	private function getMaxModifiedTime($path, $aop, $alterations)
	{
		$maxMTime = max(filemtime($path), $aop->getAlterationsDefTime());
		foreach ($alterations as $alteration)
		{
			switch ($alteration[1])
			{
				case "renameParentClass":
					$alterationSources = array($alteration[2]);
					break;
				case "replaceClass":
					$alterationSources = array($alteration[2], $alteration[3]);
					break;
				default: $alterationSources = array($alteration[0]);
			}

			foreach ($alterationSources as $source)
			{
				$alteratorMTime = filemtime($this->getRessourcePath($source));
				if ($alteratorMTime > $maxMTime)
				{
					$maxMTime = $alteratorMTime;
				}
			}
		}
		return $maxMTime;
	}

	private function rewritePathIfAOP($className, $path, $justGivePath = false)
	{
		$aop = $this->getAOP();
		if (!$aop->hasAlterations())
		{
			return $path;
		}

		$alterations = array();
		// We must scan $path because it maybe contains other classes
		// than $className, classes that are altered.
		// autoload build process generates serialized infos about files
		$definedClassesPath = $path.".classes.ser";
		if (file_exists($definedClassesPath))
		{
			$definedClasses = unserialize(f_util_FileUtils::read($definedClassesPath));
			foreach ($definedClasses as $definedClass)
			{
				$otherAlterations = $aop->getAlterationsByClassName($definedClass);
				if ($otherAlterations !== null)
				{
					$alterations = array_merge($alterations, $otherAlterations);
				}
			}
		}
		else
		{
			$tokenArray = token_get_all(f_util_FileUtils::read($path));
			foreach ($tokenArray as $index => $token)
			{
				if ($token[0] == T_CLASS)
				{
					$otherClassName = $tokenArray[$index+2][1];
					$otherAlterations = $aop->getAlterationsByClassName($otherClassName);
					if ($otherAlterations !== null)
					{
						$alterations = array_merge($alterations, $otherAlterations);
					}
				}
			}
		}

		if (count($alterations) == 0)
		{
			//echo "Has no alteration $className\n";
			return $path;
		}

		$aopPath = $this->getAOPPath($className);
		if ($justGivePath)
		{
			return $aopPath;
		}

		if (!file_exists($aopPath) || $this->getMaxModifiedTime($path, $aop, $alterations) > filemtime($aopPath))
		{
			try
			{
				//echo "Alter File $className => $aopPath\n";
				$newFileContent = $aop->applyAlterations($alterations);
				f_util_FileUtils::writeAndCreateContainer($aopPath, $newFileContent, f_util_FileUtils::OVERRIDE);

			}
			catch (Exception $e)
			{
				$message = "Error while rewriting $path for class $className: ".$e->getMessage()."\n".$e->getTraceAsString();
				f_util_FileUtils::append(f_util_FileUtils::buildWebeditPath('log', PROFILE, 'aop.log'), $message);
				// TODO: remove die and let it throw ?
				die("<pre>\n$message\n</pre>\n");
			}
		}

		return $aopPath;
	}

	public function compileAOP()
	{
		if (AG_DEVELOPMENT_MODE)
		{
			throw new Exception(__METHOD__." is not for development mode");
		}
		$this->restoreAutoload();
		$aop = $this->getAOP();
		$this->loadInjection();
		$backupDir = f_util_FileUtils::buildCachePath("aop-backup");
		foreach ($aop->getAlterations() as $classAlterations)
		{
			$className = $classAlterations[0][2];
			$path = $this->getCachePath($className);
			if (!file_exists($path))
			{
				throw new Exception("Could not find $className in ".$this->cacheDir);
			}
			$newFileContent = $aop->applyAlterations($classAlterations);
			$backupPath = $this->getCachePath($className, $backupDir);

			f_util_FileUtils::mkdir(dirname($backupPath));
			if (!rename($path, $backupPath))
			{
				throw new Exception("Could not move ".$path." to ".$backupPath);
			}
			f_util_FileUtils::write($path, $newFileContent);
		}
	}

	private function restoreAutoload()
	{
		$backupDir = f_util_FileUtils::buildChangeCachePath("aop-backup");
		if (is_dir($backupDir))
		{
			foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupDir, RecursiveDirectoryIterator::KEY_AS_PATHNAME), RecursiveIteratorIterator::CHILD_FIRST) as $file => $info)
			{
				if ($info->isLink())
				{
					$originalPath = str_replace($backupDir, $this->cacheDir, $file);
					rename($file, $originalPath);
				}
				elseif ($info->isFile())
				{
					throw new Exception($file." is not a symlink ?! Corrupted autoload ?");
				}
				elseif ($info->isDir())
				{
					rmdir($file);
				}
			}
		}
	}

	/**
	 * @param string $className Name of researched class
	 * @return string Path of resource or null
	 */
	public function getPathOrNull($className)
	{
		$path = $this->getRessourcePath($className);
		if (AG_DEVELOPMENT_MODE && $path !== null)
		{
			$aopPath = $this->rewritePathIfAOP($className, $path);
			if (file_exists($aopPath))
			{
				return $aopPath;
			}
		}
		return $path;
	}

	private function getCachePath($className, $baseDir = null)
	{
		if ($baseDir === null)
		{
			$baseDir = $this->cacheDir;
		}
		return $baseDir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $className).DIRECTORY_SEPARATOR."to_include";
	}

	/**
	 * Launch this to create the cache of class
	 */
	public function initialize()
	{
		$modulesList = $this->getListOfModulesDependencies();

		$ini = Framework::getConfiguration('autoload');

		// let's do our fancy work
		foreach ($ini as $entry)
		{
			// file mapping or directory mapping?
			if (isset($entry['path']))
			{
				// directory mapping
				$ext  = isset($entry['ext']) ? $entry['ext'] : '.php';
				$path = $entry['path'];

				$path = $this->replaceConstants($path);

				// we automatically add our php classes
				require_once(FRAMEWORK_HOME .'/util/Finder.class.php');
				$finder = f_util_Finder::type('file')->ignore_version_control()->name('*'.$ext);

				// recursive mapping?
				$recursive = ((isset($entry['recursive'])) ? $entry['recursive'] : false);

				if (!$recursive)
				{
					$finder->maxdepth(0);
				}

				// exclude files or directories?
				if (isset($entry['exclude']))
				{
					if ( ! is_array($entry['exclude']))
					{
						$entry['exclude'] = explode(',', $entry['exclude']);
					}
					$finder->prune($entry['exclude'])->discard($entry['exclude']);
				}

				$sourcePath = $path;

				if( strpos($sourcePath, '%MODULE_NAME%') )
				{
					foreach ($modulesList as $module)
					{
						$path = str_replace('%MODULE_NAME%', $module, $sourcePath);
						$matches = $this->glob($path);
						if ($matches)
						{
							$files = $finder->in($matches);
						}
						else
						{
							$files = array();
						}
						$this->constructClassList($files);
					}
				}
				else
				{
					$matches = $this->glob($path);
					if ($matches)
					{
						$files = $finder->in($matches);
					}
					else
					{
						$files = array();
					}
					$this->constructClassList($files);
				}

			}
			else
			{
				// file mapping
				foreach ($entry as $class => $path)
				{
					$path = $this->replaceConstants($path);
					$this->appendToAutoloadFile($class, $path);
				}
			}
		}
	}

	private function glob($path)
	{	
		if (strpos($path,  '*') !== false  && basename($path) !== '*')
		{
			$result = glob($path);
			return (is_array($result) && count($result) > 0) ? $result : false;
		}
		$cleanPath = str_replace('/*', '', $path);
		$result = array($cleanPath);
		if (is_dir($cleanPath))
		{
			foreach (new DirectoryIterator($cleanPath) as $fileInfo)
			{
				if (!$fileInfo->isDot())
				{
					$result[] = realpath($fileInfo->getPathname());
				}
			}
		}
		return count($result) > 0 ? $result : false;
	}
	
	/**
	 * @param String $basePattern
	 * @return String[]
	 * @example getClassNames() return all available classes name
	 * @example getCLassNames("web") return all available classes that the name starts with "web"
	 */
	function getClassNames($basePattern = null)
	{
		$classNames = array();
		$path = f_util_FileUtils::buildCachePath("autoload");
		$pathLength = strlen($path)+1;

		if ($basePattern !== null)
		{
			$patternDirs = explode("_", $basePattern);

			$lastPatternPart = $patternDirs[count($patternDirs)-1];
			unset($patternDirs[count($patternDirs)-1]);
			if (count($patternDirs) > 0)
			{
				$path .= "/".join("/", $patternDirs);
			}
		}
		else
		{
			$lastPatternPart = '';
		}
		if ($lastPatternPart != '')
		{
			foreach (glob($path."/".$lastPatternPart."*", GLOB_MARK|GLOB_ONLYDIR|GLOB_NOSORT) as $subPath)
			{
				foreach (f_util_FileUtils::find("to_include", $subPath) as $toIncludePath)
				{
					$classNames[] = substr(str_replace("/", "_", dirname($toIncludePath)), $pathLength);
				}
			}
		}
		else
		{
			foreach (f_util_FileUtils::find("to_include", $path) as $toIncludePath)
			{
				$classNames[] = substr(str_replace("/", "_", dirname($toIncludePath)), $pathLength);
			}
		}
		return $classNames;
	}

	/**
	 * Update the autoload cache
	 */
	public function update()
	{
		$oldCacheDir = $this->cacheDir;
		$this->cacheDir = f_util_FileUtils::buildCachePath('autoload_tmp');

		try
		{
			f_util_FileUtils::rmdir($this->cacheDir);
			$this->initialize();
			rename($oldCacheDir, $oldCacheDir.'.old');
			rename($this->cacheDir, $oldCacheDir);
			f_util_FileUtils::rmdir($oldCacheDir.'.old');

		}
		catch (Exception $e)
		{
			// restore state in all cases
			$this->cacheDir = $oldCacheDir;
			throw $e;
		}
		$this->cacheDir = $oldCacheDir;
	}

	/**
	 * @deprecated
	 */
	public function loadCacheFile()
	{
		// nothing
	}

	function getRessourcePath($resource)
	{
		//		echo "getRessourcePath $resource<br/>\n";
		$cacheFile = $this->getCachePath($resource);
		//		echo "$cacheFile\n";
		if (file_exists($cacheFile))
		{
			return $cacheFile;
		}

		// Define package name
		$pearBase = PEAR_DIR . DIRECTORY_SEPARATOR . str_replace('_', '/', $resource);
		$paths = array($pearBase . '.php', $pearBase . '.class.php');

		// Return matched $path.$relativePath
		foreach ($paths as $path)
		{
			if (is_readable($path))
			{
				// Save to file cache
				$this->appendToAutoloadFile($resource, $path);
				return $path;
			}
		}
		return null;
	}

	/**
	 * @param String $class full class name
	 * @param String $filePath the file defining the class
	 * @return String the cache path
	 */
	public function appendToAutoloadFile($class, $filePath)
	{
		$cacheFile = $this->getCachePath($class);
		if (!file_exists($cacheFile))
		{
			f_util_FileUtils::mkdir(dirname($cacheFile));
			symlink($filePath, $cacheFile);
		}
		return $cacheFile;
	}


	/**
	 * Returns an array containing all the defined and autoloaded classes.
	 *
	 * @return array<string>
	 */
	public final function getDefinedClasses()
	{
		throw new Exception("ClassResolver->getDefinedClasses() is not implemented ; please do it");
		if (is_null($this->classes))
		{
			// TODO
		}
		return $this->classes;
	}

	/**
	 * @param String $filePath
	 * @return String[] the name of the classes defined in the file
	 */
	public function appendFile($filePath)
	{
		return $this->constructClassList(array($filePath));
	}

	/**
	 * @param String $dirPath
	 */
	public function appendDir($dirPath)
	{
		foreach (f_util_FileUtils::find("*.php", $dirPath) as $filePath)
		{
			//echo __METHOD__." $filePath\n";
			$this->appendFile($filePath);
		}
	}

	private function constructClassList($files)
	{
		foreach ($files as $file)
		{
			$tokenArray = token_get_all(file_get_contents($file));
			$definedClasses = array();
			$cachePaths = array();
			foreach ($tokenArray as $index => $token)
			{
				if ($token[0] == T_CLASS || $token[0] == T_INTERFACE)
				{
					$className = $tokenArray[$index+2][1];
					$definedClasses[] = $className;
					$cachePaths[] = $this->appendToAutoloadFile($className, $file);
				}
			}

			$definedSer = serialize($definedClasses);
			foreach ($cachePaths as $cachePath)
			{
				f_util_FileUtils::write($cachePath.".classes.ser", $definedSer, f_util_FileUtils::OVERRIDE);
			}
		}
	}

	private function getListOfModulesDependencies()
	{
		$list = array();
		if (!is_dir(AG_MODULE_DIR))
		{
			return $list;
		}
		foreach (scandir(AG_MODULE_DIR) as $name)
		{
			if($name != '.' && $name != '..')
			{
				$list[] = $name;
			}
		}

		return $list;

	}

	private function replaceConstants($value)
	{
		return str_replace($this->keys, $this->reps, $value);
	}
}
