<?php
abstract class Controller
{

	/**
	 * @var ActionStack
	 */
	private $actionStack = null; 

	/**
	 * @var integer;
	 */
	private $maxForwards = 20; 
	
	/**
	 * @var integer;
	 */	
	private $renderMode = View::RENDER_CLIENT; 
	
	/**
	 * @var ChangeRequest
	 */
	private $request = null; 
	
	
	/**
	 * @var ChangeSessionStorage
	 */	
	private $storage = null; 
	
	/**
	 * @var FrameworkSecurityUser
	 */
	private $user = null;
	
	/**
	 * @var Context
	 */
	protected $context = null;
	
	/**
	 * 
	 * @var Controller
	 */
	private static $instance = null;
	
	abstract function dispatch();
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @return boolean
	 */
	abstract function actionExists($moduleName, $actionName);
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @return Action
	 */
	abstract function getAction($moduleName, $actionName);
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @return boolean
	 */	
	abstract function viewExists($moduleName, $viewName);
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 * @return View
	 */	
	abstract function getView($moduleName, $viewName);
	
	/**
	 * @param string $moduleName
	 * @param string $actionName
	 */
	public function forward($moduleName, $actionName)
	{
		
		$actionName = str_replace('.', '/', $actionName);
		$actionName = preg_replace('/[^a-z0-9\-_\/]+/i', '', $actionName);
		$moduleName = preg_replace('/[^a-z0-9\-_]+/i', '', $moduleName);
		
		if ($this->actionStack->getSize() >= $this->maxForwards)
		{
			throw new Exception('Too many forwards have been detected for this request');
		}
		
		if (!$this->actionExists($moduleName, $actionName))
		{

			// track the requested module so we have access to the data
			// in the error 404 page
			$this->request->setAttribute('requested_action', $actionName);
			$this->request->setAttribute('requested_module', $moduleName);
			
			// switch to error 404 action
			$moduleName = AG_ERROR_404_MODULE;
			$actionName = AG_ERROR_404_ACTION;
			
			if (!$this->actionExists($moduleName, $actionName))
			{
				$error = 'Invalid configuration settings: ' . 'AG_ERROR_404_MODULE "%s", ' . 'AG_ERROR_404_ACTION "%s"';
				$error = sprintf($error, $moduleName, $actionName);
				throw new Exception($error);			
			}		
		}
		
		// create an instance of the action
		$actionInstance = $this->getAction($moduleName, $actionName);
		
		// add a new action stack entry
		$this->actionStack->addEntry($moduleName, $actionName, $actionInstance);
							
		// initialize the action
		if ($actionInstance->initialize($this->context))
		{	
			// create a new filter chain		
			if ($actionInstance->isSecure())
			{
				if (!($this->user instanceof SecurityUser))
				{
					$error = 'Security is enabled, but your User ' . 'implementation isn\'t a sub-class of ' . 'SecurityUser';
					throw new Exception($error);
				}
				
				if (!$this->user->isAuthenticated())
				{
					$this->forward(AG_LOGIN_MODULE, AG_LOGIN_ACTION);
					return;
				}
			}
			
			$method = $this->request->getMethod();
			if (($actionInstance->getRequestMethods() & $method) != $method)
			{
				$viewName = $actionInstance->getDefaultView();
			} 
			else
			{
				if ($actionInstance->validate())
				{
					$viewName = $actionInstance->execute();
				} 
				else
				{
					$viewName = $actionInstance->handleError();
				}
			}

			if ($viewName != View::NONE)
			{
	
				if (is_array($viewName))
				{
					$moduleName = $viewName[0];
					$viewName   = $viewName[1];
	
				} 
				else
				{
					$viewName = $actionName . $viewName;
				}
	
				// display this view
				if (!$this->viewExists($moduleName, $viewName))
				{
					$error = 'Module "%s" does not contain the view "%sView"';
					$error = sprintf($error, $moduleName, $viewName);
					throw new Exception($error);
				}
	
				// get the view instance
				$viewInstance = $this->getView($moduleName, $viewName);
	
				// initialize the view
				if ($viewInstance->initialize($this->context))
				{
	
					// view initialization completed successfully
					$viewInstance->execute();
					$viewInstance->render();
				} 
				else
				{
	
					// view failed to initialize
					$error = 'View initialization failed for module "%s", view "%sView"';
					$error = sprintf($error, $moduleName, $viewName);
					throw new Exception($error);
				}
			}
		}		
	}

