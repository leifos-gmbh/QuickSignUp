<?php
include_once("./Services/COPage/classes/class.ilPageComponentPluginGUI.php");

/**
 * Quick login/register modalbox user interface
 *
 *
 * DEV NOTES
 * 1. There is no description of which button type should be used. I'm using the standard without customization.
 * 2. viewcontrol "mode" was my first thinking.
 * 3. Round-Trip rules:
 *       Round-Trip modals MUST contain at least two buttons at the bottom of the modals: a button
 *       to cancel (right) the workflow and a button to finish or reach the next step in the workflow (left).
 *     2: >
 *
 * TODO move all the globals to one single point.
 *
 * @author Jesús López <lopez@leifos.com>
 * @version $Id$
 * @ilCtrl_isCalledBy ilQuickSignUpPluginGUI: ilPCPluggedGUI
 * @ilCtrl_Calls ilQuickSignUpPluginGUI: ilPasswordAssistanceGUI
 *
 */
class ilQuickSignUpPluginGUI extends ilPageComponentPluginGUI
{
	const MD_LOGIN_VIEW = 1;
	const MD_REGISTER_VIEW = 2;

	var $register_success = false;
	var $register_message = "";
	var $tab_option = self::MD_LOGIN_VIEW;

	function executeCommand()
	{
		global $DIC;
		$ctrl = $DIC->ctrl();

		$next_class = $ctrl->getNextClass();

		switch ($next_class)
		{
			case "ilpasswordassistancegui":
				require_once("Services/Init/classes/class.ilPasswordAssistanceGUI.php");
				return $ctrl->forwardCommand(new ilPasswordAssistanceGUI());

			//todo add register commands in the array.
			default:
				// perform valid commands
				$cmd = $ctrl->getCmd();
				if (in_array($cmd, array("create", "save", "edit", "edit2", "update", "cancel", "login", "test", "register","standardAuthentication")))
				{
					$this->$cmd();
				}
				break;
		}
	}

	/**
	 * Get HTML for element
	 *
	 * @param string $a_mode (edit, presentation, preview, offline)s
	 * @return string $html
	 */
	function getElementHTML($a_mode, array $a_properties, $a_plugin_version)
	{
		//globals
		global $DIC;
		$user = $DIC->user();

		//If the user is not anonymous exit.
		if(!$user->isAnonymous()) {
			return "";
		}

		$f = $DIC->ui()->factory();
		$r = $DIC->ui()->renderer();
		$ctrl = $DIC->ctrl();

		$modal = $f->modal()->roundtrip("Modal Title", $f->legacy(""));
		$ctrl->setParameter($this, "replaceSignal", $modal->getReplaceContentSignal()->getId());

		$modal = $modal->withAsyncRenderUrl($this->getLoginUrl());
		$button = $f->button()->standard("Sign In", '#')
			->withOnClick($modal->getShowSignal());
		$content = $r->render([$modal, $button]);

		return $content;
	}

	/**
	 * Show navigation
	 *
	 * @param
	 * @return
	 */
	function getNavigation()
	{
		//globals
		global $DIC;
		$f = $DIC->ui()->factory();
		$ctrl = $DIC->ctrl();

		$replaceSignal = new \ILIAS\UI\Implementation\Component\Modal\ReplaceContentSignal($_GET["replaceSignal"]);

		$login_url = $this->getLoginUrl();
		$register_url = $this->getRegisterUrl();

		//Only show the buttons if ILIAS allows to create new registrations
		if (ilRegistrationSettings::_lookupRegistrationType() != IL_REG_DISABLED)
		{
			//todo: use custom CSS following the FW entry.
			if($this->tab_option == self::MD_LOGIN_VIEW) {
				$button1 = $f->button()->standard('Login', '#')->withUnavailableAction();
				$button2 = $f->button()->standard('Registration', '#')
					->withOnClick($replaceSignal->withAsyncRenderUrl($register_url));
			} else {
				$button1 = $f->button()->standard('Login', '#')
					->withOnClick($replaceSignal->withAsyncRenderUrl($login_url));
				$button2 = $f->button()->standard('Registration', '#')->withUnavailableAction();
			}

			return array($button1, $button2);
		}

		return array($f->legacy(""));
	}


