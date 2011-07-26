<?php


class import_ScriptBaseElement
{
	/**
	 * @var import_ScriptReader
	 */
	protected $script;

	/**
	 * @var import_ScriptBaseElement
	 */
	private $parentElement;

	/**
	 * @var string
	 */
	private $content;

	/**
	 * @var array
	 */
	protected  $attributes = array();

	public function __construct ($script, $parentElement, $name)
	{
		$this->script = $script;
		$this->parentElement = $parentElement;
		$this->name = $name;
	}

	/**
	 * @return import_ScriptBaseElement
	 */
	public function getParent ()
	{
		return $this->parentElement;
	}

	/**
	 * @param String $name
	 * @return import_ScriptBaseElement
	 */
	protected function getAncestor($name)
	{
		if ($this->name == $name)
		{
			return $this;
		}
		else if ($this->parentElement)
		{
			return $this->parentElement->getAncestor($name);
		}
		return null;
	}

	/**
	 * @param String $className
	 * @return import_ScriptBaseElement
	 */
	protected final function getAncestorByClassName($className)
	{
		$parent = $this->getParent();
		while ($parent)
		{
			if (is_a($parent, $className))
			{
				return $parent;
			}
			$parent = $parent->getParent();
		}
		return null;
	}

	public function __toString ()
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 * @param string $value
	 */
	public function setAttribute ($name, $value)
	{
		$this->attributes[$name] = $value;
	}

	public function getAncestorAttribute($name)
	{
		if (isset($this->attributes[$name]))
		{
			return $this->attributes[$name];
		}
		else if ($this->getParent() !== null)
		{
			return $this->getParent()->getAncestorAttribute($name);
		}
		return $this->script->getAttribute($name);
	}

	/**
	 * @return array<String, mixed>
	 */
	protected final function getComputedAttributes()
	{
		return $this->computeAttributes($this->attributes);
	}

	protected final function getComputedAttribute($name, $remove = true)
	{
		// WARN: sync with computeAttributes if someday we add an other computation than "-refid" and "-refids"
		if (isset($this->attributes[$name]))
		{
			return $this->attributes[$name];
		}
		$key = $name.'-refid';
		if (isset($this->attributes[$key]))
		{
			$object = $this->script->getElementById($this->attributes[$key], "import_ScriptObjectElement")->getObject();
			if ($remove)
			{
				unset($this->attributes[$key]);
			}
			return $object;
		}
		$key = $name.'-refids';
		if (isset($this->attributes[$key]))
		{
			$objects = array();
			foreach (explode(',', $this->attributes[$key]) as $value)
			{ 
				$objects[] = $this->script->getElementById($value, "import_ScriptObjectElement")->getObject();
			}
			if ($remove)
			{
				unset($this->attributes[$key]);
			}
			return $objects;
		}
		$key = $name.'-attr';
		if (isset($this->attributes[$key]))
		{
			$attrname = ($this->attributes[$key]) ? $this->attributes[$key] : $key;
			if ($remove)
			{
				unset($this->attributes[$key]);
			}
			return $this->getAncestorAttribute($attrname);
		}		
		return null;
	}

	protected final function computeAttributes($attributes)
	{
		// WARN: sync with getComputedAttribute if someday we add an other computation than "-refid" and "-refids"
		$computedAttributes = array();
		foreach ($attributes as $key => $value)
		{
			$pos = strrpos($key, '-');
			if ($pos > 0)
			{
				$subkey = substr($key, 0, $pos);
				$ext = substr($key, $pos + 1);
				switch ($ext)
				{
					case 'refid':
						$key = $subkey;
						$value = $this->script->getElementById($value, "import_ScriptObjectElement")->getObject();
						break;
						
					case 'refids':
						$key = $subkey;
						$values = explode(',', $value);
						$value = array();
						foreach ($values as $oneValue)
						{
							$value[] = $this->script->getElementById($oneValue, "import_ScriptObjectElement")->getObject();
						}
						break;
						
					case 'attr':
						$key = $subkey;
						$attrname = $value ? $value : $key;
						$value = $this->getAncestorAttribute($attrname);
				}
			}
			$computedAttributes[$key] = $value;
		}
		return $computedAttributes;
	}

	/**
	 * @param string $value
	 */
	public function addContent ($value)
	{
		if ($this->content)
		{
			$this->content .= $value;
		}
		else
		{
			$this->content = $value;
		}
	}

	/**
	 * @return string
	 */
	protected function getContent()
	{
		return $this->content;
	}

	public function process()
	{
	}

	public function endProcess()
	{
	}

	protected function parseBoolean($value)
	{
		return f_util_Convert::toBoolean($value);
	}
}