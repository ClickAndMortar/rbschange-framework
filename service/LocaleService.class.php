<?php
/**
 * @deprecated use \Change\I18n\I18nManager
 */
class LocaleService
{
	/**
	 * @deprecated
	 */
	const SYNCHRO_MODIFIED = 'MODIFIED';
	
	/**
	 * @deprecated
	 */
	const SYNCHRO_VALID = 'VALID';
	
	/**
	 * @deprecated
	 */
	const SYNCHRO_SYNCHRONIZED = 'SYNCHRONIZED';

	/**
	 * @var LocaleService
	 */
	protected static $instance;
	
	/**
	 * @var \Change\I18n\I18nManager
	 */
	protected $wrappedI18nManager;

	/**
	 * @var \Change\Db\DbProvider
	 */
	protected $dbProvider;
	
	/**
	 * @deprecated use \Change\I18n\I18nManager
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = new static();
			self::$instance->wrappedI18nManager = \Change\Application::getInstance()->getApplicationServices()->getI18nManager();
			self::$instance->dbProvider = \Change\Application::getInstance()->getApplicationServices()->getDbProvider();
		}
		return self::$instance;
	}
	
	public function __call($name, $arguments)
	{
		return call_user_func_array(array($this->wrappedI18nManager, $name), $arguments);
	}
	
	/**
	 * @deprecated use \Change\I18n\PreparedKey
	 */
	public function explodeKey($cleanKey)
	{
		$parts = explode('.', strtolower($cleanKey));
		if (count($parts) < 3)
		{
			return array(false, false);
		}
	
		$id = end($parts);
		$keyPathParts = array_slice($parts, 0, -1);
		switch ($keyPathParts[0])
		{
			case 'f' :
			case 'm' :
			case 't' :
				break;
			case 'framework' :
				$keyPathParts[0] = 'f';
				break;
			case 'modules' :
				$keyPathParts[0] = 'm';
				break;
			case 'themes' :
				$keyPathParts[0] = 't';
				break;
			default :
				return array(false, false);
		}
		return array(implode('.', $keyPathParts), $id);
	}
	
	/**
	 * @deprecated use \Change\I18n\PreparedKey::isValid()
	 */
	public function isKey($string)
	{
		list ($path, ) = $this->explodeKey($string);
		return $path !== false;
	}
	
	/**
	 * @deprecated use \Change\I18n\I18nManager::formatKey()
	 */
	public function getFullKeyContent($lang, $cleanKey)
	{
		list ($keyPath, $id) = $this->explodeKey($cleanKey);
		if ($keyPath !== false)
		{
			$lcid = $this->getLCID($lang);
			list ($content, ) = $this->dbProvider->translate($lcid, $id, $keyPath);
	
			if ($content === null)
			{
				$this->logKeyNotFound($keyPath . '.' . $id, $lcid);
			}
			return $content;
		}
		Framework::warn('Invalid Key ' . $cleanKey);
		return null;
	}
	
	/**
	 * @depreacated use \Change\I18n\I18nManager::prepareKeyFromTransString()
	 */
	public function parseTransString($transString)
	{
		$key = $this->prepareKeyFromTransString($transString);
		return array($key->getKey(), $key->getFormatters(), $key->getReplacements());
	}
	
	// Keys compilation.
	// TODO: move.
	
	public function importOverride($name = null)
	{
		if ($name === null || $name === 'framework')
		{
			$i18nPath = f_util_FileUtils::buildOverridePath('framework', 'i18n');
			if (is_dir($i18nPath))
			{
				$this->importOverrideDir($i18nPath, 'f');
			}
			
			if ($name === 'framework')
			{
				return true;
			}
		}
		if ($name === null)
		{
			$pathPattern = f_util_FileUtils::buildOverridePath('themes', '*', 'i18n');
			$paths = glob($pathPattern, GLOB_ONLYDIR);
			if (is_array($paths))
			{
				foreach ($paths as $i18nPath)
				{
					$name = basename(dirname($i18nPath));
					$basekey = 't.' . $name;
					$this->importOverrideDir($i18nPath, $basekey);
				}	
			}

			$pathPattern = f_util_FileUtils::buildOverridePath('modules', '*', 'i18n');
			$paths = glob($pathPattern, GLOB_ONLYDIR);
			if (is_array($paths))
			{
				foreach ($paths as $i18nPath)
				{
					$name = basename(dirname($i18nPath));
					$basekey = 'm.' . $name;
					$this->importOverrideDir($i18nPath, $basekey);
				}
			}
		}
		else
		{
			$parts = explode('/', $name);
			if (count($parts) != 2)
			{
				return false;
			}
			if ($parts[0] === 'modules')
			{
				$basekey = 'm.' . $parts[1];
			}
			else if ($parts[0] === 'themes')
			{
				$basekey = 't.' . $parts[1];
			}
			else
			{
				return false;
			}
			$i18nPath = f_util_FileUtils::buildOverridePath($parts[0], $parts[1], 'i18n');
			if (is_dir($i18nPath))
			{
				$this->importOverrideDir($i18nPath, $basekey);
			}
		}
		return true;
	}
	
