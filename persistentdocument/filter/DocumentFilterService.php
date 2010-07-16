<?php
/**
 * @author intportg
 * @package framework.persistentdocument.filter
 */
class f_persistentdocument_DocumentFilterService extends BaseService
{
	/**
	 * @var f_persistentdocument_DocumentFilterService
	 */
	private static $instance;

	/**
	 * @return f_persistentdocument_DocumentFilterService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * @param String $json
	 * @return f_persistentdocument_DocumentFilter
	 */
	public function getFilterInstanceFromJson($json)
	{
		$filterInfo = JsonService::getInstance()->decode($json);
		return $this->getFilterInstanceFromInfo($filterInfo);
	}

	/**
	 * @param String $json
	 * @return f_persistentdocument_DocumentFilter[]
	 */
	public function getFilterArrayFromJson($json)
	{
		if (is_string($json))
		{
			if (f_util_StringUtils::isEmpty($json)) {return array();}
			$filtersInfo = JsonService::getInstance()->decode($json);
		}
		else
		{
			$filtersInfo = $json;
		}
		$filters = array();
		foreach ($filtersInfo as $filterInfo)
		{
			if ($filterInfo['class'] == 'filterSection')
			{
				$section = array();
				foreach ($filterInfo["filters"] as $sectionItemInfo)
				{
					$section[] = $this->getFilterInstanceFromInfo($sectionItemInfo);
				}
				$filters[] = $section;
			}
			else
			{
				$filters[] = $this->getFilterInstanceFromInfo($filterInfo);
			}
		}
		return $filters;
	}
	
	/**
	 * @param Array $filterInfo
	 * @return f_persistentdocument_DocumentFilter
	 */
	private function getFilterInstanceFromInfo($filterInfo)
	{
		$filter = f_util_ClassUtils::newInstance($filterInfo['class']);
		foreach ($filterInfo['parameters'] as $name => $parameterInfo)
		{
			$parameter = $filter->getParameter($name);
			
			if (isset($parameterInfo[0]))
			{
				$parameter->setPropertyName($parameterInfo[0]);
			}
			if (isset($parameterInfo[1]))
			{
				$parameter->setRestriction($parameterInfo[1]);
			}
			
			$valueParameter = $parameter;
			if ($parameter instanceof f_persistentdocument_DocumentFilterRestrictionParameter)
			{
				$valueParameter = $parameter->getParameter();
			}
			if (isset($parameterInfo[2]) && $valueParameter !== null)
			{
				$valueParameter->setValue($parameterInfo[2]);
			}
		}
		return $filter;
	}
	
	/**
	 * @param String $json
	 * @return f_persistentdocument_criteria_QueryIntersection
	 */
	public function getQueryIntersectionFromJson($json)
	{
		$info = JsonService::getInstance()->decode($json);
		// !isset() for filter <= 3.0.2 compatibility
		if (!isset($info["operator"]) || $info["operator"] == "and")
		{
			// filter <= 3.0.2 compatibility
			$elementsInfo = (isset($info["elements"])) ? $info["elements"] : $info; 
			$group = new f_persistentdocument_criteria_QueryIntersection();
			foreach ($this->getFilterArrayFromJson($elementsInfo) as $filter)
			{
				if (is_array($filter))
				{
					$subGroup  = new f_persistentdocument_criteria_QueryUnion();
					foreach ($filter as $f)
					{
						$subGroup->add($f->getQuery());
					}
					$group->add($subGroup);
				}
				else
				{
					$group->add($filter->getQuery());
				}
			}
			return $group;
		}
		elseif ($info["operator"] == "or")
		{
			// No need for filter <= 3.0.2 as or operator didn't exist
			$intersection = new f_persistentdocument_criteria_QueryIntersection();
			$filters = $this->getFilterArrayFromJson($info["elements"]);
			if (f_util_ArrayUtils::isEmpty($filters))
			{
				return $intersection;
			}
			$group = new f_persistentdocument_criteria_QueryUnion();
			foreach ($filters as $filter)
			{
				if (is_array($filter))
				{
					$subGroup  = new f_persistentdocument_criteria_QueryIntersection();
					foreach ($filter as $f)
					{
						$subGroup->add($f->getQuery());
					}
					$group->add($subGroup);
				}
				else
				{
					$group->add($filter->getQuery());
				}
			}
			return $intersection->add($group);
		}
		else
		{
			throw new Exception("Unknown operator ".$info["operator"]);
		}
	}
	
	/**
	 * @param string $json
	 * @param mixed $value
	 * @return boolean
	 */
	public function checkValueFromJson($json, $value)
	{
		$info = JsonService::getInstance()->decode($json);
		// !isset() for filter <= 3.0.2 compatibility
		if (!isset($info["operator"]) || $info["operator"] == "and")
		{
			// filter <= 3.0.2 compatibility
			$elementsInfo = (isset($info["elements"])) ? $info["elements"] : $info; 
			
			$filters = $this->getFilterArrayFromJson($elementsInfo);
			if (f_util_ArrayUtils::isEmpty($filters))
			{
				return true;
			}
			foreach ($filters as $filter) 
			{
				if (is_array($filter))
				{
					$subFilterValue = false;
					foreach ($filter as $subFilter)
					{
						if ($subFilter->checkValue($value))
						{
							$subFilterValue = true;
							break;
						}
					}
					if (!$subFilterValue)
					{
						return false;
					}
				}
				else
				{
					if (!$filter->checkValue($value)) return false;
				}
			}
			return true;
		}
		elseif ($info["operator"] == "or")
		{
			$filters = $this->getFilterArrayFromJson($info["elements"]);
			if (f_util_ArrayUtils::isEmpty($filters))
			{
				return true;
			}
			foreach ($filters as $filter) 
			{
				if (is_array($filter))
				{
					$subFilterValue = true;
					foreach ($filter as $subFilter)
					{
						if (!$subFilter->checkValue($value))
						{
							$subFilterValue = false;
							break;
						}
					}
					if ($subFilterValue)
					{
						return true;
					}
				}
				else
				{
					if ($filter->checkValue($value)) return true;
				}
			}
			return false;
		}
		else
		{
			throw new Exception("Unknown operator ".$info["operator"]);
		}
	}
	
