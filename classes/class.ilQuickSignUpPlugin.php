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

	/**
	 * Create the registered user.
	 * @param $a_role
	 * @param $a_user_data
	 * @return bool
	 */
	public function createUser($a_role, $a_user_data)
	{
		global $rbacadmin;

		if(!$a_role)
		{
			global $ilias;
			$ilias->raiseError("Invalid role selection in registration".
				", IP: ".$_SERVER["REMOTE_ADDR"], $ilias->error_obj->FATAL);
		}

		$user_object = new ilObjUser();

		include_once("./Services/User/classes/class.ilUserProfile.php");
		$up = new ilUserProfile();
		$up->setMode(ilUserProfile::MODE_REGISTRATION);

		$user_object->setLogin($a_user_data["username"]);
		$user_object->setEmail($a_user_data["email"]);
		$user_object->setPasswd($a_user_data["password"]);

		if($user_object->create()) {

			/*Mandatory configuration*/
			$user_object->setActive(true);
			$user_object->setTimeLimitUnlimited(true);
			$user_object->setFirstname($a_user_data["firstname"]);
			$user_object->setLastname($a_user_data["lastname"]);
			//accept terms of service
			$date_time = new ilDateTime( time(),IL_CAL_UNIX);
			$user_object->setAgreeDate($date_time);

			//set user as self registered
			$user_object->setIsSelfRegistered(true);

			//store user in usr_data
			$user_object->saveAsNew();

			//Assign role to user
			$rbacadmin->assignUser((int)$a_role, $user_object->getId());


			//send mail notification
			$this->sendRegistrationEmail($user_object);

			//login
			$this->login($a_user_data['username'], $a_user_data['password']);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param $a_name string username
	 * @param $a_pass string pass
	 */
	public function login($a_name, $a_pass)
	{
		global $DIC;
		$auth_session = $DIC['ilAuthSession'];

		//login the user
		include_once './Services/Authentication/classes/Frontend/class.ilAuthFrontendCredentials.php';
		$credentials = new ilAuthFrontendCredentials();
		$credentials->setUsername($a_name);
		$credentials->setPassword($a_pass);

		include_once './Services/Authentication/classes/Provider/class.ilAuthProviderFactory.php';
		$provider_factory = new ilAuthProviderFactory();
		$providers = $provider_factory->getProviders($credentials);

		include_once './Services/Authentication/classes/class.ilAuthStatus.php';
		$status = ilAuthStatus::getInstance();

		include_once './Services/Authentication/classes/Frontend/class.ilAuthFrontendFactory.php';
		$frontend_factory = new ilAuthFrontendFactory();
		$frontend_factory->setContext(ilAuthFrontendFactory::CONTEXT_STANDARD_FORM);

		$frontend = $frontend_factory->getFrontend(
			$auth_session,
			$status,
			$credentials,
			$providers
		);

		$frontend->authenticate();
	}

	/**
	 * @param $a_user_object ilObjUser
	 */
	public function sendRegistrationEmail($a_user_object)
	{
		global $DIC;
		$lng = $DIC->language();
		// try individual account mail in user administration
		include_once("Services/Mail/classes/class.ilAccountMail.php");
		include_once './Services/User/classes/class.ilObjUserFolder.php';
		include_once "Services/Mail/classes/class.ilMimeMail.php";

		$senderFactory = $GLOBALS["DIC"]["mail.mime.sender.factory"];

		$mmail = new ilMimeMail();
		$mmail->From($senderFactory->system());
		$mmail->To($a_user_object->getEmail());

		// mail subject
		$subject = $lng->txt("reg_mail_subject");

		// mail body
		$body = $lng->txt("reg_mail_body_salutation")." ".$a_user_object->getLogin().",\n\n".
			$lng->txt("reg_mail_body_text1")."\n\n".
			$lng->txt("reg_mail_body_text2")."\n".
			ILIAS_HTTP_PATH."/login.php?client_id=".CLIENT_ID."\n";
		$body .= $lng->txt("login").": ".$a_user_object->getLogin()."\n";

		$body.= "\n";

		$body .= ($lng->txt("reg_mail_body_text3")."\n\r");
		$body .= $a_user_object->getProfileAsString($lng);
		$mmail->Subject($subject);
		$mmail->Body($body);
		$mmail->Send();

	}

}