	private function importOverrideDir($dir, $baseKey)
	{
		foreach (scandir($dir) as $file)
		{
			if ($file[0] == ".")
			{
				continue;
			}
			$absFile = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_dir($absFile))
			{
				$this->importOverrideDir($absFile, $baseKey . '.' . $file);
			}
			elseif (f_util_StringUtils::endsWith($file, '.xml'))
			{
				$entities = array();
				//$entities[$lcid][$id] = array($content, $format);
				$this->processFile($absFile, $entities, false);
				if (count($entities))
				{
					echo "Import $baseKey\n";
					$this->updatePackage($baseKey, $entities);
				}
				echo "Remove file $absFile\n";
				unlink($absFile);
			}
		}	
		f_util_FileUtils::rmdir($dir);	
	}
	
	/**
	 * @param string $baseKey
	 * @param array $keysInfos [lcid => [id => [text, format]]
	 * @param boolean $override
	 * @param boolean $addOnly
	 * @param string $includes
	 */
	public function updatePackage($baseKey, $keysInfos, $override = false, $addOnly = false, $includes = '')
	{
		if (is_array($keysInfos))
		{
			foreach ($keysInfos as $lcid => $values)
			{
				if (strlen($lcid) === 5)
				{
					$this->updateI18nFile($baseKey, $lcid, $values, $includes, $override, $addOnly);
				}
			}
		}
	}

	private function getI18nFilePath($baseKey, $lcid, $override = false)
	{
		$parts = explode('.', $baseKey);
		$parts[] = $lcid . '.xml';
		switch ($parts[0])
		{
			case 'f' :
			case 'framework' :
				$parts[0] = '/framework/i18n';
				break;
			case 'm' :
			case 'modules' :
				$parts[0] = '/modules';
				$parts[1] .= '/i18n';
				break;
			case 't' :
			case 'themes' :
				$parts[0] = '/themes';
				$parts[1] .= '/i18n';
				break;
		}
		
		if ($override)
		{
			array_unshift($parts, 'override');
		}	
		return PROJECT_HOME . implode('/', $parts);
	}
	
	/**
	 * @param string $baseKey
	 * @param string $lcid
	 * @param array $values
	 * @param string $include
	 * @param boolean $override
	 * @param boolean $addOnly
	 */
	private function updateI18nFile($baseKey, $lcid, $values, $includes, $override, $addOnly)
	{
		$path = $this->getI18nFilePath($baseKey, $lcid, $override);
		
		if (is_readable($path))
		{
			$i18nDoc = f_util_DOMUtils::fromPath($path);
		}
		else
		{
			$i18nDoc = f_util_DOMUtils::fromString('<?xml version="1.0" encoding="utf-8"?><i18n/>');
		}
		$i18nNode = $i18nDoc->documentElement;
		$i18nNode->setAttribute('baseKey', $baseKey);
		$i18nNode->setAttribute('lcid', $lcid);
		if ($includes !== '')
		{
			foreach (explode(',', $includes) as $include)
			{
				$include = strtolower(trim($include));
				if ($include == '')
				{
					continue;
				}
				
				$includeNode = $i18nDoc->findUnique('include[@id="' . $include . '"]', $i18nNode);
				if ($includeNode === null)
				{
					$includeNode = $i18nDoc->createElement('include');
					$includeNode->setAttribute('id', $includes);
					if ($i18nNode->firstChild)
					{
						$i18nNode->insertBefore($includeNode, $i18nNode->firstChild);
					}
					else
					{
						$i18nNode->appendChild($includeNode);
					}
				}
			}
		}
		
		foreach ($values as $id => $value)
		{
			$id = strtolower($id);
			$keyNode = $i18nDoc->findUnique('key[@id="' . $id . '"]', $i18nNode);
			if ($keyNode !== null)
			{
				if ($addOnly)
				{
					continue;
				}
				$newNode = $i18nDoc->createElement('key');
				$i18nNode->replaceChild($newNode, $keyNode);
			}
			else
			{
				$newNode = $i18nNode->appendChild($i18nDoc->createElement('key'));
			}
			$newNode->setAttribute('id', $id);
			if (is_array($value))
			{
				list($content, $format) = $value;
				$format = strtolower($format);
				if ($format != 'text')
				{
					$newNode->setAttribute('format', $format);
					$newNode->appendChild($i18nDoc->createCDATASection($content));
				}
				else 
				{
					$newNode->appendChild($i18nDoc->createTextNode($content));
				}
			}
			else
			{
				$newNode->appendChild($i18nDoc->createTextNode($value));
			}
		}
		f_util_DOMUtils::save($i18nDoc, $path);
	}
	
	/**
	 * Regenerate all locales of application
	 */
	public function regenerateLocales()
	{
		$dbp = $this->dbProvider;
		try
		{
			$dbp->beginTransaction();		
			$dbp->clearTranslationCache();
			$this->processModules();
			$this->processFramework();
			$this->processThemes();
				
			$dbp->commit();
		}
		catch (Exception $e)
		{
			$dbp->rollBack($e);
			throw $e;
		}
	}
	
	/**
	 * Regenerate locale for a module and save in databases
	 *
	 * @param string $moduleName Example: users
	 */
	public function regenerateLocalesForModule($moduleName)
	{
		try
		{
			$this->dbProvider->beginTransaction();
			$this->dbProvider->clearTranslationCache('m.' . $moduleName);		
			// Processing module : $moduleName
			$this->processModule($moduleName);
			$this->dbProvider->commit();
		}
		catch (Exception $e)
		{
			$this->dbProvider->rollBack($e);
			throw $e;
		}
	}
	
	/**
	 * Regenerate locale for a theme and save in databases
	 *
	 * @param string $themeName Example: webfactory
	 */
	public function regenerateLocalesForTheme($themeName)
	{
		try
		{
			$this->dbProvider->beginTransaction();
			$this->dbProvider->clearTranslationCache('t.' . $themeName);
			$this->processTheme($themeName);
			$this->dbProvider->commit();
		}
		catch (Exception $e)
		{
			$this->dbProvider->rollBack($e);
			throw $e;
		}
	}
	
	/**
	 * Regenerate locale for the framework and save in databases
	 */
	public function regenerateLocalesForFramework()
	{
		try
		{
			$this->dbProvider->beginTransaction();
			$this->dbProvider->clearTranslationCache('f');
			$this->processFramework();
			$this->dbProvider->commit();
		}
		catch (Exception $e)
		{
			$this->dbProvider->rollBack($e);
			throw $e;
		}
	}
	
	/**
	 * Insert locale keys for all modules
	 */
	private function processModules()
	{
		$paths = glob(PROJECT_HOME . "/modules/*/i18n", GLOB_ONLYDIR);
		if (! is_array($paths))
		{
			return;
		}
		foreach ($paths as $path)
		{
			$moduleName = basename(dirname($path));
			$this->processModule($moduleName);
		}
	}
	
	private function processThemes()
	{
		$paths = glob(PROJECT_HOME . "/themes/*/i18n", GLOB_ONLYDIR);
		foreach ($paths as $path)
		{
			$themeName = basename(dirname($path));
			$this->processTheme($themeName);
		}
	}
	
	/**
	 * Compile locale for a module
	 * @param string $moduleName Example: users
	 */
	private function processModule($moduleName)
	{
		$availablePaths = change_FileResolver::getNewInstance()->getPaths('modules', $moduleName, 'i18n');
		if (!count($availablePaths))
		{
			return;
		}
		
		$availablePaths = array_reverse($availablePaths);
		// For all path found for the locale of module insert all localization keys
		foreach ($availablePaths as $path)
		{
			$this->processDir('m.' . $moduleName, $path);
		}
	}
	
	/**
	 * Compile locale for a theme
	 * @param string $themeName Example: webfactory
	 */
	private function processTheme($themeName)
	{
		$availablePaths = change_FileResolver::getNewInstance()->getPaths('themes', $themeName, 'i18n');
		if (!count($availablePaths))
		{
			return;
		}
		$availablePaths = array_reverse($availablePaths);
		
		// For all path found for the locale of module insert all localization keys
		foreach ($availablePaths as $path)
		{
			$this->processDir('t.' . $themeName, $path);
		}
	}
	
	/**
	 * Generate the framework localization
	 * @param string $dir
	 * @param string $basedir
	 */
	private function processFramework()
	{
		
		try
		{
			$this->dbProvider->beginTransaction();
			
			$availablePaths = array(f_util_FileUtils::buildFrameworkPath('i18n'), 
					f_util_FileUtils::buildOverridePath('framework', 'i18n'));
			foreach ($availablePaths as $path)
			{
				if (is_dir($path))
				{
					$this->processDir("f", $path);
				}
			}
			
			$this->dbProvider->commit();
		}
		catch (Exception $e)
		{
			$this->dbProvider->rollBack($e);
			throw $e;
		}
	}
	
	/**
	 * Parse recursively directory and launch the genration of localization for all locale XML file
	 *
	 * @param string $baseKey
	 * @param string $dir
	 */
	private function processDir($baseKey, $dir)
	{
		if (substr($dir, - 1) === DIRECTORY_SEPARATOR)
		{
			$dir = substr($dir, 0, strlen($dir) - 1);
		}
		
		if (is_dir($dir))
		{
			$dirs = array();
			$entities = array();
			foreach (scandir($dir) as $file)
			{
				if ($file[0] == ".")
				{
					continue;
				}
				$absFile = $dir . DIRECTORY_SEPARATOR . $file;
				if (is_dir($absFile))
				{
					$dirs[$baseKey . '.' . $file] = $absFile;
				}
				elseif (f_util_StringUtils::endsWith($file, '.xml'))
				{
					$this->processFile($absFile, $entities);

				}
			}
			
			if (count($entities))
			{
				if ($this->hasI18nKeysSynchro())
				{
					$this->applyI18nKeysSynchro($entities);
				}
				$this->processDatabase($baseKey, $entities);
			}
			
			foreach ($dirs as $baseKey => $dir)
			{
				$this->processDir($baseKey, $dir);
			}
		}
	}
	
	/**
	 * Read a file and extract informations of localization
	 * @param string $file
	 * @param array $entities
	 * @param boolean $processInclude
	 */
	private function processFile($file, &$entities, $processInclude = true)
	{
		$lcid = basename($file, '.xml');
		$dom = f_util_DOMUtils::fromPath($file);
		foreach ($dom->documentElement->childNodes as $node)
		{
			if ($node->nodeType == XML_ELEMENT_NODE)
			{
				if ($node->nodeName == 'include' && $processInclude)
				{
					$id = $node->getAttribute('id');
					$subPath = $this->getI18nFilePath($id, $lcid);
					$ok = false;
					if (file_exists($subPath))
					{
						$ok = true;
						$this->processFile($subPath, $entities);
					}
					$subPath = $this->getI18nFilePath($id, $lcid, true);
					if (file_exists($subPath))
					{
						$ok = true;
						$this->processFile($subPath, $entities);
					}
					if (! $ok && Framework::isWarnEnabled())
					{
						Framework::warn("Include ($id) not found in file $file");
					}
				}
				else if ($node->nodeName == 'key')
				{
					$id = $node->getAttribute('id');
					$content = $node->textContent;
					$format = $node->getAttribute('format') === 'html' ? 'HTML' : 'TEXT';
					$entities[$lcid][$id] = array($content, $format);
				}
			}
		}
	}
	
	/**
	 * @param string $keyPath
	 * @return array[id => [lcid => ['content' => string, 'useredited' => integer, 'format' => string]]]
	 */
	public function getPackageContentFromFile($keyPath)
	{
		$entities = array();
		foreach ($this->getSupportedLanguages() as $lang) 
		{
			$lcid = $this->getLCID($lang);
			$filePath = $this->getI18nFilePath($keyPath, $lcid);
			if (file_exists($filePath))
			{
				$this->processFile($filePath, $entities);
			}
			$filePath = $this->getI18nFilePath($keyPath, $lcid, true);
			if (file_exists($filePath))
			{
				$this->processFile($filePath, $entities);
			}
		}
		$results  = array();
		if (count($entities))
		{
			foreach ($entities as $lcid => $infos) 
			{
				foreach ($infos as $id => $entityInfos)
				{
					list($content, $format) = $entityInfos;
					$results[$id][$lcid] = array('content' => $content, 'useredited' => false, 'format' => $format);
				}
			}
		}
		return $results;
	}
	
	protected function applyI18nKeysSynchro(&$entities)
	{
		$syncConf = $this->getI18nKeysSynchro();
		if (count($syncConf) === 0) {return;}
		foreach ($syncConf as $to => $froms)
		{
			$toLCID = $this->getLCID($to);
			foreach ($froms as $from)
			{
				$fromLCID = $this->getLCID($from);
				if (isset($entities[$fromLCID]))
				{
					if (!isset($entities[$toLCID]))
					{
						$entities[$toLCID] = array();
					}
					foreach ($entities[$fromLCID] as $id => $data)
					{
						if (!isset($entities[$toLCID][$id]))
						{
							$entities[$toLCID][$id] = $data;
						}
					}
				}
			}
		}
	}
	
	/**
	 * @param string $keyPath
	 * @param array $entities
	 */
	protected function processDatabase($keyPath, $entities)
	{
		$keyPath = strtolower($keyPath);
		$lcids = array();
		foreach ($this->getSupportedLanguages() as $lang)
		{
			$lcids[$this->getLCID($lang)] = $lang;
		}
		
		foreach ($entities as $lcid => $infos)
		{
			if (! isset($lcids[$lcid]))
			{
				continue;
			}
			foreach ($infos as $id => $entityInfos)
			{
				list($content, $format) = $entityInfos;
				$this->dbProvider->addTranslate($lcid, strtolower($id), $keyPath, $content, 0, $format, false);
			}
		}
	}
}