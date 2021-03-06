<?php
/**
 * SecurityGenerator
 */
class builder_SecurityGenerator
{
	const ROLE_DEF_FILE_NAME ='rights.xml';
	const CONFIG_DIR_NAME = 'config';

	private $quiet = false;

	private $logs = array();
	
	private $classBackList;
	private $classFrontList;

	/**
	 * @var ModuleRoles
	 */
	private $baseModuleAction;

	private $baseBackOfficeAction;

	
	public function buildSecurity()
	{
		$this->loadBaseAction();

		$moduleService = ModuleService::getInstance();
		$this->initClassBackList();
		$this->initClassFrontList();
		
		foreach ($moduleService->getModules() as $moduleName)
		{
			$shortModuleName = $moduleService->getShortModuleName($moduleName);
			$baseRightsPath = f_util_FileUtils::buildWebeditPath('modules', $shortModuleName, self::CONFIG_DIR_NAME, self::ROLE_DEF_FILE_NAME);
			if (!file_exists($baseRightsPath)) {$baseRightsPath = null;}
			$overrideRightsPath = f_util_FileUtils::buildOverridePath('modules', $shortModuleName, self::CONFIG_DIR_NAME, self::ROLE_DEF_FILE_NAME);
			if (!file_exists($overrideRightsPath)) {$overrideRightsPath = null;}
			
			f_util_FileUtils::rmdir(f_util_FileUtils::buildChangeBuildPath('modules', $shortModuleName, 'roles'));
			
			if ($baseRightsPath === null && $overrideRightsPath === null)
			{
				$this->logs[] = "Module $shortModuleName skipped";
				continue;
			}
			$this->generateRoleSystemFiles($shortModuleName, $baseRightsPath, $overrideRightsPath);
			$this->logs[] = "Module $shortModuleName OK";
		}

		return $this->logs;
	}
	
	/**
	 * @return ModuleRoles
	 */
	private function getBaseModuleAction()
	{
		if ($this->baseModuleAction === null)
		{
			$this->loadBaseAction();
		}
		return $this->baseModuleAction;
	}
	
	/**
	 * @param boolean $bool
	 */
	public function setQuiet($bool)
	{
		$this->quiet = $bool;
	}

	/**
	 * @param string $moduleName
	 * @return array
	 */
	public function getRolesFields($moduleName)
	{
		$baseRightsPath = f_util_FileUtils::buildWebeditPath('modules', $moduleName, self::CONFIG_DIR_NAME, self::ROLE_DEF_FILE_NAME);
		if (!file_exists($baseRightsPath)) {$baseRightsPath = null;}
		$overrideRightsPath = f_util_FileUtils::buildOverridePath('modules', $moduleName, self::CONFIG_DIR_NAME, self::ROLE_DEF_FILE_NAME);
		if (!file_exists($overrideRightsPath)) {$overrideRightsPath = null;}
		
		if ($baseRightsPath === null  && $overrideRightsPath === null)
		{
			return array();	
		}
		
		$module = $this->loadModuleRoles($moduleName, $baseRightsPath, $overrideRightsPath);
		$rolesFields = array();
		if (count($module->getPermissions()) > 0)
		{	
			$this->initClassBackList();
			$this->initClassFrontList();
		
			$roles = $module->getRoles();			
			foreach ($roles as $role) 
			{
				if ($role->isFrontEnd())
				{
					$rolesFields[$role->getName()] = 
						array('type' => 'front', 'class' => $this->classFrontList);
				}
				else
				{
					$rolesFields[$role->getName()] = 
						array('type' => 'back', 'class' => $this->classBackList);
				}
			}
		}
		return $rolesFields;
	}
	
	private function initClassBackList()
	{	
		if ($this->classBackList === null)
		{
			$list = array(str_replace('/', '_', 'modules_users/backenduser'), str_replace('/', '_', 'modules_users/backendgroup'));
			$boUser = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_users/backenduser');
			if ($boUser->hasChildren())
			{
				foreach ($boUser->getChildrenNames() as $childrenName) 
				{
					$list[] = str_replace('/', '_', $childrenName);
				}
			}
			$boGroup = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_users/backendgroup');
			if ($boGroup->hasChildren())
			{
				foreach ($boGroup->getChildrenNames() as $childrenName) 
				{
					$list[] = str_replace('/', '_', $childrenName);
				}
			}
			$this->classBackList = implode(',', $list);	
		}
	}
	
