# QuickSignUp
Plugin to add Sign in/Sign up button into pages opening one modalbox where the user can do login/register filling the form.

Only Anonymous  

Only standard users are allowed to use this method. For instance  Apache/Shibboleth ... are not allowed.

No special configuration for migrated users
    
    ilAuthStatus::STATUS_ACCOUNT_MIGRATION_REQUIRED:

No special configuration for users who have a registration code. 
`(Administration->Authentication and Registration -> (TAB)ILIAS Auth/Self-Registration
		(Radiobutton) Registration with Codes
			This type allows self-registration of users but requires a valid code.`
			
    ilAuthStatus::STATUS_CODE_ACTIVATION_REQUIRED:
			
Captcha configuration is not used.

##User registration

Users registered via plugin are configured as follows:

- Terms of service accepted.
- Activated automatically.
- Time limit "Unlimited".
- First name = User name.
- Last name = User name.

To avoid user redirection to "Complete your Profile" don't set required fields in the user administration.
Administration -> User Management -> Settings -> Standard Fields/ Custom Fields.



Users registered via plugin have time limit "Unlimited".

