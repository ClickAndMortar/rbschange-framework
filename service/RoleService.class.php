<?php
interface change_RoleService
{
	/**
	 * Returns the list of roles
	 * @return string[]
	 */
	function getRoles();
	
	/**
	 * replace isBackEndRole isFrontEndRole
	 * @param String $roleName
	 * @return Boolean
	 */
	function hasRole($roleName);	

	/**
	 * returns the compiled list of actions defined for the module in config/rights.xml
	 * 
	 * @return string[]
	 */
	function getActions();
	
	
	/**
	 * @param String $actionName
	 * @return Boolean
	 */
	function hasAction($actionName);

	/**
	 * @return string[]
	 */
	function getPermissions();
	
	/**
	 * @param String $permissionName
	 * @return Boolean
	 */
	function hasPermission($permissionName);

	
	/**
	 * returns the list of permissions attributed to each roles defined for 
	 * the module in config/roles.xml.
	 * 
	 * @param String $roleName full role name
	 * @return string[]
	 */
	function getPermissionsByRole($roleName);
		
	/**
	 * returns the list of permissions attributed to each roles defined for 
	 * the module in config/roles.xml.
	 * 
	 * @param String $roleName
	 * @return String
	 */
	function getRoleLabelKey($roleName);
	
	/**
	 * returns the list of permissions attributed to each roles defined for 
	 * the module in config/right.xml.
	 * 
	 * @param String $roleName
	 * @return string[]
	 */
	function getPermissionsByAction($actionName);
		
	/**
	 * @param string[] $permissions
	 * @return string[]
	 */
	function getActionsByPermissions($permissions);
	
}