	private function initClassFrontList()
	{
		if ($this->classFrontList === null)
		{
			$list = array('modules_users_frontenduser', 'modules_users_frontendgroup');
			$feUser = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_users/frontenduser');
			if ($feUser->hasChildren())
			{
				foreach ($feUser->getChildrenNames() as $childrenName) 
				{
					$list[] = str_replace('/', '_', $childrenName);
				}
			}
	
			$feGroup = f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_users/frontendgroup');
			if ($feGroup->hasChildren())
			{
				foreach ($feGroup->getChildrenNames() as $childrenName) 
				{
					$list[] = str_replace('/', '_', $childrenName);
				}
			}
			$this->classFrontList = implode(',', $list);
		}
	}
	
	/**
	 * @param string $moduleName
	 * @param string $baseRightsPath
	 * @param string $overrideRightsPath
	 * @return ModuleRoles
	 */
	private function loadModuleRoles($moduleName, $baseRightsPath, $overrideRightsPath)
	{
		$module = new ModuleRoles($baseRightsPath, $moduleName);
		$module->classBackList = $this->classBackList;
		$module->classFrontList = $this->classFrontList;
		
		if ($overrideRightsPath !== null)
		{
			$domDoc = f_util_DOMUtils::fromPath($overrideRightsPath);
			$module->loadXml($domDoc->documentElement);
		}
		
		if ($baseRightsPath !== null)
		{
			$domDoc = f_util_DOMUtils::fromPath($baseRightsPath);
			$module->loadXml($domDoc->documentElement);
		}

		$actions  = $this->getBaseModuleAction()->getDocumentAction(null);
		$module->importActions($actions, null);
		
		$documentActions = $this->getBaseModuleAction()->getDocumentAction('basedocument');
		foreach ($module->documents as $name => $ignore)
		{
			if (!$ignore)
			{
				$module->importActions($documentActions, $name);
			}
		}

		//Genere les permissions implicite
		$module->generateImplicitePermission();

		//transform les permissions virtuel
		$module->extendVirtualRolePermission();

		$module->extendRole();

		$module->cleanPermissions();

		return $module;
		
	}
	
	/**
	 * Generate cached {$moduleName}RoleService.class.php, {$moduleName}RoleService.class.php in cache/config
	 *
	 * @param String $moduleName SHORT target moduleName
	 * @param String $baseRightsPath FULL qualified path to rights.xml for the original module.
	 * @param String $overrideRightsPath FULL qualified path to rights.xml for overrided the module.
	 */

	private function generateRoleSystemFiles($moduleName, $baseRightsPath, $overrideRightsPath)
	{
		$module = $this->loadModuleRoles($moduleName, $baseRightsPath, $overrideRightsPath);
		
		if (count($module->getPermissions()) > 0)
		{
			$backOfficeActions = array_merge($this->baseBackOfficeAction, $this->getBackOfficeActionsName($moduleName));
			foreach ($module->actions as $action)
			{
				if (array_key_exists($action->getBackOfficeName(), $backOfficeActions))
				{
					unset($backOfficeActions[$action->getBackOfficeName()]);
				}
			}

			$module->backOfficeActions = $backOfficeActions;
			$className = ucfirst($moduleName) . 'RoleService';
			$filePath = f_util_FileUtils::buildChangeBuildPath('modules', $moduleName, 'roles', $className . '.class.php');
			f_util_FileUtils::writeAndCreateContainer($filePath, $this->generateFile('RightsService' , 'permissions' , $module), f_util_FileUtils::OVERRIDE );
			ClassResolver::getInstance()->appendToAutoloadFile('roles_'.$className, $filePath);

			if (!uixul_ModuleBindingService::getInstance()->hasConfigFile($moduleName))
			{
				$buildForm = f_util_FileUtils::buildChangeBuildPath('modules', $module->name, 'forms');
				$filePath = f_util_FileUtils::buildPath($buildForm, "permission_layout.all.all.xul");
				f_util_FileUtils::writeAndCreateContainer($filePath, $this->generateFile('PermissionForm', 'permissions' , $module), f_util_FileUtils::OVERRIDE );
				$implFile = f_util_FileUtils::buildFrameworkPath('builder', 'templates', 'permissions', 'permission_impl.js');
				$filePath = f_util_FileUtils::buildPath($buildForm, 'permission_impl.js');			
				copy($implFile, $filePath);
			}
		}
	}

