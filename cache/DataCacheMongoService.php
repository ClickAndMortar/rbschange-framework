<?php
class f_DataCacheMongoService extends f_DataCacheService
{
	private static $instance;
	private static $mongoInstance = null;
	private static $mongoCollection = null;
	private static $mongoRegistration = null;
	
	protected function __construct()
	{
		$connectionString = null;
		$config = Framework::getConfiguration("mongoDB");
		
		if (isset($config["authentication"]["username"]) && isset($config["authentication"]["password"]) && 
			$config["authentication"]["username"] !== '' && $config["authentication"]["password"] !== '')
		{
			$connectionString .= $config["authentication"]["username"].':'.$config["authentication"]["password"].'@';
		}
		
		$connectionString .= implode(",", $config["serversSimpleCache"]);
		
		if ($connectionString != null)
		{
			$connectionString = "mongodb://".$connectionString;
		}
		
		try
		{
			self::$mongoInstance = new Mongo($connectionString);
			self::$mongoCollection = self::$mongoInstance->$config["database"]["name"]->dataCache;
			self::$mongoRegistration = self::$mongoInstance->$config["database"]["name"]->dataCacheRegistration;
		}
		catch (MongoConnnectionException $e)
		{
			Framework::exception($e);
		}
	}

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
	 * @param String $subCache (optional)
	 * @param Array	$newPatterns
	 * @return f_DataCacheItem or null or String
	 */
	public function readFromCache($namespace, $keyParameters, $newPatterns = null)
	{
		if ($newPatterns !== null)
		{
			$returnItem = true;
		}
		else 
		{
			$returnItem = false;
			$newPatterns = array();
		}
		
		$item = $this->getData($this->getNewCacheItem($namespace, $keyParameters, $newPatterns));
		
		if ($this->exists($item) || $returnItem)
		{
			return $item;
		}
		return null;
	}
	
	/**
	 * @param f_DataCacheItem $item
	 */
	public function writeToCache($item)
	{
		$this->register($item);
		$data = $item->getValues();
		
		$data["timestamp"] = time();
		$data["isValid"] = true;
		$data["ttl"] = $item->getTTL();
		unset($data["_id"]);
		
		try
		{
			self::$mongoCollection->update(array("_id" => $item->getNamespace().'-'.$item->getKeyParameters()), 
				array('$set' => $data),	array("upsert" => true, "safe" => true));
		}
		catch (MongoCursorException $e)
		{
			Framework::exception($e);
		}
	}
	
