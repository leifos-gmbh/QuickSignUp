<?php
include_once("./Services/COPage/classes/class.ilPageComponentPlugin.php");

/***
 * Plugin to add Sign in/Sign up button into pages opening one modalbox where the user can login/register.
 * Plugin slot: PageComponent
 * @author Jesús López Reyes <lopez@leifos.com>
 * @version $Id$
 */
class ilQuickSignUpPlugin extends ilPageComponentPlugin
{
	private static $instance = null;

	const PNAME = 'QuickSignUp';
	const SLOT_ID = 'pgcp';
	const CNAME = 'COPage';
	const CTYPE = 'Services';

	function getPluginName()
	{
		return "QuickSignUp";
	}

	/**
	 * All pages are allowed
	 * @param string $a_type
	 * @return bool
	 */
	function isValidParentType($a_type)
	{
		return true;
		/*
		if (in_array($a_parent_type, array("cont", "wpg")))
		{
			return true;
		}
		return false;
		*/
	}

	/**
	 * Get singleton instance
	 * @global ilPluginAdmin $ilPluginAdmin
	 * @return ilQuickSignUp
	 */
	public static function getInstance()
	{
		global $ilPluginAdmin;

		if(self::$instance)
		{
			return self::$instance;
		}
		include_once './Services/Component/classes/class.ilPluginAdmin.php';
		$instance = ilPluginAdmin::getPluginObject(
			self::CTYPE,
			self::CNAME,
			self::SLOT_ID,
			self::PNAME
		);

		return self::$instance = $instance;
	}

}