	private function generateFile($templateName, $directory, $module)
	{
		$generator = new builder_Generator($directory);
		$generator->assign('module', $module);
		$result = $generator->fetch($templateName .'.tpl');
		return $result;
	}

	private function loadBaseAction()
	{
		$path = f_util_FileUtils::buildFrameworkPath('config', 'rights.xml');
		$domDoc = f_util_DOMUtils::fromPath($path);
		$baseModule = new ModuleRoles($path, 'base');
		$baseModule->loadXml($domDoc->documentElement);
		$this->baseModuleAction = $baseModule;
		$this->baseBackOfficeAction = $this->getBackOfficeActionsName('uixul');
	}
	
	private function getBackOfficeActionsName($moduleName)
	{
		$backOfficeActions = array();
		$path = FileResolver::getInstance()
			->setPackageName('modules_'.$moduleName)
			->setDirectory(self::CONFIG_DIR_NAME)
			->getPath('actions.xml');		
		if ($path !== null)
		{
			$domDoc = f_util_DOMUtils::fromPath($path);
			foreach ($domDoc->getElementsByTagName('action') as $actionNode)
			{
				$name = $actionNode->getAttribute('name');
				$backOfficeActions[$name] = $name;
			}
		}
		return $backOfficeActions;
	}
}

class ModuleRoles
{
	public $filePath;
	public $name;
	public $backOfficeActions;
	public $classBackList;
	public $classFrontList;

	public $documents = array();

	public $actions = array();
	public $roles = array();

	private $permissions = array();


	public function __construct($filePath, $name)
	{
		$this->filePath = $filePath;
		$this->name = $name;
	}

	public function getDocumentAction($documentName)
	{
		$actions = array();
		foreach ($this->actions as $action)
		{
			if ($action->getDocumentName() == $documentName)
			{
				$actions[] = $action;
			}
		}
		return $actions;
	}

	public function importActions($actions, $documentName = null)
	{
		foreach ($actions as $action)
		{
			$newAction = $this->getActionInfo($action->getShortName(), $documentName);
			$newAction->setBackOfficeName($action->getBackOfficeName());
		}
	}

	/**
	 * @param DOMElement $documentElement
	 */
	public function loadXml($documentElement)
	{
		foreach ($documentElement->childNodes as $node)
		{
			if ($node->nodeType === XML_ELEMENT_NODE)
			{
				switch ($node->nodeName)
				{
					case 'actions':
						$this->loadXmlActions($node);
						break;
					case 'roles':
						$this->loadXmlRoles($node);
						break;
					case 'import':
						$module = $node->getAttribute('modulename');
						$fileName = $node->hasAttribute('configfilename') ? $node->getAttribute('configfilename') . '.xml' : 'rights.xml';
						$path = FileResolver::getInstance()
							->setPackageName('modules_' . $module)
							->setDirectory('config')->getPath($fileName);
						if ($path !== null)
						{
							$doc = f_util_DOMUtils::fromPath($path);
							$this->loadXml($doc->documentElement);
						}
						else
						{
							Framework::warn(__METHOD__ . " Unable to find $fileName in $module config dir");
						}
						break;	
					default:
						break;
				}
			}
		}
	}

	/**
	 * @param DOMElement $element
	 */
	private function loadXmlActions($element)
	{
		foreach ($element->childNodes as $node)
		{
			switch ($node->nodeName)
			{
				case 'document':
					$documentName = $node->getAttribute('name');
					$ignoreBase = ($node->hasAttribute('ignore-base') && self::strtoboolean($node->getAttribute('ignore-base')));
					$this->documents[$documentName] = $ignoreBase;

					foreach ($node->childNodes as $actionNode)
					{
						if ($actionNode->nodeName == 'action')
						{
							$name = $actionNode->getAttribute('name');
							$action = $this->getActionInfo($name, $documentName);
							$action->loadXmlAction($actionNode);
						}
					}

					break;
				case 'action':
					$name = $node->getAttribute('name');
					$action = $this->getActionInfo($name);
					$action->loadXmlAction($node);
					break;
				default:
					break;
			}
		}
	}