	/**
	 * @param f_DataCacheItem $item
	 * @param String $subCache
	 * @return Boolean
	 */
	public function exists($item, $subCache = null)
	{
		$result = $item->isValid();
		if ($subCache !== null)
		{
			$subResult = $item->getValue($subCache) !== null;
		}
		else 
		{
			$subResult = true;
		}
		return $result && $subResult;
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
	
	public function cleanExpiredCache()
	{
		try
		{
			self::$mongoCollection->remove(array("isValid" => false), array("safe" => true));
			self::$mongoCollection->remove(array("timestamp" => array('$lt' => time() - self::MAX_TIME_LIMIT)), array("safe" => true));
		}
		catch (MongoCursorException $e)
		{
			Framework::exception($e);
			return false;
		}
		return true;
	}
	
	/**
	 * @param f_DataCacheItem $item
	 * @param String $subCache
	 * @param Boolean $dispatch (optional)
	 */
	public final function clearSubCache($item, $subCache, $dispatch = true)
	{
		$this->registerShutdown();
		
		try
		{
			self::$mongoCollection->remove(array("_id" => $item->getNamespace().'-'.$item->getKeyParameters()), array("safe" => true));
		}
		catch (MongoCursorException $e)
		{
			Framework::exception($e);
		}
		
		if (Framework::isDebugEnabled())
		{
			Framework::debug(__METHOD__ . ' ' . $item->getNamespace().'-'.$item->getKeyParameters().' : '.$subCache);
		}
		
		if (!array_key_exists($item->getNamespace(), $this->idToClear))
		{
			$this->idToClear[$item->getNamespace()] = array($item->getKeyParameters() => $subCache);
		}
		else if (is_array($this->idToClear[$item->getNamespace()]))
		{
			$this->idToClear[$item->getNamespace()][$item->getKeyParameters()] = $subCache;
		}

		$this->dispatch = $dispatch || $this->dispatch;
	}
	
	private function commitClear()
	{
		if (Framework::isDebugEnabled())
		{
			Framework::debug("SimpleCache->commitClear");
		}
		if ($this->clearAll)
		{
			if (Framework::isDebugEnabled())
			{
				Framework::debug("Clear all");
			}
			self::$mongoCollection->update(array(), array('$set' => array("isValid" => false)), array("multiple" => true, "safe" => true));	
			if ($this->dispatch)
			{
				f_event_EventManager::dispatchEvent('simpleCacheCleared', null);
			}
		}
		else
		{
			if (!empty($this->idToClear))
			{
				$ids = array();
				foreach ($this->idToClear as $id => $value)
				{
					$ids[] = $id;
				}
				self::buildInvalidCacheList($ids);
			}
			if (!empty($this->docIdToClear))
			{
				self::commitClearByDocIds($this->docIdToClear);
			}
			
			if ($this->dispatch)
			{
				if ($this->idToClear === null)
				{
					$this->idToClear = array();
				}
				if ($this->docIdToClear === null)
				{
					$this->docIdToClear = array();
				}
				f_event_EventManager::dispatchEvent('simpleCacheCleared', null, array_merge($this->idToClear, $this->docIdToClear));
			}
		}
		
		$this->clearAll = false;
		$this->idToClear = null;
		$this->docIdToClear = null;
	}

	/**
	 * @param Array $docIds
	 */
	private function commitClearByDocIds($docIds)
	{
		try
		{
			$keyParameters = self::$mongoRegistration->find(array("_id" => array('$in' => $docIds)),
				array("keyParameters" => true));
			self::$mongoCollection->update(array("_id" => array('$in' => $keyParameters)), 
				array('$set' => array("isValid" => false)), array("multiple" => true, "safe" => true));
		}
		catch (MongoCursorException $e)
		{
			Framework::exception($e);
		}
	}

	/**
	 * @param Array $dirsToClear
	 */
	private function buildInvalidCacheList($dirsToClear)
	{
		try
		{
			$regex =  implode("|", $dirsToClear);
			self::$mongoCollection->update(array("_id" => new MongoRegex("/^($regex)-/") ), 
				array('$set' => array("isValid" => false)), 
				array("multiple" => true, "safe" => true));				
		}
		catch (MongoCursorException $e)
		{
			Framework::exception($e);
		}
	}
	
	/**
	 * @param f_DataCacheItem $item
	 */
	private function register($item)
	{
		if (!$this->isRegistered($item))
		{	
			try
			{
				self::$mongoRegistration->update(array("_id" => $item->getNamespace()), 
					array('$set' => array("pattern" => $this->optimizeCacheSpecs($item->getPatterns()))), 
					array("upsert" => true, "safe" => true));
			}
			catch (MongoCursorException $e)
			{
				Framework::exception($e);
			}
		}
		
		foreach ($item->getPatterns() as $spec)
		{
			if (is_numeric($spec))
			{
				try
				{
					self::$mongoRegistration->update(array("_id" => $spec), 
						array('$addToSet' => array("keyParameters" => $item->getNamespace().'-'.$item->getKeyParameters())), 
						array("upsert" => true, "safe" => true));
				}
				catch (MongoCursorException $e)
				{
					Framework::exception($e);
				}
			}
		}
	}
	
	private function isRegistered($item, $id = null, $keyParameters = null)
	{
		if ($id === null && $keyParameters === null)
		{
			try
			{
				$object = self::$mongoRegistration->findOne(array("_id" => $item->getNamespace()));
			}
			catch (MongoConnnectionException $e)
			{
				Framework::exception($e);
			}
		
			return ($object !== null);
		}
		
		try
		{
			$object = self::$mongoRegistration->findOne(array("keyParameters" => $id.'-'.$keyParameters));
		}
		catch (MongoConnnectionException $e)
		{
			Framework::exception($e);
		}
		
		return ($object !== null);
	}
	
	/**
	 * @param f_DataCacheItem $item
	 * @return f_DataCacheItem
	 */
	private function getData($item)
	{
		try
		{
			$object = self::$mongoCollection->findOne(array("_id" => $item->getNamespace().'-'.$item->getKeyParameters()));
		}
		catch (MongoConnnectionException $e)
		{
			Framework::exception($e);
		}
		
		if ($object !== null)
		{
			foreach ($object as $k => $v)
			{
				if ($k == "isValid")
				{
					$item->setValidity($v);
					continue;
				}
				if ($k == "timestamp")
				{
					$item->setCreationTime($v);
					continue;
				}
				if ($k == "ttl")
				{
					$item->setTTL($v);
					continue;
				}
				$item->setValue($k, $v);
			}
		}
		return $item;
	}
}
?>