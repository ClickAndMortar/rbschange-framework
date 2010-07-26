<?php
class f_DataCacheService extends BaseService
{
	const MAX_TIME_LIMIT = 86400;
	
	/**
	 * @var f_DataCacheService
	 */
	private static $instance;
	
	protected $clearAll = false;
	protected $idToClear = array();
	protected $docIdToClear = array();
	protected $dispatch = false;
	protected $shutdownRegistered = false;

	/**
	 * @return f_DataCacheService
	 */
	public static function getInstance()
	{
		if (self::$instance === null)
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	/**
	 * @param String $namespace
	 * @param Mixed $keyParameters
	 * @param Array $patterns
	 * @return f_DataCacheItem
	 */
	public function getNewCacheItem($namespace, $keyParameters, $patterns)
	{
		return new f_DataCacheItemImpl($namespace, $keyParameters, $patterns);
	}
	
	/**
	 * @return Boolean
	 */
	function isEnabled()
	{
		return !defined("DISABLE_DATACACHE") || constant("DISABLE_DATACACHE") !== true;
	}
	
	/**
	 * @param String $namespace
	 * @param Mixed $keyParameters
	 * @param String $subCache (optional)
	 * @param Array	$newPatterns
	 * @return f_DataCacheItem or null
	 */
	public function readFromCache($namespace, $keyParameters, $newPatterns = null)
	{
		if ($newPatterns !== null)
		{
			$cacheItem = $this->getNewCacheItem($namespace, $keyParameters, $newPatterns);
			$cacheItem->setInvalid();
			return $cacheItem;
		}
		return null;
	}
	
	/**
	 * @param f_DataCacheItem $item
	 */
	public function writeToCache($item)
	{
		
	}
	
	/**
	 * @param f_DataCacheItem $item
	 * @param String $subCache
	 * @return Boolean
	 */
	public function exists($item, $subCache = null)
	{
		return false;
	}
	
	/**
	 * @param String $pattern
	 */
	public function clearCacheByPattern($pattern)
	{
		$cacheIds = $this->getPersistentProvider()->getCacheIdsByPattern($pattern);
		foreach ($cacheIds as $cacheId)
		{
			if (Framework::isDebugEnabled())
			{
				Framework::debug("[". __CLASS__ . "]: clear $cacheId cache");
			}
			$this->clear($cacheId);
		}
	}
	
	/**
	 * @param String $namespace
	 */
	public function clearCacheByNamespace($namespace)
	{
		$this->clear($namespace);
	}
	
	/**
	 * @param String $id
	 */
	public function clearCacheByDocId($id)
	{
		$this->registerShutdown();
		$this->docIdToClear[] = $id;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocumentModel $model
	 */
	public function clearCacheByModel($model)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug("[". __CLASS__ . "]: clear cache by model:".$model->getName());
		}
		$this->clearCacheByPattern($model->getName());
		if ($model->isInjectedModel())
		{
			$this->clearCacheByPattern($model->getOriginalModelName());
		}
	}

	/**
	 * @param String $tag
	 */
	public function clearCacheByTag($tag)
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug("[". __CLASS__ . "]: clear cache by tag:$tag");
		}
		$this->clearCacheByPattern(f_DataCachePatternHelper::getTagPattern($tag));
	}

	public function clearAll()
	{
		$this->clear();
	}
	
	public function cleanExpiredCache()
	{
		
	}
	
	/**
	 * This is the same as BlockCache::commitClear()
	 * but designed for the context of <code>register_shutdown_function()</code>,
	 * to be sure the correct umask is used.
	 */
	public function shutdownCommitClear()
	{
		$this->commitClear();
	}
	
	/**
	 * @param Array $cacheSpecs
	 * @return Array
	 */
	protected function optimizeCacheSpecs($cacheSpecs)
	{
		if (f_util_ArrayUtils::isNotEmpty($cacheSpecs))
		{
			$finalCacheSpecs = array();
			foreach (array_unique($cacheSpecs) as $spec)
			{
				if (preg_match('/^modules_\w+\/\w+$/', $spec))
				{
					try
					{
						$model = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName($spec);
						$finalCacheSpecs[] = $model->getName();
					}
					catch (Exception $e)
					{
						Framework::exception($e);
					}
				}
				else if (strpos($spec, 'tags/') === 0 && strpos($spec, '*') !== false)
				{
					try
					{
						$tags = TagService::getInstance()->getAvailableTagsByPattern(substr($spec,5));
						foreach ($tags as $tag)
						{
							$finalCacheSpecs[] = 'tags/' . $tag;
						}
					}
					catch (Exception $e)
					{
						Framework::exception($e);
					}
				}
				elseif (!is_numeric($spec))
				{
					$finalCacheSpecs[] = $spec;
				}
			}

			return $finalCacheSpecs;
		}
		return array();
	}
	
	protected function registerShutdown()
	{
		if (!$this->shutdownRegistered)
		{
			register_shutdown_function(array($this,'shutdownCommitClear'));
			$this->shutdownRegistered = true;
		}
	}
	
	/**
	 * @param String $id
	 * @param Boolean $dispatch (optional)
	 */
	protected function clear($id = null, $dispatch = true)
	{
		$this->registerShutdown();
		if ($id === null)
		{
			$this->clearAll = true;
		}
		else
		{
			$this->idToClear[$id] = true;
		}
		$this->dispatch = $dispatch || $this->dispatch;
	}
	
	/**
	 * @param Array $ids
	 */
	protected function commitClearDispatched($ids = null)
	{
		$this->registerShutdown();
		if (Framework::isDebugEnabled())
		{
			Framework::debug("SimpleCache->commitClearDispatched");
		}
		if ($ids === null)
		{
			$this->clearAll = true;
		}
		else
		{
			$this->idToClear = $ids;
		}
		$this->dispatch = false;
	}
	
	/*private function commitClear()
	{
		
	}*/

	/**
	 * @param Array $docIds
	 */
	/*private function commitClearByDocIds($docIds)
	{
		
	}*/
	
	/**
	 * @param Array $dirsToClear
	 */
	/*private function buildInvalidCacheList($dirsToClear)
	{
		
	}*/
}
?>