	/**
	 * @param DOMElement $element
	 */
	private function loadXmlRoles($element)
	{
		foreach ($element->childNodes as $node)
		{
			switch ($node->nodeName)
			{
				case 'rootrole':
				case 'role':
				case 'frontendrole':
					$role = $this->getRoleByName($node->getAttribute('name'));
					if ($role === null)
					{
						$role = new RoleInfo($node->nodeName);
						$this->roles[] = $role;
					}
					$role->loadXmlRole($node);
					break;
				default:
					break;
			}
		}
	}
	
	
	/**
	 * @param string $roleName
	 * @return RoleInfo
	 */
	public function getRoleByName($roleName)
	{
		foreach ($this->roles as $role)
		{
			if ($role->getName() == $roleName)
			{
				return $role;
			}
		}
		return null;
	}

	/**
	 * @param string $name
	 * @param string $documentName
	 * @return ActionInfo
	 */
	private function getActionInfo($name, $documentName = null)
	{
		$fullName = is_null($documentName) ? $name : $name . '.' . $documentName;

		foreach ($this->actions as $action)
		{
			if ($action->getFullName() == $fullName)
			{
				return $action;
			}
		}

		$action = new ActionInfo($name, $documentName);
		$this->actions[] = $action;
		return $action;
	}

	/**
	 * @param String $string
	 * @return Boolean
	 */
	public static function strtoboolean($string)
	{
		$string = strtolower($string);
		if ($string == 'true' || $string == '1' || $string == 'yes')
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public function generateImplicitePermission()
	{
		foreach ($this->actions as $action)
		{
			$action->setDefaultPermission();
		}
	}

	private function getActionPermissions()
	{
		$result = array();
		foreach ($this->actions as $action)
		{
			foreach ($action->getPermissions() as $permission)
			{
				$result[$permission->getName()] = $permission;
			}
		}
		return $result;
	}

	public function extendVirtualRolePermission()
	{
		$this->permissions = $this->getActionPermissions();
		$frontEndPermissions = $this->getFrontEndPermissions();

		foreach ($this->roles as $role)
		{
			foreach ($role->getPermissions() as $permission)
			{
				$permissionName = $permission->getName();
				if ($permission->isVirtual())
				{
					$permissions = $this->getActionsPermissions($permissionName, $frontEndPermissions);
					foreach ($permissions as $actperm)
					{
						$role->addPermission($actperm);
						if ($permissionName != '*')
						{
							$actperm->setInRole();
						}
					}
				}
				else if (array_key_exists($permissionName, $this->permissions))
				{
					$this->permissions[$permissionName]->setInRole();
					$permission->setInAction();
				}
			}
		}
	}


	private function getActionsPermissions($name, $frontEndPermissions)
	{
		$result = array();
		if ($name == '*')
		{
			if (count($frontEndPermissions) == 0)
			{
				return $this->permissions;
			}
			foreach ($this->permissions as $name => $permission)
			{

				if (!array_key_exists($name, $frontEndPermissions))
				{
					$result[$name] = $permission;
				}
			}
			return $result;
		}

		$partName = explode('.', $name);
		foreach ($this->permissions as $permission)
		{
			$part = explode('.', $permission->getName());
			if (count($part) == count($partName))
			{
				if ($partName[0] != '*')
				{
					if ($partName[0] == $part[0])
					{
						$result[$permission->getName()] = $permission;
					}
				}
				else if (array_key_exists(1, $partName) && $partName[1] != '*')
				{
					if ($partName[1] == $part[1])
					{
						$result[$permission->getName()] = $permission;
					}
				}
			}
		}

		return $result;
	}

	public function getRoles()
	{
		$result = array();
		foreach ($this->roles as $role)
		{
			if (!$role->isRoot())
			{
				$result[] = $role;
			}
		}
		return $result;
	}

	public function getActions()
	{
		return $this->actions;
	}

	public function getFrontEndPermissions()
	{
		$result = array();
		foreach ($this->roles as $role)
		{
			foreach ($role->getPermissions() as $permission)
			{
				if ($permission->isFrontEnd())
				{
					$result[$permission->getName()] = $permission;
				}
			}
		}
		return $result;
	}

	public function extendRole()
	{
		foreach ($this->roles as $role)
		{
			$role->generateExtendRole($this);
		}
	}

	public function cleanPermissions()
	{
		$actions = array();
		foreach ($this->actions as $action)
		{
			$action->cleanNotDefinedPermission();
			if (count($action->getPermissions()) != 0)
			{
				$actions[] = $action;
			}
		}
		$this->actions = $actions;

		$this->permissions = $this->getActionPermissions();

		$roles = array();
		$rootRole = null;
		foreach ($this->roles as $role)
		{
			$role->cleanNotDefinedPermission($this->permissions);
			if (count($role->getPermissions()) != 0)
			{
				if ($role->isRoot())
				{
					$rootRole = $role;
				}
				$roles[] = $role;
			}
		}

		$this->roles = $roles;

		if (!is_null($rootRole))
		{
			$roles = array();
			foreach ($this->roles as $role)
			{
				$role->removeRootPermission($rootRole);
				if (count($role->getPermissions()) != 0)
				{
					$roles[] = $role;
				}
			}
			$this->roles = $roles;
		}
	}

	public function getPermissions()
	{
		return $this->permissions;
	}

	public function getPrefix()
	{
		return 'modules_' . $this->name . '.';
	}

	public function __toString()
	{
		$string =  $this->getPrefix() . "(\n";
		foreach ($this->actions as $action)
		{
			$string .= $action->__toString();
		}

		foreach ($this->roles as $role)
		{
			$string .= $role->__toString();
		}

		$string .= "Global permission List : \n";
		foreach ($this->permissions as $permission)
		{
			$string .= $permission->__toString();
		}
		$string .= ")\n";
		return $string;
	}

}


class ActionInfo
{
	private $name;
	private $documentName;
	private $backOfficeName;
	private $inheritPermissions = false;

