<?php
class cboot_Configuration
{
	private static $instances = array();
	private static $propertiesLocation = array();
	
	private $name;
	
	private $properties = array();

	function __construct($name = null)
	{
		$this->name = $name;
	}

	static function getInstance($name)
	{
		if (!isset(self::$instances[$name]))
		{
			self::$instances[$name] = new self($name);
		}
		return self::$instances[$name];
	}

	function addLocation($path)
	{
		self::$propertiesLocation[] = $path;
	}
	
	function getLocations()
	{
		return self::$propertiesLocation;
	}

	/**
	 * @return cboot_Properties
	 */
	function getProperties($propFileName = null)
	{
		// echo __METHOD__." ".$propFileName."\n";
		if ($propFileName === null)
		{
			$propFileName = $this->name;
		}
		if (!isset($this->properties[$propFileName]))
		{
			// Load properties: first element has priority over the others
			$props = new cboot_Properties();
			foreach (array_reverse(self::$propertiesLocation) as $propLocation)
			{
				$propPath = $propLocation . "/".$propFileName.".properties";
				//echo $propPath."\n";
				if (is_file($propPath) && is_readable($propPath))
				{
					$props->load($propPath);
				}
			}
			$this->properties[$propFileName] = $props;
		}
		return $this->properties[$propFileName];
	}

	static function getFilePath($relativePath, $strict = true)
	{
		foreach (array_reverse(self::$propertiesLocation) as $propLocation)
		{
			$path = $propLocation."/".$relativePath;
			if (is_readable($path))
			{
				return $path;
			}
		}

		if ($strict)
		{
			throw new Exception("Could not find any readable '$relativePath' configuration file");
		}
		return null;
	}

	function getProperty($propFileName = null, $propertyName, $strict = true)
	{
		$props = $this->getProperties($propFileName);
		$propValue = $props->getProperty($propertyName);
		if ($propValue === null && $strict)
		{
			throw new Exception("Could not find $propertyName value in any configuration file");
		}
		return $propValue;
	}
}

class cboot_Properties
{
	/**
	 * @var array<String,String>
	 */
	private $properties;

	/**
	 * @var Boolean
	 */
	private $preserveComments = false;

	/**
	 * @var Boolean
	 */
	private $preserveEmptyLines = false;

	function __construct($path = null)
	{
		if ($path !== null)
		{
			$this->load($path);
		}
	}

	/**
	 * @param string $path
	 */
	function load($path)
	{
		if (!is_file($path) || !is_readable($path))
		{
			throw new Exception("Can not read file $path");
		}
		$this->parse($path);
	}
	
	/**
	 * @param string $path
	 */
	function save($path)
	{
		$dir = dirname($path);
		if ((!file_exists($path) && !is_writable($dir)) || (file_exists($path) && !is_writable($path)))
		{
			throw new Exception("Can not write to $path");
		}
		if (file_put_contents($path, $this->__toString()) === false)
		{
			throw new Exception("Could not write to $path");
		}
	}

	/**
	 * (by defaults, comments are not preserved)
	 * @param boolean $preserveComments
	 */
	function setPreserveComments($preserveComments)
	{
		$this->preserveComments = $preserveComments;
	}

	/**
	 * (by defaults, comments are not preserved)
	 * @param boolean $preserveComments
	 */
	function setPreserveEmptyLines($preserveEmptyLines)
	{
		$this->preserveEmptyLines = $preserveEmptyLines;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		if ($this->properties !== null)
		{
			$buf = "";
			foreach($this->properties as $key => $item)
			{
				if ($this->preserveComments && is_int($key))
				{
					$buf .= $item."\n";
				}
				else
				{
					$buf .= $key . "=" . $this->writeValue($item)."\n";
				}
			}
			return $buf;
		}
		return "";
	}

	/**
	 * Returns copy of internal properties hash.
	 * Mostly for performance reasons, property hashes are often
	 * preferable to passing around objects.
	 *
	 * @return array
	 */
	function getProperties()
	{
		return $this->properties;
	}

	/**
	 * Get value for specified property.
	 * This is the same as get() method.
	 *
	 * @param string $prop The property name (key).
	 * @return mixed
	 * @see get()
	 */
	function getProperty($prop, $defaultValue = null)
	{
		if (!isset($this->properties[$prop]))
		{
			return $defaultValue;
		}
		return $this->properties[$prop];
	}

	/**
	 * Set the value for a property.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return mixed Old property value or NULL if none was set.
	 */
	function setProperty($key, $value)
	{
		$oldValue = @$this->properties[$key];
		$this->properties[$key] = $value;
		return $oldValue;
	}
	
	/**
	 * @param array<String, String> $properties
	 */
	function setProperties($properties)
	{
		$this->properties = $properties;
	}

	/**
	 * Same as keys() function, returns an array of property names.
	 * @return array
	 */
	function propertyNames()
	{
		return $this->keys();
	}

	/**
	 * Whether loaded properties array contains specified property name.
	 * @return boolean
	 */
	function hasProperty($key)
	{
		return isset($this->properties[$key]);
	}

	/**
	 * Whether properties list is empty.
	 * @return boolean
	 */
	function isEmpty()
	{
		return empty($this->properties);
	}

	// protected methods

	/**
	 * @param unknown_type $val
	 * @return unknown
	 */
	protected function readValue($val)
	{
		if ($val === "true")
		{
			$val = true;
		}
		elseif ($val === "false")
		{
			$val = false;
		}
		else
		{
			$valLength = strlen($val);
			if ($valLength > 0 && (($val[0] == "'" && $val[$valLength-1] == "'") || ($val[0] == "\"" && $val[$valLength-1] == "\"")))
			{
				$val = substr($val, 1, -1);
			}
		}
		return $val;
	}

	/**
	 * Process values when being written out to properties file.
	 * does things like convert true => "true"
	 * @param mixed $val The property value (may be boolean, etc.)
	 * @return string
	 */
	protected function writeValue($val)
	{
		if ($val === true)
		{
			$val = "true";
		}
		elseif ($val === false)
		{
			$val = "false";
		}
		return $val;
	}

	// private methods

	/**
	 * @param string $filePath
	 */
	private function parse($filePath)
	{
		$lines = @file($filePath);
		if ($lines === false)
		{
			throw new Exception("Could not read $filePath");
		}
		if ($this->properties === null)
		{
			$this->properties = array();
		}
		foreach($lines as $line)
		{
			$line = trim($line);
			if($line == "")
			{
				if ($this->preserveEmptyLines)
				{
					$this->properties[] = " ";
				}
				continue;
			}

			if ($line[0] == '#' || $line[0] == ';')
			{
				// it's a comment, so continue to next line
				if ($this->preserveComments)
				{
					$this->properties[] = $line;
				}
				continue;
			}
			else
			{
				$pos = strpos($line, '=');
				if ($pos === false)
				{
					throw new Exception("Invalid property file line $line");
				}
				$property = trim(substr($line, 0, $pos));
				$value = trim(substr($line, $pos + 1));
				$this->properties[$property] = $this->readValue($value);
			}
		}
	}
}