	/**
	 * Get login screen
	 */
	function login()
	{
		global $DIC;
		$f = $DIC->ui()->factory();
		$r = $DIC->ui()->renderer();
		$lng = $DIC->language();
		$ctrl = $DIC->ctrl();

		$ctrl->saveParameter($this, "replaceSignal");

		$this->setTabOption(self::MD_LOGIN_VIEW);

		include_once './Services/Authentication/classes/class.ilAuthStatus.php';
		$status = ilAuthStatus::getInstance();

		$current_status = $status->getStatus();

		$legacy_content = "";

		switch ($current_status)
		{
			case ilAuthStatus::STATUS_AUTHENTICATED:
				//todo: lang var
				$auth_result = array(
					"status" => "ok",
					"html" => "welcome_back"
				);
				echo json_encode($auth_result);
				exit;

			case ilAuthStatus::STATUS_AUTHENTICATION_FAILED:
				//todo remove inline css and use the ilias sendFailure css
				$css = "background-color:red; color:white; margin:10px 0; padding:10px;";
				$legacy_content = $r->render($this->getNavigation());
				$legacy_content .= "<div style='" . $css . "'>" . $status->getTranslatedReason() . "</div>" . $this->getLoginForm()->getHTML();
				$legacy_content .= $this->appendLoginJS($this->getLoginUrl());
				$legacy_content .= " ".$this->getPasswordAssistance();
				$auth_result = array(
					"status" => "ko",
					"html" => $legacy_content
				);
				echo json_encode($auth_result);
				exit;
		}

		if($current_status !=ilAuthStatus::STATUS_AUTHENTICATED && $legacy_content == "")
		{
			$legacy_content = $this->getLoginForm()->getHTML();
			$legacy_content .= " ".$this->getPasswordAssistance();
			$legacy_content .= $this->appendLoginJS($this->getLoginValidationUrl());
		}

		$legacy = $f->legacy($legacy_content);

		//todo: perform redirect when close button and the user is authenticated successfully
		$modal = $f->modal()->roundtrip("Login", array_merge($this->getNavigation(), array($legacy)))->withCancelButtonLabel($lng->txt('close'));
		echo $r->renderAsync([$modal]);
		exit;
	}

	/**
	 * Get register screen
	 */
	function register()
	{
		global $DIC;
		$f = $DIC->ui()->factory();
		$r = $DIC->ui()->renderer();
		$lng = $DIC->language();
		$ctrl = $DIC->ctrl();

		$ctrl->saveParameter($this, "replaceSignal");

		$this->setTabOption(self::MD_REGISTER_VIEW);

		$legacy_content = $this->initFormRegister()->getHTML();

		$legacy = $f->legacy($legacy_content);

		//todo lang var
		$modal = $f->modal()->roundtrip("Registration", array_merge($this->getNavigation(), array($legacy)));

		echo $r->renderAsync([$modal]);
		exit;
	}

	//todo: if nothing special to do, delete this method and use only initFormLogin.
	function getLoginForm()
	{
		$form = $this->initFormLogin();

		return $form;
	}

	function getRegisterForm()
	{
		$form = $this->initFormRegister();

		return $form;
	}

	function test()
	{
		die("Voila");
	}

	/**
	 * Create
	 *
	 * @param
	 * @return
	 */
	function insert()
	{
		global $tpl;

		$form = $this->initForm(true);
		$tpl->setContent($form->getHTML());
	}

	/**
	 * Save new pc example element
	 */
	public function create()
	{
		global $tpl, $lng, $ilCtrl;

		$form = $this->initForm(true);
		if ($form->checkInput())
		{
			$properties = array(
				"value_1" => $form->getInput("val1"),
				"value_2" => $form->getInput("val2")
			);
			if ($this->createElement($properties))
			{
				ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}

		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());
	}

	/**
	 * Edit
	 *
	 * @param
	 * @return
	 */
	function edit()
	{
		global $tpl;

		$this->setTabs("edit");

		$form = $this->initForm();
		$tpl->setContent($form->getHTML());
	}

