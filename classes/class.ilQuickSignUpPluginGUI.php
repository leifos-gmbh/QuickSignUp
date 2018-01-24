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
				//if (in_array($cmd, array("create", "save", "edit", "edit2", "update", "cancel")))
				//{
					$this->$cmd();
				//}
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
			$modal = $f->modal()->roundtrip($navigation,$f->legacy($this->getLoginForm()));
		} else {
			$modal = $f->modal()->roundtrip($navigation, "First content");
		}

		$asyncUrl = $url . '&signup=login&replaceSignal=' . $modal->getReplaceContentSignal()->getId();

		//$modal->withAsyncRenderUrl($asyncUrl);
		$modal->withAsyncContentUrl($asyncUrl);

		$button = $f->button()->standard("Login", "#")->withOnClick($modal->getShowSignal());

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

	function insert()
	{
		//Todo if necessary
	}

	public function create()
	{
		//Todo if necessary
	}

	function edit()
	{
		//Todo if necessary
	}

}
?>