<?php
interface indexer_Cache
{
	/**
	 * @param $queryId
	 * @return indexer_CachedQuery
	 */
	function get($queryId);
	
	/**
	 * @param indexer_CachedQuery $cachedQuery
	 */
	function store($cachedQuery);
}

class indexer_FSCache implements indexer_Cache
{
	private $dir;
	
	function __construct($dir = null)
	{
		if ($dir === null)
		{
			$dir = Framework::getConfigurationValue("indexer/FSCache/directory", "solr_requests");
		}
		$this->dir = $dir;
	}
	
	private function getPath($queryId)
	{
		$queryRelPath = $queryId[0]."/".$queryId[1]."/".$queryId[2]."/".$queryId;
		return f_util_FileUtils::buildCachePath($this->dir, $queryRelPath);
	}
	
	/**
	 * @param $queryId
	 * @return indexer_CachedQuery
	 */
	function get($queryId)
	{
		$path = $this->getPath($queryId);
		if (file_exists($path))
		{
			return unserialize(file_get_contents($path));
		}
		return null;
	}
	
	/**
	 * @param indexer_CachedQuery $cachedQuery
	 */
	function store($cachedQuery)
	{
		f_util_FileUtils::writeAndCreateContainer($this->getPath($cachedQuery->getId()), serialize($cachedQuery), f_util_FileUtils::OVERRIDE);
	}
}

class indexer_CachedQuery
{
	private $time;
	private $data;
	private $url;
	private $id;
	
	function __construct($url, $data = null)
	{
		$this->time = time();
		$this->data = $data;
		$this->url = $url;
		$this->id = md5($url);
	}
	
	function setData($data)
	{
		$this->data = $data;
	}
	
	function getTime()
	{
		return $this->time;
	}
	
	function getData()
	{
		return $this->data;
	}
	
	function getId()
	{
		return $this->id; 
	}
}