	/**
	 * Update
	 *
	 * @param
	 * @return
	 */
	function update()
	{
		global $tpl, $lng, $ilCtrl;

		$form = $this->initForm(true);
		if ($form->checkInput())
		{
			$properties = array(
				"value_1" => $form->getInput("val1"),
				"value_2" => $form->getInput("val2")
			);
			if ($this->updateElement($properties))
			{
				ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}

		$form->setValuesByPost();
		$tpl->setContent($form->getHtml());

	}


	/**
	 * Init editing form
	 *
	 * @param        int        $a_mode        Edit Mode
	 */
	public function initForm($a_create = false)
	{
		global $lng, $ilCtrl;

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		// value one
		$v1 = new ilTextInputGUI($this->getPlugin()->txt("text"), "val1");
		$v1->setMaxLength(40);
		$v1->setSize(40);
		$v1->setRequired(true);
		$form->addItem($v1);

		// value two
		$v2 = new ilTextInputGUI($this->getPlugin()->txt("color"), "val2");
		$v2->setMaxLength(40);
		$v2->setSize(40);
		$form->addItem($v2);

		if (!$a_create)
		{
			$prop = $this->getProperties();
			$v1->setValue($prop["value_1"]);
			$v2->setValue($prop["value_2"]);
		}

		// save and cancel commands
		if ($a_create)
		{
			$this->addCreationButton($form);
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->getPlugin()->txt("cmd_insert"));
		}
		else
		{
			$form->addCommandButton("update", $lng->txt("save"));
			$form->addCommandButton("cancel", $lng->txt("cancel"));
			$form->setTitle($this->getPlugin()->txt("edit_ex_el"));
		}

		$form->setFormAction($ilCtrl->getFormAction($this));

		return $form;
	}

	/**
	 * Cancel
	 */
	function cancel()
	{
		$this->returnToParent();
	}

	/**
	 * Set tabs
	 *
	 * @param
	 * @return
	 */
	function setTabs($a_active)
	{
		global $ilTabs, $ilCtrl;

		$pl = $this->getPlugin();

		$ilTabs->addTab("edit", $pl->txt("settings_1"),
			$ilCtrl->getLinkTarget($this, "edit"));

		$ilTabs->addTab("edit2", $pl->txt("settings_2"),
			$ilCtrl->getLinkTarget($this, "edit2"));

		$ilTabs->activateTab($a_active);
	}

	/**
	 * More settings editing
	 *
	 * @param
	 * @return
	 */
	function edit2()
	{
		global $tpl;

		$this->setTabs("edit2");

		ilUtil::sendInfo($this->getPlugin()->txt("more_editing"));
	}


	/**
	 * @return ilPropertyFormGUI
	 */
	function initFormLogin()
	{
		global $DIC;

		$lng = $DIC->language();
		$ctrl = $DIC->ctrl();

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		$form->setFormAction($ctrl->getFormAction($this));
		$form->setName("formlogin");
		$form->setId("login_modal_plugin");
		$form->setShowTopButtons(false);

		$ti = new ilTextInputGUI($lng->txt("username"), "username");
		$ti->setSize(20);
		$ti->setRequired(true);
		$form->addItem($ti);

		$pi = new ilPasswordInputGUI($lng->txt("password"), "password");
		$pi->setUseStripSlashes(false);
		$pi->setRetype(false);
		$pi->setSkipSyntaxCheck(true);
		$pi->setSize(20);
		$pi->setDisableHtmlAutoComplete(false);
		$pi->setRequired(true);
		$form->addItem($pi);

		//async
		$form->addCommandButton("standardAuthentication", $lng->txt("log_in"));

		return $form;
	}


