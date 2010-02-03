<?php
/**
 * @author intportg
 * @package framework.persistentdocument.filter
 */
interface f_persistentdocument_DocumentFilter
{
	/**
	 * @return String
	 */
	public static function getDocumentModelName();

	/**
	 * @return String
	 */
	public function getLabel();
	
	/**
	 * @return String
	 */
	public function getText();
	
	/**
	 * @return f_persistentdocument_DocumentFilterParameter[]
	 */
	public function getParameters();
	
	/**
	 * @param String $name
	 * @return f_persistentdocument_DocumentFilterParameter
	 */
	public function getParameter($name);

	/**
	 * @return String
	 */
	public function getAsJson();
	
	/**
	 * @param Boolean $throwException
	 * @return Boolean
	 * @throws ValidationException
	 */
	public function validate($throwException);
}

/**
 * @author intportg
 * @package framework.persistentdocument.filter
 */
abstract class f_persistentdocument_DocumentFilterImpl implements f_persistentdocument_DocumentFilter
{
	/**
	 * @var f_persistentdocument_DocumentFilterParameter[]
	 */
	private $parameters;
	
	/**
	 * @return String
	 */
	public function getLabel()
	{
		list($moduleName, $filterName) = explode('_', get_class($this));
		return f_Locale::translateUI('&modules.'.$moduleName.'.bo.documentfilters.'.strtolower($filterName).'-label;');
	}
	
	/**
	 * @param Boolean $addMarkers
	 * @return String
	 */
	public function getText($addMarkers = false)
	{
		list($moduleName, $filterName) = explode('_', get_class($this));
		$replacements = array();
		foreach ($this->getParameters() as $name => $parameter)
		{
			if ($addMarkers)
			{
				$values = $parameter->getValueForXul();
				$replacements[$name] = '<cFilterParameter filter="' . get_class($this) . '" name="' . $name . '"';
				if (isset($values['property']))
				{
					$replacements[$name] .= ' property="' . $values['property'] . '"';
				}
				if (isset($values['restriction']))
				{
					$replacements[$name] .= ' restriction="' . $values['restriction'] . '"';
				}
				if (isset($values['value']))
				{
					$replacements[$name] .= ' value="' . $values['value'] . '"';
				}
				$replacements[$name] .= '>' . $values['pattern'] . '</cFilterParameter>';
			}
			else
			{
				$replacements[$name] = $parameter->getValueAsText();
			}
		}
		return f_Locale::translateUI('&modules.'.$moduleName.'.bo.documentfilters.'.strtolower($filterName).'-text;', $replacements);
	}
	
	/**
	 * @param f_persistentdocument_DocumentFilterParameter[] $parameters
	 */
	protected function setParameters($parameters)
	{
		$this->parameters = $parameters;
	}
	
	/**
	 * @param f_persistentdocument_DocumentFilterParameter[] $parameters
	 */
	protected function setParameter($name, $parameters)
	{
		$this->parameters[$name] = $parameters;
	}
	
	/**
	 * @return f_persistentdocument_DocumentFilterParameter[]
	 */
	public function getParameters()
	{
		return $this->parameters;
	}
	
	/**
	 * @return f_persistentdocument_DocumentFilterParameter
	 */
	public function getParameter($name)
	{
		return $this->parameters[$name];
	}
	
	/**
	 * @return String
	 */
	public function getAsJson()
	{
		$array = array('class' => get_class($this));
		foreach ($this->getParameters() as $name => $parameter)
		{
			$array['parameters'][$name] = $parameter->getValueForJson();
		}
		return JsonService::getInstance()->encode($array);
	}
		
	/**
	 * @param Boolean $throwException
	 * @return Boolean
	 * @throws ValidationException
	 */
	public function validate($throwException)
	{
		foreach ($this->parameters as $parameter)
		{
			if (!$parameter->validate($throwException))
			{
				return false;
			}
		}
		return true;
	}
	
	// Basic methods that may be used in checkValue method.
	
	/**
	 * @param String $testVal
	 * @param String $restriction
	 * @param String $val
	 * @return Boolean
	 */
	protected function evalRestriction($testVal, $restriction, $val)
	{
		switch ($restriction)
		{
			case 'eq':
				return $testVal == $val;
			case 'ieq' : 
				return f_util_StringUtils::strtolower($testVal) == f_util_StringUtils::strtolower($val);
			case 'ge':
				return $testVal >= $val;
			case 'gt':
				return $testVal > $val;
			case 'le':
				return $testVal <= $val;
			case 'lt':
				return $testVal <= $val;
			case 'ne':
				return $testVal != $val;
			case 'like':
				return f_util_StringUtils::contains($testVal, $val);
			case 'ilike':
				return f_util_StringUtils::contains(f_util_StringUtils::strtolower($testVal), f_util_StringUtils::strtolower($val));
			case 'notLike':
				return !f_util_StringUtils::contains($testVal, $val);
			case 'in':
				foreach ($testVal as $id)
				{
					if (in_array($id, explode(',', $val)))
					{
						return true;
					}
				}
				return false;
			case 'notin':
				foreach ($testVal as $id)
				{
					if (in_array($id, explode(',', $val)))
					{
						return false;
					}
				}
				return true;
		}
		return false;
	}
	
	/**
	 * @param f_mvc_Bean $bean
	 * @param String $propertyName
	 * @return Mixed
	 */
	protected function getTestValueForPropertyName($bean, $propertyName)
	{
		$propInfo = $bean->getBeanModel()->getBeanPropertyInfo($propertyName);
		$getterName = $propInfo->getGetterName();
		if ($propInfo->getType() == BeanPropertyType::DOCUMENT)
		{
			if ($propInfo->getMaxOccurs() != 1)
			{
				return DocumentHelper::getIdArrayFromDocumentArray($bean->$getterName());
			}
			$value = $bean->$getterName();
			return $value === null ? array() : array($value->getId());
		}
		return $bean->$getterName();
	}
}