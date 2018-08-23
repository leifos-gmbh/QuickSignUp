# QuickSignUp Plugin

Plugin to add "Sign in/Sign up" button into pages opening one modalbox where the user can perform login/register filling the proper form.

The button mentioned is only displayed to non logged in users.

## Details and Configuration

Only standard users are allowed to use this method. For instance  Apache/Shibboleth ... are not allowed.

No special configuration for migrated users
    
    ilAuthStatus::STATUS_ACCOUNT_MIGRATION_REQUIRED:

No special configuration for users who have a registration code. 
`(Administration->Authentication and Registration -> (TAB)ILIAS Auth/Self-Registration
		(Radiobutton) Registration with Codes
			This type allows self-registration of users but requires a valid code.`
			
    ilAuthStatus::STATUS_CODE_ACTIVATION_REQUIRED:
			
Captcha configuration is not used.

#### User registration

Users registered via plugin are configured as follows:

- Terms of service accepted.
- Activated automatically.
- Time limit "Unlimited".
- First name = User name.
- Last name = User name.

To avoid user redirection to "Complete your Profile" after a new user registration, please don't set required fields in the user administration and
check that only First name, Last name and E-Mail are set as "Required". In ILIAS 5.3 the field "Salutation" is required by default and can be changed.

`Administration -> User Management -> Settings -> Standard Fields/ Custom Fields.
`
##Installation

- Inside the directory:

    `./Customizing/global/plugins/Services/COPage/PageComponent`
    
- Clone the plugin repository as follows:
     
      git clone https://github.com/leifos-gmbh/QuickSignUp.git

- Install and Activate it in the ILIAS Administration - Plugins.