	private $filtersByModel = null;
	
	/**
	 * @param String $modelName
	 * @param String[] $methods
	 * @return String[]
	 */
	public function getFiltersByModelName($modelName, $methods = array())
	{
		$keys = array();
		while ($modelName)
		{
			$keys[] = $modelName;
			$model = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName($modelName);
			$modelName = $model->getParentName();		
		}
		return $this->getFiltersByKeys($keys, $methods);
	}
	
	/**
	 * @param String[] $keys
	 * @param String[] $methods
	 * @return String[]
	 */
	public function getFiltersByKeys($keys, $methods = array())
	{
		$filters = array();
		if ($this->filtersByModel === null)
		{
			$fileName = f_util_FileUtils::buildChangeBuildPath('documentFilters.php');
			if (file_exists($fileName))
			{
				$this->filtersByModel = unserialize(f_util_FileUtils::read($fileName));
			}
			else
			{
				$this->filtersByModel = array();
			}
		}
		foreach ($keys as $modelName) 
		{
			if (isset($this->filtersByModel[$modelName]))
			{
				$filtersTemp = $this->filtersByModel[$modelName]['all'];
				foreach ($methods as $method)
				{
					if (isset($this->filtersByModel[$modelName][$method]))
					{
						$filtersTemp = array_intersect($filtersTemp, $this->filtersByModel[$modelName][$method]);
					}
				}
				$filters = array_merge($filters, $filtersTemp);
			}
		}
		return $filters;
	}
	
	/**
	 * @return void
	 */
	public function compileFilters()
	{
		$filters = array();
		
		// Get filters in modules.
		$modules = ModuleService::getInstance()->getModules();
		foreach ($modules as $module)
		{
			$dir = $this->getFiltersDirectoryByPackage($module);
			if (!is_null($dir))
			{
				foreach ($this->getFiltersInDirectory($dir, $module) as $filterClass)
				{
					$modelName = f_util_ClassUtils::callMethod($filterClass, 'getDocumentModelName');
					if (!isset($filters[$modelName]))
					{
						$filters[$modelName] = array();
						$filters[$modelName]['all'] = array();
					}
					$filters[$modelName]['all'][] = $filterClass;
					foreach ($this->getMethods() as $method)
					{
						if (f_util_ClassUtils::methodExists($filterClass, $method))
						{
							if (!isset($filters[$modelName][$method]))
							{
								$filters[$modelName][$method] = array();
							}
							$filters[$modelName][$method][] = $filterClass;
						}
					}
				}
			}
		}
		
		$this->filtersByModel = $filters;
		
		// Write filter list in file.
		f_util_FileUtils::writeAndCreateContainer(f_util_FileUtils::buildChangeBuildPath('documentFilters.php'), serialize($filters), f_util_FileUtils::OVERRIDE);
	}
	
	/**
	 * @param String $restriction
	 * @return String
	 */
	public function getRestrictionAsText($restriction)
	{
		return f_Locale::translateUI('&modules.filter.bo.restrictions.'.$restriction.'-text;');
	}
	
	/**
	 * @param String $name like that: "modules_<moduleName>/<documentName>.<propertyName>"
	 * @return BeanPropertyInfo
	 */
	public function getPropertyInfoByName($name)
	{
		list($modelName, $propertyName) = explode('.', $name);
		$model = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName($modelName);
		return $model->getBeanPropertyInfo($propertyName);
	}
	
	/**
	 * @param String $modeAsString 'start', 'end', 'anywhere' or 'exact'.
	 * @return MatchMode
	 */
	public function getMatchMode($modeAsString)
	{
		switch ($modeAsString)
		{
			case 'start' : 
				$mode = MatchMode::START();
				break;
				
			case 'end' : 
				$mode = MatchMode::END();
				break;
				
			case 'anywhere' : 
				$mode = MatchMode::ANYWHERE();
				break;
				
			case 'exact' : 
			default :
				$mode = MatchMode::EXACT();
				break;
		}
		return $mode;
	}
	
	// Private methods.
	
	/**
	 * @return string[]
	 */
	private function getMethods()
	{
		return array('getQuery', 'checkValue');
	}
	
	/**
	 * @param String $package
	 * @return String
	 */
	private function getFiltersDirectoryByPackage($package)
	{
		return FileResolver::getInstance()->setPackageName($package)->setDirectory('persistentdocument')->getPath('filters');
	}
	
	/**
	 * @param String $dir
	 * @return Array<String, String>
	 */
	private function getFiltersInDirectory($dir, $package)
	{
		list (,$moduleName) = explode('_', $package);
		$filters = array();
		foreach (f_util_FileUtils::getDirFiles($dir) as $filePath)
		{
			$fileName = basename($filePath);
			if (preg_match('#^[a-z0-9]+\.php$#i', $fileName) === 1)
			{
				list ($className,) = explode('.', $fileName);
				$filters[] = $moduleName . '_' . $className;
			}
		}
		return $filters;
	}
}