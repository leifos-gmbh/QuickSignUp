<?php
include_once("./Services/COPage/classes/class.ilPageComponentPluginGUI.php");

/**
 * Quick login/register modalbox user interface
 * DEV NOTES
 * 1. There is no description of which button type should be used. I'm using the standard without customization.
 * 2. viewcontrol "mode" was my first thinking.
 * In the registration form we are not showing the available domains if limited. But we are
 * taking care about it when validate the form.
 *
 * @author Jesús López <lopez@leifos.com>
 * @version $Id$
 * @ilCtrl_isCalledBy ilQuickSignUpPluginGUI: ilPCPluggedGUI
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

	/**
	 * @var ilCtrl
	 */
	var $ctrl;

	/**
	 * @var ilObjUser
	 */
	var $user;

	/**
	 * @var ilTemplate
	 */
	var $tpl;

	/**
	 * @var ilLanguage
	 */
	var $lng;

	/**
	 * @var \ILIAS\UI\Factory
	 */
	var $ui_factory;

	/**
	 * @var \ILIAS\UI\Renderer
	 */
	var $ui_renderer;

	/**
	 * @var ilSetting
	 */
	var $settings;

	/**
	 * @var string
	 */
	var $form_login_id = "login_modal_plugin";

	/**
	 * @var string
	 */
	var $form_register_id = "register_modal_plugin";

	/**
	 * global vars initialization.
	 */
	function initialization()
	{
		global $DIC, $tpl;

		$this->ctrl = $DIC->ctrl();
		$this->user = $DIC->user();
		$this->tpl = $tpl;
		$this->lng = $DIC->language();
		$this->ui_factory = $DIC->ui()->factory();
		$this->ui_renderer = $DIC->ui()->renderer();
		$this->settings = $DIC->settings();
		$this->globals_init = true;

		$this->tpl->addCss("./Customizing/global/plugins/Services/COPage/PageComponent/QuickSignUp/templates/custom.css");

	}

	/**
	 * @return mixed
	 */
	function executeCommand()
	{
		if(!$this->globals_init) {
			$this->initialization();
		}
		$next_class = $this->ctrl->getNextClass();

		switch ($next_class)
		{
			case "ilpasswordassistancegui":
				require_once("Services/Init/classes/class.ilPasswordAssistanceGUI.php");
				return $this->ctrl->forwardCommand(new ilPasswordAssistanceGUI());

			default:
				// perform valid commands
				$cmd = $this->ctrl->getCmd();
				if (in_array($cmd, array("create", "save", "edit", "update", "cancel", "loginView", "register","standardAuthentication", "jumpToPasswordAssistance", "jumpToNameAssistance", "showTermsOfService", "saveRegistration")))
				{
					$this->$cmd();
				}
				else {
					die($this->getPlugin()->txt("something_wrong"));
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
			$this->initialization();
		}

		//If the user is not anonymous exit.
		if(!$this->user->isAnonymous() && $a_mode != IL_PAGE_EDIT) {
			return " ";
		}
		else if($a_mode == IL_PAGE_EDIT)
		{
			$button = $this->ui_factory->button()->standard($this->getPlugin()->txt("sign_in"), '#');
			$content = $this->ui_renderer->render([$button]);
		}
		else
		{
			$modal = $this->ui_factory->modal()->roundtrip("Modal Title", $this->ui_factory->legacy(""));
			$this->ctrl->setParameter($this, "replaceSignal", $modal->getReplaceContentSignal()->getId());

			$modal = $modal->withAsyncRenderUrl($this->getLoginUrl());
			$button = $this->ui_factory->button()->standard($this->getPlugin()->txt("sign_in"), '#')
				->withOnClick($modal->getShowSignal());
			$content = $this->ui_renderer->render([$modal, $button]);
		}

		return $content;
	}

	/**
	 * get navigation
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
			if($this->tab_option == self::MD_LOGIN_VIEW) {
				$button1 = $this->ui_factory->button()->shy($this->getPlugin()->txt("login"), '#')->withUnavailableAction();
				$button2 = $this->ui_factory->button()->shy($this->getPlugin()->txt("registration"), '#')
					->withOnClick($replaceSignal->withAsyncRenderUrl($register_url));
			} else {
				$button1 = $this->ui_factory->button()->shy($this->getPlugin()->txt("login"), '#')
					->withOnClick($replaceSignal->withAsyncRenderUrl($login_url));
				$button2 = $this->ui_factory->button()->shy($this->getPlugin()->txt("registration"), '#')->withUnavailableAction();
			}

			$html_nav = "<div id='il_qsu_plugin_navigation' class='row'><div class='col-sm-6'>".
				$this->ui_renderer->render($button1)."</div><div class='col-sm-6'>".
				$this->ui_renderer->render($button2)."</div>".
				"</div>";
			return $this->ui_renderer->render($this->ui_factory->legacy($html_nav));
		}

		return array($this->ui_factory->legacy(""));
	}

	/**
	 * Get login screen
	 */
	function loginView()
	{
		$this->ctrl->saveParameter($this, "replaceSignal");

		$this->setTabOption(self::MD_LOGIN_VIEW);

		include_once './Services/Authentication/classes/class.ilAuthStatus.php';
		$status = ilAuthStatus::getInstance();

		$current_status = $status->getStatus();

		$legacy_content = "";
		$form_id = "form_".$this->form_login_id;

		switch ($current_status)
		{
			case ilAuthStatus::STATUS_AUTHENTICATED:
				$auth_result = array(
					"status" => "ok",
					"html" => "ok"
				);
				echo json_encode($auth_result);
				exit;

			case ilAuthStatus::STATUS_AUTHENTICATION_FAILED:
				$legacy_content .= "<div class='error_message'>" . $status->getTranslatedReason() . "</div>";
				$legacy_content .= $this->getLoginForm()->getHTML();
				$legacy_content .= " ".$this->getPasswordAssistance();

				$legacy_content .= $this->appendJS($this->getLoginValidationUrl(), $form_id);
				//$embed_content = $this->embedTheContent($legacy_content);

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
			$legacy_content .= $this->appendJS($this->getLoginValidationUrl(), $form_id);
		}

		$modal_navigation = $this->getNavigation();
		$embed_content = $this->embedTheContent($legacy_content);

		// Build a submit button (action button) for the modal footer
		$submit = $this->getSubmitButton($form_id, 'submit');

		$modal = $this->ui_factory->modal()->roundtrip($this->getPlugin()->txt("login"), $this->ui_factory->legacy($modal_navigation.$embed_content))->withCancelButtonLabel('close')->withActionButtons([$submit]);
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

		include_once './Services/Authentication/classes/class.ilAuthStatus.php';
		$status = ilAuthStatus::getInstance();

		$current_status = $status->getStatus();
		$legacy_content = "";
		$form_id = "form_".$this->form_register_id;

		switch($current_status)
		{
			case ilAuthStatus::STATUS_AUTHENTICATION_FAILED:
				$legacy_content .= "<div class='error_message'>" . $status->getTranslatedReason() . "</div>" . $this->getRegisterForm()->getHTML();
				$legacy_content .= $this->appendJS($this->getRegisterUrl(), $form_id);
				$legacy_content .= " ".$this->getPasswordAssistance();
				$auth_result = array(
					"status" => "ko",
					"html" => $legacy_content
				);
				echo json_encode($auth_result);
				exit;
		}


		//get default form.
		if($current_status !=ilAuthStatus::STATUS_AUTHENTICATED && $legacy_content == "")
		{
			$legacy_content = $this->getRegisterForm()->getHTML();
			$legacy_content .= $this->appendJS($this->getRegisterValidationURL(), $form_id);
		}

		$modal_navigation = $this->getNavigation();
		$modal_content = $legacy_content;
		$modal_content .= $this->getTermsOfService();
		$embed_content = $this->embedTheContent($modal_content);

		$submit = $this->getSubmitButton($form_id, 'register');

		$modal = $this->ui_factory->modal()->roundtrip($this->lng->txt('registration'), $this->ui_factory->legacy($modal_navigation.$embed_content))->withCancelButtonLabel('close')->withActionButtons([$submit]);

		echo $this->ui_renderer->renderAsync([$modal]);
		exit;
	}

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
				"value_1" => $form->getInput("val1")//,
				//"value_2" => $form->getInput("val2")
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
		if(!$this->globals_init) {
			$this->initialization();
		}
		$this->tpl->setContent($this->getPlugin()->txt("element_not_editable"));

		$this->setTabs("edit");

		//$form = $this->initForm();
		//$this->tpl->setContent($form->getHTML());
	}

	/**
	 * Update
	 * Dummy method, this plugin is not editable so far.
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
	 * @param bool $a_create
	 * @return ilPropertyFormGUI
	 */
	public function initForm($a_create = false)
	{
		if(!$this->globals_init) {
			$this->initialization();
		}

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		$v1 = new ilCheckboxInputGUI($this->getPlugin()->txt("insert_login_button"), "val1");
		$v1->setChecked(true);
		$v1->setDisabled(true);
		$form->addCustomProperty($this->getPlugin()->txt("insert_login_button"), "yes");
		$form->addItem($v1);

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
	 * @param string $a_active
	 */
	function setTabs($a_active)
	{
		global $ilTabs;

		if(!$this->globals_init) {
			$this->initialization();
		}

		$ilTabs->activateTab($a_active);
	}

	/**
	 * @return ilPropertyFormGUI
	 */
	function initFormLogin()
	{
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();

		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setId($this->form_login_id);
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

		return $form;
	}

	/**
	 * It performs the authentication using the form values and calls the login modal again.
	 */
	function standardAuthentication()
	{
		$form = $this->initFormLogin();

		if($form->checkInput()) {
			$this->getPlugin()->login($form->getInput("username"), $form->getInput('password'));
		}

		$this->loginView();
	}

	/**
	 * @return string HTML with the text + link to Terms and Conditions
	 */
	public function getTermsOfService()
	{
		//redirect the user to the terms and conditions.
		require_once './Services/TermsOfService/classes/class.ilTermsOfServiceSignableDocumentFactory.php';
		$document = ilTermsOfServiceSignableDocumentFactory::getByLanguageObject($this->lng);

		if(ilTermsOfServiceHelper::isEnabled() && $document->exists())
		{
			$link = $this->ui_factory->link()->standard($this->lng->txt("usr_agreement"), $this->ctrl->getLinkTarget($this, "showTermsOfService"))->withOpenInNewViewport(true);
			$terms_text = "<div id='terms_qsu_plugin'>".sprintf($this->getPlugin()->txt("creating_accept_terms"), $this->ui_renderer->render($link))."</div>";

			return $terms_text;
		}
	}

	/**
	 * @return string with the password assistance links
	 */
	public function getPasswordAssistance()
	{
		if ($this->settings->get("password_assistance"))
		{
			//new rule in the button component. Buttons are not allowed to perform navigation changes. (JF 26.2.18)
			$link_pass = $this->ui_factory->link()->standard($this->lng->txt("forgot_password"), $this->ctrl->getLinkTargetByClass(array("ilstartupgui", "ilpasswordassistancegui")));
			$link_name = $this->ui_factory->link()->standard($this->lng->txt("forgot_username"), $this->ctrl->getLinkTargetByClass(array("ilstartupgui", "ilpasswordassistancegui"),"showUsernameAssistanceForm"));

			return $this->ui_renderer->render($link_pass)."&nbsp;&nbsp;".$this->ui_renderer->render($link_name);
		}

		return "";
	}

	/**
	 * Ctrl link to the login form
	 * @return string
	 */
	protected function getLoginUrl()
	{
		$pl = $this->getPlugin();
		return $this->ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "loginView",
			"", true);
	}

	/**
	 * Ctrl link to the login validation.
	 * @return string
	 */
	protected function getLoginValidationUrl()
	{
		$pl = $this->getPlugin();
		return $this->ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "standardAuthentication",
			"", true);
	}

	/**
	 * Ctrl link to register form
	 * @return string
	 */
	protected function getRegisterUrl()
	{
		$pl = $this->getPlugin();
		return $this->ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "register",
			"", true);
	}

	/**
	 * Ctrl link to user register validation.
	 * @return string
	 */
	protected function getRegisterValidationURL()
	{
		$pl = $this->getPlugin();
		return $this->ctrl->getLinkTargetByClass(array($pl->getPageGUIClass(), "ilpcpluggedgui", "ilquicksignupplugingui"), "saveRegistration",
			"", true);
	}

	/**
	 * @param $a_url string ajax url
	 * @param $a_form_id string form id
	 * @return string
	 */
	public function appendJS($a_url, $a_form_id)
	{
		$js = "<script>
			var form_id = '".$a_form_id."';
			/*alert('append with form id => ' + form_id);*/
			$('#'+form_id).on('submit', function(e) {
				var post_url = '".$a_url."';
				e.preventDefault();
				$.ajax({
					type: 'POST',
					url: post_url,
					data: $(this).serialize(),
					dataType: 'json',
					success: function(result) {
						if(result['status'] === 'ok') {
						    /*setTimeout(function(){ location.reload() }, 1000);*/
						    location.reload();
						} else {
						    $('#quick_sign_up_modal_content').html(result['html']);
						}
					 },
					error: function(result) {
					    /*todo nothing*/
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

	/**
	 * Registration form.
	 * @return ilPropertyFormGUI
	 */
	public function initFormRegister()
	{
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setId($this->form_register_id);
		$form->setShowTopButtons(false);

		$ti = new ilTextInputGUI($this->lng->txt("username"), "username");
		$ti->setSize(20);
		$ti->setRequired(true);
		$ti->setMaxLength(30);
		$form->addItem($ti);

		$ti = new ilTextInputGUI($this->lng->txt("firstname"), "usr_firstname");
		$ti->setSize(20);
		$ti->setRequired(true);
		$ti->setMaxLength(30);
		$form->addItem($ti);

		$ti = new ilTextInputGUI($this->lng->txt("lastname"), "usr_lastname");
		$ti->setSize(20);
		$ti->setRequired(true);
		$ti->setMaxLength(30);
		$form->addItem($ti);

		$ti = new ilEMailInputGUI($this->lng->txt("email"), "usr_email");
		$ti->setSize(50);
		$ti->setRequired(true);
		$ti->setMaxLength(100);
		$form->addItem($ti);

		$pi = new ilPasswordInputGUI($this->lng->txt("password"), "usr_password");
		$pi->setUseStripSlashes(false);
		$pi->setRetype(true);
		$pi->setSkipSyntaxCheck(true);
		$pi->setSize(20);
		$pi->setDisableHtmlAutoComplete(false);
		$pi->setRequired(true);
		$form->addItem($pi);

		return $form;
	}

	/**
	 * Save the registration
	 */
	function saveRegistration()
	{
		//need this for the email domains.
		$registration_settings = new ilRegistrationSettings();

		$form = $this->getRegisterForm();

		$form_valid = $form->checkInput();

		// validate email against restricted domains
		$email = $form->getInput("usr_email");
		if($email)
		{
			// #10366
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
				$mail_valid = false;
				foreach($domains as $domain)
				{
					$domain = str_replace("*", "~~~", $domain);
					$domain = preg_quote($domain);
					$domain = str_replace("~~~", ".+", $domain);
					if(preg_match("/^".$domain."$/", $email, $hit))
					{
						$mail_valid = true;
						break;
					}
				}
				if(!$mail_valid)
				{
					$mail_obj = $form->getItemByPostVar('usr_email');
					$mail_obj->setAlert(sprintf($this->lng->txt("reg_email_domains"),
						implode(", ", $domains)));
					$form_valid = false;
				}
			}
		}

		$error_lng_var = '';
		if(
			//!$this->registration_settings->passwordGenerationEnabled() &&
			!ilUtil::isPasswordValidForUserContext($form->getInput('usr_password'), $form->getInput('username'), $error_lng_var)
		)
		{
			$passwd_obj = $form->getItemByPostVar('usr_password');
			$passwd_obj->setAlert($this->lng->txt($error_lng_var));
			$form_valid = false;
		}

		// role
		if($registration_settings->roleSelectionEnabled())
		{
			//If new users can choose the role, the first one is assigned by default. //[0] 4 User [1] 5 Guest
			$valid_role = (int)current(ilObjRole::_lookupRegisterAllowed())['id'];
		}
		else
		{
			//If the Role is assigned Automatically (administration->ILIAS auth->Automatic Role Assignment)
			$registration_role = new ilRegistrationRoleAssignments();
			$valid_role = (int)$registration_role->getDefaultRole();
		}

		//no valid role could be determined
		if (!$valid_role)
		{
			$form_valid = false;
		}

		// validate username
		$login_obj = $form->getItemByPostVar('username');
		$login = $form->getInput("username");
		if (!ilUtil::isLogin($login))
		{
			$login_obj->setAlert($this->lng->txt("login_invalid"));
			$form_valid = false;
		}
		else if (ilObjUser::_loginExists($login))
		{
			$login_obj->setAlert($this->lng->txt("login_exists"));
			$form_valid = false;
		}
		else if ((int)$this->settings->get('allow_change_loginname') &&
			(int)$this->settings->get('reuse_of_loginnames') == 0 &&
			ilObjUser::_doesLoginnameExistInHistory($login))
		{
			$login_obj->setAlert($this->lng->txt('login_exists'));
			$form_valid = false;
		}

		//resolution
		if(!$form_valid)
		{
			$form->setValuesByPost();
			$this->tab_option = self::MD_REGISTER_VIEW;
			if(!$valid_role){
				$html .= "<div id='quick_sign_up_modal_error' class='error_message'>".$this->lng->txt("registration_no_valid_role")."</div>";
			}
			$html .= $form->getHTML();
			$html .= $this->appendJS($this->getRegisterValidationURL(), "form_".$this->form_register_id);

			$auth_result = array(
				"status" => "ko",
				"html" => $html
			);
			echo json_encode($auth_result);
			exit;
		}
		else
		{
			$user_data = array(
				"username" => $form->getInput("username"),
				"email" => $form->getInput("usr_email"),
				"password" => $form->getInput("usr_password"),
				"firstname" => $form->getInput("usr_firstname"),
				"lastname" => $form->getInput("usr_lastname")
			);
			//create user
			if($this->getPlugin()->createUser($valid_role, $user_data))
			{
				//return status ok, html empty
				$auth_result = array(
					"status" => "ok",
					"html" => "ok"
				);
				echo json_encode($auth_result);
				exit;
			}
			else
			{
				$html = $this->getNavigation();
				$html .= "<div id='quick_sign_up_modal_error' class='error_message'>".$this->lng->txt("registration_can_not_register")."</div>";
				$html .= $form->getHTML();
				$auth_result = array(
					"status" => "ko",
					"html" => $html
				);
				echo json_encode($auth_result);
				exit;
			}

		}
	}



	/**
	 * Embed the modal content in a identified div
	 * @param $a_content
	 * @return string
	 */
	public function embedTheContent($a_content)
	{
		return "<div id='quick_sign_up_modal_content'>".$a_content."</div>";
	}

	/**
	 * Page with only the breadcrumb to return to the original point + terms of service information.
	 * Show terms of service
	 */
	function showTermsOfService()
	{
		global $DIC;

		$DIC->tabs()->clearTargets();
		$DIC->tabs()->clearSubTabs();

		$this->tpl->setTitleIcon("");
		$this->tpl->setUpperIcon("");
		$this->tpl->setTitle("Terms of Service");

		require_once './Services/TermsOfService/classes/class.ilTermsOfServiceSignableDocumentFactory.php';
		$document = ilTermsOfServiceSignableDocumentFactory::getByLanguageObject($this->lng);
		$html = "";
		$content = $document->getContent();
		if($content != "")
		{
			$custom_tpl = new ilTemplate("./Customizing/global/plugins/Services/COPage/PageComponent/QuickSignUp/templates/default/tpl.content.html", true, true);
			$custom_tpl->setCurrentBlock("terms");
			$custom_tpl->setVariable("CONTENT", $content);
			$custom_tpl->parseCurrentBlock();
			$html = $custom_tpl->get();
		}

		$this->tpl->setContent($html);
	}

	/**
	 * Get the submit button for the login/register with the proper javascript code.
	 * @param $a_form_id
	 * @param $a_btn_label
	 * @return \ILIAS\UI\Component\JavaScriptBindable
	 */
	function getSubmitButton($a_form_id, $a_btn_label)
	{
		return $this->ui_factory->button()->primary($this->lng->txt($a_btn_label), '#')
			->withOnLoadCode(function($id) use ($a_form_id) {
				return "
					$('#{$id}').click(function() { $('#{$a_form_id}').submit(); return false; });
					$(document).keypress(function(e) { if(e.which == 13) { $('#{$a_form_id}').submit(); return false;}});
					";
			});
	}
}