	public function getActionStack()
	{
		return $this->actionStack;
	}
	
	public function getContext()
	{
		return $this->context;
	}

	public static function getInstance()
	{	
		if (null !== self::$instance)
		{
			return self::$instance;
		}
		return self::newInstance("controller_ChangeController");
	}
	

	public function getRenderMode()
	{	
		return $this->renderMode;
	}
	
	protected function initialize()
	{
		$this->maxForwards = defined('AG_MAX_FORWARDS') ? AG_MAX_FORWARDS : 20;		
		$this->loadContext();
		$this->actionStack = $this->context->getActionStack();
		$this->request = $this->context->getRequest();
		$this->user = $this->context->getUser();
		$this->storage = $this->context->getStorage();
		
		register_shutdown_function(array($this, 'shutdown'));
	}
	
	protected function loadContext()
	{
		$this->context = Context::getInstance($this);
	}

	public static function newInstance($class)
	{
		try
		{
			if (null === self::$instance)
			{
				$controllerClassName = Framework::getConfiguration('mvc/classes/'.$class);
				$controller = new $controllerClassName();
				if (! ($controller instanceof Controller))
				{
					$error = 'Class "%s" is not of the type Controller';
					$error = sprintf($error, $controllerClassName);	
					throw new Exception($error);
				}
				
				self::$instance = $controller;	
				return self::$instance;
			}
			else
			{
				$error = 'A Controller implementation instance has already been created';
				throw new Exception($error);			
			}
		}
		catch (Exception $e)
		{
			echo $e->getMessage() . "\n";
			echo $e->getTraceAsString() . "\n";
		}
	}
	
	public function setRenderMode($mode)
	{
		if ($mode == View::RENDER_CLIENT || $mode == View::RENDER_VAR || $mode == View::RENDER_NONE)
		{
			$this->renderMode = $mode;
			return;	
		}
		// invalid rendering mode type
		$error = 'Invalid rendering mode: %s';
		$error = sprintf($error, $mode);		
		throw new Exception($error);
	}
	
	public function shutdown()
	{	
		$this->user->shutdown();
		$this->storage->shutdown();	
		session_write_close();	
		$this->request->shutdown();	
	}
}

abstract class WebController extends Controller
{
	
	private $contentType = null;
	
	/**
	 */
	public function genURL($url = null, $parameters = array())
	{
		if ($url == null)
		{
			$url = $_SERVER['SCRIPT_NAME'];
		}
		
		if (count($parameters))
		{
			return $url . ((strpos($url, '?') !== false) ? '&' : '?') . http_build_query($parameters, 'c', '&');
		}
		return $url;
	}
	
	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}
	
	protected function initialize()
	{
		
		// initialize parent
		parent::initialize();	
		// set our content type
		$this->contentType = $this->getContext()->getRequest()->getParameter('ctype', AG_CONTENT_TYPE);
	}
	
	/**
	 * @param string $url
	 * @param integer $delay
	 */
	public function redirect($url, $delay = 0)
	{		
		// shutdown the controller
		$this->shutdown();
		if (headers_sent() == false)
		{
			header('Location: ' . $url);
		}
		$echo = '<html><head><meta http-equiv="refresh" content="%d;url=%s"/></head></html>';	
		$echo = sprintf($echo, $delay, $url);		
		echo $echo;		
		exit();	
	}
	
	/**
	 * @param string $type
	 */
	public function setContentType($type)
	{
		$this->contentType = $type;	
	}
}

class HttpController extends WebController
{
	public function __construct()
	{
		// initialize the controller
	    $this->initialize();
	}
	
    /**
     * Dispatch a request.
     */
    public function dispatch ()
    {
 		// get the application context
	    $context = $this->getContext();
	    // determine our module and action
	    $moduleName = $context->getRequest()->getParameter('module');
    	if ($moduleName == null)
		{
			$moduleName = AG_DEFAULT_MODULE;
		}
		
	    $actionName = $context->getRequest()->getParameter('action');
		if ($actionName == null)
		{
			// no action has been specified
			if ($this->actionExists($moduleName, AG_DEFAULT_ACTION))
			{
				 // an Index action exists
			   	$actionName = AG_DEFAULT_ACTION;
			}
			else
			{
			    $moduleName =  AG_ERROR_404_MODULE;
			    $actionName = AG_ERROR_404_ACTION;
			}
	    }
	    // make the first request
	    $this->forward($moduleName, $actionName);
    }
    