	private $permissions = array();

	public function __construct($name, $documentName)
	{
		$this->name = $name;
		$this->documentName = $documentName;
	}

	public function isDefined()
	{
		$isDefined = false;
		foreach ($this->permissions as $permission)
		{
			$isDefined = $isDefined || $permission->isDefined();
		}
		return $isDefined;
	}

	public function getFullName()
	{
		return is_null($this->documentName) ? $this->name : $this->name . '.' . $this->documentName;
	}

	public function getDocumentName()
	{
		return $this->documentName;
	}

	public function getShortName()
	{
		return $this->name;
	}

	public function setBackOfficeName($backName)
	{
		if (is_null($this->backOfficeName))
		{
			$this->backOfficeName = $backName;
		}
	}

	public function getBackOfficeName()
	{
		if (is_null($this->backOfficeName))
		{
			return strtolower(substr($this->name, 0 , 1)).substr($this->name, 1);;
		}
		return $this->backOfficeName;
	}

	/**
	 * @param DOMElement $element
	 */
	public function loadXmlAction($element)
	{
		$this->name = $element->getAttribute('name');

		if ($element->hasAttribute('back-office-name'))
		{
			$this->backOfficeName = $element->getAttribute('back-office-name');
		}

		if ($element->hasAttribute('inherit-permissions') || ModuleRoles::strtoboolean($element->getAttribute('inherit-permissions')))
		{
			$this->inheritPermissions = true;
		}

		foreach ($element->childNodes as $permissionNode)
		{
			if ($permissionNode->nodeName == 'permission')
			{
				$name = $permissionNode->getAttribute('name');
				if (!is_null($this->documentName))
				{
					$name = $this->documentName . '.' . $name;
				}
				$permission = new PermissionInfo($name);
				$permission->setInAction();
				$this->permissions[] = $permission;
			}
		}
	}

	public function clearPermissions()
	{
		$this->permissions = array();
	}

	public function setDefaultPermission()
	{
		if ($this->inheritPermissions || count($this->permissions) == 0)
		{
			$permission = new PermissionInfo($this->getFullName());
			$permission->setInAction();
			$this->permissions[] = $permission;
		}
	}

	public function cleanNotDefinedPermission()
	{
		$result = array();
		foreach ($this->permissions as $permission)
		{
			if ($permission->isDefined())
			{
				$result[$permission->getName()] = $permission;
			}
		}
		$this->permissions = $result;
	}

	public function getPermissions()
	{
		return $this->permissions;
	}