	/**
	 * It performs the authentication using the form values and calls the login modal again.
	 */
	function standardAuthentication()
	{
		global $DIC;
		$auth_session = $DIC['ilAuthSession'];

		$form = $this->initFormLogin();

		if($form->checkInput()) {
			include_once './Services/Authentication/classes/Frontend/class.ilAuthFrontendCredentials.php';
			$credentials = new ilAuthFrontendCredentials();
			$credentials->setUsername($form->getInput('username'));
			$credentials->setPassword($form->getInput('password'));

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

		$this->login();
	}

	/**
	 * TODO remove this: If we have the tabs we don't need this method nor getAlreadyUsingIlias
	 * @return string with link to the register
	 */
	public function getNewToIlias()
	{
		global $DIC;
		$lng = $DIC->language();
		$ctrl = $DIC->ctrl();

		if (ilRegistrationSettings::_lookupRegistrationType() != IL_REG_DISABLED)
		{
			return $lng->txt("registration").$ctrl->getLinkTargetByClass("ilaccountregistrationgui", "");
		}
	}

	public function getAlreadyUsingIlias()
	{
		//TODO nothing.
	}



	/**
	 * TODO fix the ctrl call
	 * @return string with the password assistance links
	 */
	public function getPasswordAssistance()
	{
		global $DIC;

		$il_setting = $DIC->settings();
		$lng = $DIC->language();
		$ctrl = $DIC->ctrl();
		$f = $DIC->ui()->factory();
		$r = $DIC->ui()->renderer();

		// allow password assistance? Surpress option if Authmode is not local database
		if ($il_setting->get("password_assistance"))
		{
			//todo try to do this other way like "jumpToRegistration"
			$link_pass = $f->link()->standard($lng->txt("forgot_password"),$ctrl->getLinkTargetByClass("ilpasswordassistancegui", ""));
			$link_name = $f->link()->standard($lng->txt("forgot_username"),$ctrl->getLinkTargetByClass("ilpasswordassistancegui", "showUsernameAssistanceForm"));
			return $r->render($link_pass)." ".$r->render($link_name);
		}

		return "";
	}

	public function jumpToNameAssistance()
	{
		//TODO
		//change base class and do something like this.
		//$this->ctrl->setCmdClass("ilpasswordassistancegui");
		//$this->ctrl->setCmd("showUsernameAssistanceForm");
		//$this->executeCommand();
	}

	/**
	 * @return string control URL to the login modal
	 */
	protected function getLoginUrl()
	{
		global $DIC;
		$ctrl = $DIC->ctrl();
		$pl = $this->getPlugin();

		return $ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "login",
			"", true);
	}