	public function redirect($moduleName, $actionName)
	{
		$request = $this->getContext()->getRequest();	
		$requestParametersNames = $request->getParameterNames();
		
		$urlParam = array();
		foreach ($requestParametersNames as $requestParameterName)
		{
			if (is_string($request->getParameter($requestParameterName)))
			{
				$urlParam[$requestParameterName] = $request->getParameter($requestParameterName);
			}
		}
		
		$urlParam['module'] = $moduleName;
		$urlParam['action'] = $actionName;
		
		$url = $this->generateRedirectUrl($urlParam);
		
		if (Framework::isDebugEnabled())
		{
			Framework::debug("Controller->redirect ; resulting URL : $url", 'controller');
		}
		
		// shutdown the controller
		$this->shutdown();
		if (!headers_sent())
		{
			header('Location: ' . $url);
		}
		else
		{
			if (Framework::isWarnEnabled())
			{
				Framework::warn('Controller->redirect called while header already sent', 'controller');
			}
		}
		exit();
	}
	    
	public function actionExists($moduleName, $actionName)
	{
		return f_util_ClassUtils::classExists(strtolower($moduleName) . '_' . $actionName . "Action");
	}
	
	public function getAction($moduleName, $actionName)
	{
		$className = $moduleName . '_' . $actionName . 'Action';
		ClassLoader::getInstance()->load($className);	
		return new $className();
	}

	public function viewExists($moduleName, $viewName)
	{
		return f_util_ClassUtils::classExists($moduleName . '_' . $viewName . 'View');
	}
	
	public function getView($moduleName, $viewName)
	{
		$className = $moduleName . '_' . $viewName . 'View';
		ClassLoader::getInstance()->load($className);	
		return new $className();
	}
	
	public function forward($moduleName, $actionName)
	{
		$this->pushEffectiveModuleName($moduleName);
		try
		{
			if (!$moduleName !== K::GENERIC_MODULE_NAME && !$this->actionExists($moduleName, $actionName))
			{
				$this->getRequest()->setParameter(K::WEBEDIT_MODULE_ACCESSOR, $moduleName);
				$moduleName = K::GENERIC_MODULE_NAME;
			}
			parent::forward($moduleName, $actionName);
			$this->popEffectiveModuleName();
		}
		catch (Exception $e)
		{
			Framework::exception($e);
			$this->popEffectiveModuleName();
		}			
	}
	
	public function modelExists($moduleName, $modelName)
	{
		return false;	
	}
	
	/**
	 * @return Request
	 */
	private function getRequest()
	{
		return $this->getContext()->getRequest();
	}
	
	/**
	 * @param String $moduleName
	 */
	private function pushEffectiveModuleName($moduleName)
	{
		$moduleStack = $this->getModuleStack();
		$moduleStack[] = $moduleName;
		$this->getRequest()->setAttribute(K::EFFECTIVE_MODULE_NAME, $moduleStack);
	}
	
	/**
	 * @return String[]
	 */
	private function getModuleStack()
	{
		$moduleStack = null;
		if (!$this->getRequest()->hasAttribute(K::EFFECTIVE_MODULE_NAME))
		{
			$moduleStack = array();
		}
		else
		{
			$moduleStack = $this->getRequest()->getAttribute(K::EFFECTIVE_MODULE_NAME);
		}
		return $moduleStack;
	}
	
	/**
	 */
	private function popEffectiveModuleName()
	{
		$moduleStack = $this->getModuleStack();
		array_pop($moduleStack);
		$this->getRequest()->setAttribute(K::EFFECTIVE_MODULE_NAME, $moduleStack);
	}
	
	/**
	 * @param array $urlParams
	 */
	protected function generateRedirectUrl($urlParams)
	{
		return $this->genURL('http://' . $_SERVER['HTTP_HOST'] . '/index.php', $urlParams);
	}
	
	public function redirectToUrl($url)
	{
		parent::redirect($url);
	}
}