	public function __toString()
	{
		$string = '	Action:' . $this->getFullName();
		$string .= "\n";
		foreach ($this->permissions as $permissions)
		{
			$string .= $permissions->__toString();
		}
		return $string;
	}
}

class RoleInfo
{
	private $name;
	private $permissions = array();

	private $frontEnd = false;
	private $rootRole = false;

	private $extendRole;

	public function __construct($roleType)
	{
		switch ($roleType)
		{
			case 'rootrole':
				$this->rootRole = true;
				break;
			case 'frontendrole':
				$this->frontEnd = true;
				break;
		}
	}

	public function generateExtendRole($moduleRoles)
	{
		if (!is_null($this->extendRole))
		{
			$baseRole = $moduleRoles->getRoleByName($this->extendRole);
			$this->extendRole = null;
			if (!is_null($baseRole))
			{
				$baseRole->generateExtendRole($moduleRoles);
				foreach ($baseRole->permissions as $permission)
				{
					$this->addPermission($permission);
				}
			}
		}
	}

	public function isDefined()
	{
		$isDefined = false;
		if ($this->rootRole)
		{
			return $isDefined;
		}
		foreach ($this->permissions as $permission)
		{
			$isDefined = $isDefined || $permission->isDefined();
		}
		return $isDefined;
	}

	/**
	 * @param DOMElement $element
	 */
	public function loadXmlRole($element)
	{
		if ($element->hasAttribute('name'))
		{
			$this->name = $element->getAttribute('name');
		}

		if ($element->hasAttribute('extend'))
		{
			$this->extendRole = $element->getAttribute('extend');
		}

		foreach ($element->childNodes as $permissionNode)
		{
			if ($permissionNode->nodeName == 'permission')
			{
				$name = $permissionNode->getAttribute('name');
				$permission = new PermissionInfo($name);
				if ($this->isFrontEnd())
				{
					$permission->setFrontEnd();
				}
				$permission->setInRole();
				$this->permissions[] = $permission;
			}
		}
	}

	public function getPermissions()
	{
		return $this->permissions;
	}

	public function addPermission($permission)
	{
		$this->permissions[] = $permission;
	}

	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param RoleInfo $rootRole
	 */
	public function removeRootPermission($rootRole)
	{
		if ($this == $rootRole) {return;}
		foreach ($rootRole->permissions as $name => $permissions)
		{
			if (array_key_exists($name, $this->permissions))
			{
				unset($this->permissions[$name]);
			}
		}
	}

	public function cleanNotDefinedPermission($permissions)
	{
		$result = array();
		foreach ($this->permissions as $permission)
		{
			if (array_key_exists($permission->getName(), $permissions))
			{
				$result[$permission->getName()] = $permissions[$permission->getName()];
			}
		}

		if ($this->isFrontEnd())
		{
			foreach ($result as $permission)
			{
				$permission->setFrontEnd();
			}
		}

		$this->permissions = $result;
	}

	public function isFrontEnd()
	{
		return $this->frontEnd;
	}

	public function isRoot()
	{
		return $this->rootRole;
	}

	public function __toString()
	{
		$string .= '	Role:' . $this->name;
		if ($this->frontEnd) { $string .= " (FrontEnd)";}
		$string .= "\n";
		foreach ($this->permissions as $permissions)
		{
			$string .= $permissions->__toString();
		}
		return $string;
	}
}

class PermissionInfo
{
	private $name;
	private $frontEnd = false;
	private $virtual = false;
	private $hasRole = false;
	private $hasAction = false;


	public function __construct($name)
	{
		$this->name = $name;
		$this->virtual = (strpos($name, '*') !== false);
	}


	public function getName()
	{
		return $this->name;
	}

	public function setInRole()
	{
		$this->hasRole = true;
	}

	public function setInAction()
	{
		$this->hasAction = true;
	}

	public function setFrontEnd()
	{
		$this->frontEnd = true;
	}

	public function isDefined()
	{
		return $this->hasRole && $this->hasAction && !$this->virtual;
	}

	public function isVirtual()
	{
		return $this->virtual;
	}

	public function isFrontEnd()
	{
		return $this->frontEnd;
	}

	public function __toString()
	{
		$string = '		P:'.$this->name;
		if ($this->isDefined()) {$string .= " (Defined)";}
		$string .= "\n";
		return $string;
	}
}