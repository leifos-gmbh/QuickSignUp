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

	var $globals_init = false;
	var $ctrl;
	var $user;
	var $tpl;
	var $lng;
	var $ui_factory;
	var $ui_renderer;

	/**
	 * global vars initialization.
	 */
	function globalsInit()
	{
		global $DIC, $tpl;

		$this->ctrl = $DIC->ctrl();
		$this->user = $DIC->user();
		$this->tpl = $tpl;
		$this->lng = $DIC->language();
		$this->ui_factory = $DIC->ui()->factory();
		$this->ui_renderer = $DIC->ui()->renderer();

		$this->globals_init = true;

	}

	function executeCommand()
	{
		if(!$this->globals_init) {
			$this->globalsInit();
		}
		$next_class = $this->ctrl->getNextClass();

		switch ($next_class)
		{
			case "ilpasswordassistancegui":
				require_once("Services/Init/classes/class.ilPasswordAssistanceGUI.php");
				return $this->ctrl->forwardCommand(new ilPasswordAssistanceGUI());

			//todo add register commands in the array.
			default:
				// perform valid commands
				$cmd = $this->ctrl->getCmd();
				if (in_array($cmd, array("create", "save", "edit", "edit2", "update", "cancel", "login", "test", "register","standardAuthentication", "jumpToPasswordAssistance")))
				{
					$this->$cmd();
				}
				//todo remove this else
				else {
					die("WOOOOOOUUUPS! no method found");
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
		if(!$this->globals_init) {
			$this->globalsInit();
		}
		//If the user is not anonymous exit.
		if(!$this->user->isAnonymous()) {
			return "";
		}

		$modal = $this->ui_factory->modal()->roundtrip("Modal Title", $this->ui_factory->legacy(""));
		$this->ctrl->setParameter($this, "replaceSignal", $modal->getReplaceContentSignal()->getId());

		$modal = $modal->withAsyncRenderUrl($this->getLoginUrl());
		$button = $this->ui_factory->button()->standard("Sign In", '#')
			->withOnClick($modal->getShowSignal());
		$content = $this->ui_renderer->render([$modal, $button]);

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
		$replaceSignal = new \ILIAS\UI\Implementation\Component\Modal\ReplaceContentSignal($_GET["replaceSignal"]);

		$login_url = $this->getLoginUrl();
		$register_url = $this->getRegisterUrl();

		//Only show the buttons if ILIAS allows to create new registrations
		if (ilRegistrationSettings::_lookupRegistrationType() != IL_REG_DISABLED)
		{
			//todo: use custom CSS following the FW entry.
			if($this->tab_option == self::MD_LOGIN_VIEW) {
				$button1 = $this->ui_factory->button()->standard('Login', '#')->withUnavailableAction();
				$button2 = $this->ui_factory->button()->standard('Registration', '#')
					->withOnClick($replaceSignal->withAsyncRenderUrl($register_url));
			} else {
				$button1 = $this->ui_factory->button()->standard('Login', '#')
					->withOnClick($replaceSignal->withAsyncRenderUrl($login_url));
				$button2 = $this->ui_factory->button()->standard('Registration', '#')->withUnavailableAction();
			}

			return array($button1, $button2);
		}

		return array($this->ui_factory->legacy(""));
	}


	/**
	 * Get login screen
	 */
	function login()
	{
		$this->ctrl->saveParameter($this, "replaceSignal");

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
				$legacy_content = $this->ui_renderer->render($this->getNavigation());
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

		$legacy = $this->ui_factory->legacy($legacy_content);

		//todo: perform redirect when close button and the user is authenticated successfully
		$modal = $this->ui_factory->modal()->roundtrip("Login", array_merge($this->getNavigation(), array($legacy)))->withCancelButtonLabel($this->lng->txt('close'));
		echo $this->ui_renderer->renderAsync([$modal]);
		exit;
	}

	/**
	 * Get register screen
	 */
	function register()
	{
		$this->ctrl->saveParameter($this, "replaceSignal");

		$this->setTabOption(self::MD_REGISTER_VIEW);

		$legacy_content = $this->initFormRegister()->getHTML();

		$legacy = $this->ui_factory->legacy($legacy_content);

		//todo lang var
		$modal = $this->ui_factory->modal()->roundtrip("Registration", array_merge($this->getNavigation(), array($legacy)));

		echo $this->ui_renderer->renderAsync([$modal]);
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

	/**
	 * Create
	 *
	 * @param
	 * @return
	 */
	function insert()
	{
		$form = $this->initForm(true);
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * Save new pc example element
	 */
	public function create()
	{
		$form = $this->initForm(true);
		if ($form->checkInput())
		{
			$properties = array(
				"value_1" => $form->getInput("val1"),
				"value_2" => $form->getInput("val2")
			);
			if ($this->createElement($properties))
			{
				ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}

		$form->setValuesByPost();
		$this->tpl->setContent($form->getHtml());
	}

	/**
	 * Edit
	 *
	 * @param
	 * @return
	 */
	function edit()
	{
		$this->setTabs("edit");

		$form = $this->initForm();
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * Update
	 *
	 * @param
	 * @return
	 */
	function update()
	{
		$form = $this->initForm(true);
		if ($form->checkInput())
		{
			$properties = array(
				"value_1" => $form->getInput("val1"),
				"value_2" => $form->getInput("val2")
			);
			if ($this->updateElement($properties))
			{
				ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
				$this->returnToParent();
			}
		}

		$form->setValuesByPost();
		$this->tpl->setContent($form->getHtml());

	}


	/**
	 * Init editing form
	 *
	 * @param        int        $a_mode        Edit Mode
	 */
	public function initForm($a_create = false)
	{
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
			$form->addCommandButton("cancel", $this->lng->txt("cancel"));
			$form->setTitle($this->getPlugin()->txt("cmd_insert"));
		}
		else
		{
			$form->addCommandButton("update", $this->lng->txt("save"));
			$form->addCommandButton("cancel", $this->lng->txt("cancel"));
			$form->setTitle($this->getPlugin()->txt("edit_ex_el"));
		}

		$form->setFormAction($this->ctrl->getFormAction($this));

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
		global $ilTabs;

		$pl = $this->getPlugin();

		$ilTabs->addTab("edit", $pl->txt("settings_1"),
			$this->ctrl->getLinkTarget($this, "edit"));

		$ilTabs->addTab("edit2", $pl->txt("settings_2"),
			$this->ctrl->getLinkTarget($this, "edit2"));

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
		$this->setTabs("edit2");

		ilUtil::sendInfo($this->getPlugin()->txt("more_editing"));
	}


	/**
	 * @return ilPropertyFormGUI
	 */
	function initFormLogin()
	{
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setName("formlogin");
		$form->setId("login_modal_plugin");
		$form->setShowTopButtons(false);

		$ti = new ilTextInputGUI($this->lng->txt("username"), "username");
		$ti->setSize(20);
		$ti->setRequired(true);
		$form->addItem($ti);

		$pi = new ilPasswordInputGUI($this->lng->txt("password"), "password");
		$pi->setUseStripSlashes(false);
		$pi->setRetype(false);
		$pi->setSkipSyntaxCheck(true);
		$pi->setSize(20);
		$pi->setDisableHtmlAutoComplete(false);
		$pi->setRequired(true);
		$form->addItem($pi);

		//async
		$form->addCommandButton("standardAuthentication", $this->lng->txt("log_in"));

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
		if (ilRegistrationSettings::_lookupRegistrationType() != IL_REG_DISABLED)
		{
			return $this->lng->txt("registration").$this->ctrl->getLinkTargetByClass("ilaccountregistrationgui", "");
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

		// allow password assistance? Surpress option if Authmode is not local database
		if ($il_setting->get("password_assistance"))
		{
			//todo try to do this other way like "jumpToRegistration"
			//$link_pass = $this->ui_factory->link()->standard($this->lng->txt("forgot_password"),$this->ctrl->getLinkTargetByClass("ilpasswordassistancegui", ""));
			$link_pass = $this->ui_factory->link()->standard($this->lng->txt("forgot_password"),$this->ctrl->getLinkTarget($this, "jumpToPasswordAssistance"));
			$link_name = $this->ui_factory->link()->standard($this->lng->txt("forgot_username"),$this->ctrl->getLinkTargetByClass("ilpasswordassistancegui", "showUsernameAssistanceForm"));
			return $this->ui_renderer->render($link_pass)." ".$this->ui_renderer->render($link_name);
		}

		return "";
	}

	public function jumpToPasswordAssistance()
	{

		//global $ilCtrl;

		//$ilCtrl->setCmdClass(" ilpasswordassistancegui");
		//$this->ctrl->setCmdClass("ilpasswordassistancegui");

		//ilLoggerFactory::getRootLogger()->debug("next = ".$ilCtrl->getNextClass());
		//$this->ctrl->initBaseClass("ilStartUpGUI");
		//$this->ctrl->
		//$this->executeCommand();
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
		$pl = $this->getPlugin();

		return $this->ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "login",
			"", true);
	}

	/**
	 * @return string control URL to the validation method.
	 */
	protected function getLoginValidationUrl()
	{
		$pl = $this->getPlugin();

		return $this->ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "standardAuthentication",
			"", true);
	}

	protected function getRegisterUrl()
	{
		$pl = $this->getPlugin();

		return $this->ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "register",
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
		$registration_settings = new ilRegistrationSettings();

		$code_enabled = ($registration_settings->registrationCodeRequired() ||
			$registration_settings->getAllowCodes());

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setShowTopButtons(false);

		// user defined fields
		$user_defined_data = $this->user->getUserDefinedData();

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
				$options = array(""=>$this->lng->txt("please_select")) + $custom_fields["udf_".$definition['field_id']]->getOptions();
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
			$this->ctrl->getLinkTarget($this, 'doProfileAutoComplete', '', true)
		);

		$this->lng->loadLanguageModule("user");

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
			$mail_obj->setInfo(sprintf($this->lng->txt("reg_email_domains"),
					implode(", ", $domains))."<br />".
				($code_enabled ? $this->lng->txt("reg_email_domains_code") : ""));
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
		$document = ilTermsOfServiceSignableDocumentFactory::getByLanguageObject($this->lng);
		if(ilTermsOfServiceHelper::isEnabled() && $document->exists())
		{
			$field = new ilFormSectionHeaderGUI();
			$field->setTitle($this->lng->txt('usr_agreement'));
			$form->addItem($field);

			$field = new ilCustomInputGUI();
			$field->setHTML('<div id="agreement">' . $document->getContent() . '</div>');
			$form->addItem($field);

			$field = new ilCheckboxInputGUI($this->lng->txt('accept_usr_agreement'), 'accept_terms_of_service');
			$field->setRequired(true);
			$field->setValue(1);
			$form->addItem($field);
		}

		$form->addCommandButton("saveForm", $this->lng->txt("register"));

		return $form;
	}
}
