<?php
class change_Storage
{
	
	/**
	 * @var change_Context
	 */
	private $context = null;
	
	/**
	 * @var array
	 */
	protected $parameters = array('session_name' => '__CHANGESESSIONID', 'auto_start' => true);
	
	private $started = null;
	
	/**
	 * @param change_Context $context
	 * @param array $parameters
	 */
	public function initialize ($context, $parameters = null)
	{
		$this->context = $context;
		if ($parameters != null)
		{
			$this->parameters = array_merge($this->parameters, $parameters);
		}	
			
		if (!$this->getParameter('auto_start'))
		{
			$this->startSession();
		}
	}
	
	protected function startSession()
	{
		if (isset($_SERVER["SERVER_ADDR"]))
		{
			$sessionName = $this->getParameter('session_name');
			session_name($sessionName);
			if (session_id() == "")
			{
				session_start();
			}
			$this->started = true;
		
			$currentKey =  $this->getSecureKey(); 
			$md5 = $this->read('SecureKey');
			if ($md5 === null)
			{
				$this->write('SecureKey', $currentKey);
				$this->write('SecurePort', $_SERVER["SERVER_PORT"]);
			} 
			else if ($md5 !== $currentKey)
			{
				$oldSessionId = session_id();
				session_regenerate_id();
				$_SESSION = array();
				$this->sessionIdChanged($oldSessionId);
			}
			else if ($this->read('SecurePort') !== $_SERVER["SERVER_PORT"])
			{
				$oldSessionId = session_id();
				session_regenerate_id();
				$this->write('SecurePort', $_SERVER["SERVER_PORT"]);
				$this->sessionIdChanged($oldSessionId);	
			}
		}
		else
		{
			$this->started = false;
		}
	}
	
	protected function stopSession()
	{
		if ($this->started === true)
		{
			$this->started = null;
			session_write_close();
		}
	}

	/**
	 * @return string
	 */
	protected function getSecureKey()
	{
		$string = 'CHANGEKEY';
		if (defined('SECURE_SESSION_BY_IP') &&  SECURE_SESSION_BY_IP)
		{
			$string .= $_SERVER['REMOTE_ADDR'];
		}
		return md5($string);
	}
	
	protected function sessionIdChanged($oldSessionId)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . ' ' . $oldSessionId . ' -> ' . session_id());
		}		
	}
	
	public function clearParameters()
	{
		$this->parameters = null;
		$this->parameters = array();
	}

	public function getParameter($name, $default = null)
	{
		return (isset($this->parameters[$name])) ? $this->parameters[$name] : $default;
	}

	public function getParameterNames()
	{
		return array_keys($this->parameters);
	}

	public function getParameters()
	{
		return $this->parameters;
	}

	public function hasParameter($name)
	{
		return isset($this->parameters[$name]);

	}

	public function removeParameter($name)
	{
		if (isset($this->parameters[$name]))
		{
			$retval = $this->parameters[$name];
			unset($this->parameters[$name]);
			return $retval;
		}
		return null;
	}

	public function setParameter($name, $value)
	{
		$this->parameters[$name] = $value;
	}

	public function setParameterByRef($name, &$value)
	{
		$this->parameters[$name] = &$value;
	}

	public function setParameters ($parameters)
	{
		$this->parameters = array_merge($this->parameters, $parameters);
	}

	public function setParametersByRef(&$parameters)
	{
		foreach ($parameters as $key => &$value)
		{
			$this->parameters[$key] = &$value;
		}
	}

	/**
	 * @return change_Context
	 */
	public function getContext()
	{
		return $this->context;
	}


	public static function newInstance($class)
	{
		$object = new $class();
		if (!($object instanceof change_Storage))
		{
			$error = 'Class "' .$class .'" is not of the type ChangeSessionStorage';
			$error = sprintf($error, $class);
			throw new Exception($error);
		}
		return $object;
	}
		

	

	public function &read($key)
	{
		if ($this->started === null) {$this->startSession();}
		$retval = null;
		if ($this->started && isset($_SESSION[$key]))
		{
			$retval = &$_SESSION[$key];
		}
		return $retval;
	}

	public function remove($key)
	{
		if ($this->started === null) {$this->startSession();}
		
		$retval = null;
		if ($this->started && isset($_SESSION[$key]))
		{
			$retval = $_SESSION[$key];
			unset($_SESSION[$key]);
		}
		return $retval;
	}

	public function shutdown ()
	{
		$this->stopSession();
	}

	public function write($key, &$data)
	{
		if ($this->started === null) {$this->startSession();}
		if ($this->started)
		{
			$_SESSION[$key] = &$data;
		}
	}
}
