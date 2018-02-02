# QuickSignUp
Plugin to add Sign in/Sign up button into pages opening one modalbox where the user can do login/register filling the form.

Only Anonnimous  

Only standard users are allowed to use this method. For instance  Apache/Shibboleth ... are not allowed.

No special configuration for migrated users
    
    ilAuthStatus::STATUS_ACCOUNT_MIGRATION_REQUIRED:

No special configuration for users who have a registration code. 
`(Administration->Authentication and Registration -> (TAB)ILIAS Auth/Self-Registration
		(Radiobutton) Registration with Codes
			This type allows self-registration of users but requires a valid code.`
			
			ilAuthStatus::STATUS_CODE_ACTIVATION_REQUIRED:
			
Captcha configuration is not used.