	/**
	 * @return string control URL to the validation method.
	 */
	protected function getLoginValidationUrl()
	{
		global $DIC;
		$ctrl = $DIC->ctrl();
		$pl = $this->getPlugin();

		return $ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "standardAuthentication",
			"", true);
	}

	protected function getRegisterUrl()
	{
		global $DIC;
		$ctrl = $DIC->ctrl();
		$pl = $this->getPlugin();

		return $ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "register",
			"", true);
	}

	/**
	 * @param $a_url
	 * @return string
	 */
	public function appendLoginJS($a_url)
	{
		//todo lang var
		$js = "<script>
			$('#form_login_modal_plugin').on('submit', function(e) {
				var post_url = '".$a_url."';
				e.preventDefault();
				$.ajax({
					type: 'POST',
					url: post_url,
					data: $(this).serialize(),
					dataType: 'json',
					success: function(result) {
						$('.modal-body').html(result['html']);
						if(result['status'] === 'ok') {
						    setTimeout(function(){ location.reload() }, 1000);
						}
					 },
					error: function(result) {
						$('.modal-body').html('Something is wrong!');
					 }
				});
			});
		</script>";

		return $js;
	}

	/**
	 * This tab option is about the login, register buttons.
	 * @param $a_view
	 */
	public function setTabOption($a_view)
	{
		$this->tab_option = $a_view;
	}

	public function initFormRegister()
	{
		global $DIC;

		$lng = $DIC->language();
		$ctrl = $DIC->ctrl();
		$user = $DIC->user();

		$registration_settings = new ilRegistrationSettings();

		$code_enabled = ($registration_settings->registrationCodeRequired() ||
			$registration_settings->getAllowCodes());

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		$form->setFormAction($ctrl->getFormAction($this));
		$form->setShowTopButtons(false);

		// user defined fields
		$user_defined_data = $user->getUserDefinedData();

		include_once './Services/User/classes/class.ilUserDefinedFields.php';
		$user_defined_fields =& ilUserDefinedFields::_getInstance();
		$custom_fields = array();
		foreach($user_defined_fields->getRegistrationDefinitions() as $field_id => $definition)
		{
			if($definition['field_type'] == UDF_TYPE_TEXT)
			{
				$custom_fields["udf_".$definition['field_id']] =
					new ilTextInputGUI($definition['field_name'], "udf_".$definition['field_id']);
				$custom_fields["udf_".$definition['field_id']]->setValue($user_defined_data["f_".$field_id]);
				$custom_fields["udf_".$definition['field_id']]->setMaxLength(255);
				$custom_fields["udf_".$definition['field_id']]->setSize(40);
			}
			else if($definition['field_type'] == UDF_TYPE_WYSIWYG)
			{
				$custom_fields["udf_".$definition['field_id']] =
					new ilTextAreaInputGUI($definition['field_name'], "udf_".$definition['field_id']);
				$custom_fields["udf_".$definition['field_id']]->setValue($user_defined_data["f_".$field_id]);
				$custom_fields["udf_".$definition['field_id']]->setUseRte(true);
			}
			else
			{
				$custom_fields["udf_".$definition['field_id']] =
					new ilSelectInputGUI($definition['field_name'], "udf_".$definition['field_id']);
				$custom_fields["udf_".$definition['field_id']]->setValue($user_defined_data["f_".$field_id]);
				$custom_fields["udf_".$definition['field_id']]->setOptions(
					$user_defined_fields->fieldValuesToSelectArray($definition['field_values']));
			}
			if($definition['required'])
			{
				$custom_fields["udf_".$definition['field_id']]->setRequired(true);
			}

			if($definition['field_type'] == UDF_TYPE_SELECT && !$user_defined_data["f_".$field_id])
			{
				$options = array(""=>$lng->txt("please_select")) + $custom_fields["udf_".$definition['field_id']]->getOptions();
				$custom_fields["udf_".$definition['field_id']]->setOptions($options);
			}
		}

		// standard fields
		include_once("./Services/User/classes/class.ilUserProfile.php");
		$up = new ilUserProfile();
		$up->setMode(ilUserProfile::MODE_REGISTRATION);
		$up->skipGroup("preferences");
		$up->skipGroup("settings");

//todo try to find a way to add fields instead of remove them.
//TODO: ask if the plugin should have the same fields and configuration than the normal login in ilias configured in admin users.
		//$up->skipGroup("interests");
		//$up->skipGroup("personal_data");
		//$up->skipGroup("other");

		/*$fields_to_skip = array (
			"institution",
			"department",
			"street",
			"zipcode",
			"city",
			"country",
			"phone_office",
			"phone_home",
			"phone_mobile",
			"fax",
			"second_email"
		);
		foreach($fields_to_skip as $field)
		{
			$up->skipField($field);
		}*/

/*
 * STILL WORKING HERE!
 */
		$up->setAjaxCallback(
			$ctrl->getLinkTarget($this, 'doProfileAutoComplete', '', true)
		);

		$lng->loadLanguageModule("user");

		// add fields to form
		$up->addStandardFieldsToForm($form, NULL, $custom_fields);
		unset($custom_fields);


		// #11407
		$domains = array();
		foreach($registration_settings->getAllowedDomains() as $item)
		{
			if(trim($item))
			{
				$domains[] = $item;
			}
		}
		if(sizeof($domains))
		{
			$mail_obj = $form->getItemByPostVar('usr_email');
			$mail_obj->setInfo(sprintf($lng->txt("reg_email_domains"),
					implode(", ", $domains))."<br />".
				($code_enabled ? $lng->txt("reg_email_domains_code") : ""));
		}

		// #14272
		if($registration_settings->getRegistrationType() == IL_REG_ACTIVATION)
		{
			$mail_obj = $form->getItemByPostVar('usr_email');
			if($mail_obj) // #16087
			{
				$mail_obj->setRequired(true);
			}
		}

		require_once 'Services/TermsOfService/classes/class.ilTermsOfServiceSignableDocumentFactory.php';
		$document = ilTermsOfServiceSignableDocumentFactory::getByLanguageObject($lng);
		if(ilTermsOfServiceHelper::isEnabled() && $document->exists())
		{
			$field = new ilFormSectionHeaderGUI();
			$field->setTitle($lng->txt('usr_agreement'));
			$form->addItem($field);

			$field = new ilCustomInputGUI();
			$field->setHTML('<div id="agreement">' . $document->getContent() . '</div>');
			$form->addItem($field);

			$field = new ilCheckboxInputGUI($lng->txt('accept_usr_agreement'), 'accept_terms_of_service');
			$field->setRequired(true);
			$field->setValue(1);
			$form->addItem($field);
		}

		$form->addCommandButton("saveForm", $lng->txt("register"));

		return $form;
	}
}
