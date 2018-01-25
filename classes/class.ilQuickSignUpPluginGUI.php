<?php
include_once("./Services/COPage/classes/class.ilPageComponentPluginGUI.php");

/**
 * Quick login/register modalbox user interface
 *
 *
 * DEV NOTES
 * 1. There is no description of which button type should be used. I'm using the standard without customization.
 * 2. viewcontrol "mode" was my first thinking.
 *
 * @author Jesús López <lopez@leifos.com>
 * @version $Id$
 * @ilCtrl_isCalledBy ilQuickSignUpPluginGUI: ilPCPluggedGUI
 */
class ilQuickSignUpPluginGUI extends ilPageComponentPluginGUI
{

	protected $form_displayed = "login";

	function executeCommand()
	{
		global $ilCtrl;

		$next_class = $ilCtrl->getNextClass();

		switch($next_class)
		{
			default:
				// perform valid commands
				$cmd = $ilCtrl->getCmd();
				if (in_array($cmd, array("create", "save", "edit", "edit2", "update", "cancel")))
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
		$f = $DIC->ui()->factory();
		$r = $DIC->ui()->renderer();
		$ctrl = $DIC->ctrl();
		$user = $DIC->user();
		$url = $_SERVER['REQUEST_URI'];

		//validation TODO when finish: uncomment this validation and add more if needed. We want to show this button only for non logged users.
		//if($user->id) {
		//	return "";
		//}

		//template
		$pl = $this->getPlugin();
		$tpl = $pl->getTemplate("tpl.content.html");

		if(isset($_GET['signup']))
		{
			$signalId = $_GET['replaceSignal'];

			$replaceSignal = new \ILIAS\UI\Implementation\Component\Modal\ReplaceContentSignal($signalId);

			//Working here.
			$login_btn = $f->button()->standard('Login', '#')
				->withOnClick($replaceSignal->withAsyncRenderUrl($url . '&signup=login&replaceSignal=' . $signalId));

			$register_btn = $f->button()->standard("register", "#")
				->withOnClick($replaceSignal->withAsyncRenderUrl($url . '&signup=register&replaceSignal=' . $signalId));

			ilLoggerFactory::getRootLogger()->debug("* ISSET--> ".$_GET['signup']);
			if($_GET['signup'] == "login")
			{
				ilLoggerFactory::getRootLogger()->debug("***** LOGIN!");

			} else {
				ilLoggerFactory::getRootLogger()->debug("***** REGISTER!");
			}
		}
		else
		{
			ilLoggerFactory::getRootLogger()->debug("NO GET signup");
			//button example
			$login_btn = $f->button()->standard("Login", "");
			$register_btn = $f->button()->standard("register", "");
		}

		$navigation = $r->render($login_btn);
		$navigation .= $r->render($register_btn);

		if(!isset($_GET['signup'])) {
			ilLoggerFactory::getRootLogger()->debug("no signup");
			$modal = $f->modal()->roundtrip($navigation,$f->legacy($this->getLoginForm()));
		} else {

			ilLoggerFactory::getRootLogger()->debug("else");

			$signalId = $_GET['replaceSignal'];

			$replaceSignal = new \ILIAS\UI\Implementation\Component\Modal\ReplaceContentSignal($signalId);

			$modal = $f->modal()->roundtrip($navigation,$f->legacy($this->getRegisterForm()));

			echo $r->render($modal);
			exit;
		}

		$asyncUrl = $url . '&signup=login&replaceSignal=' . $modal->getReplaceContentSignal()->getId();

		//$modal->withAsyncRenderUrl($asyncUrl);
		//$modal = $modal->withAsyncContentUrl($asyncUrl);
		$modal = $modal->withAsyncRenderUrl($asyncUrl);

		//We can get the first value of the form to get the label doing something like this:
		$button = $f->button()->standard($a_properties['value_1'], "#")->withOnClick($modal->getShowSignal());
		//$button = $f->button()->standard($a_properties['value_1'], "#")->withOnClick($modal->getShowSignal());

		ilLoggerFactory::getRootLogger()->debug("***modal ASYNC URL => ".$asyncUrl);

		$comps = [$button, $modal];

		$content = $r->render($comps);

		$tpl->setVariable("CONTENT", $content);

		return $tpl->get();
	}

	function getLoginForm()
	{
		return "login Form";
	}

	function getRegisterForm()
	{
		return "Register";
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

}
?>