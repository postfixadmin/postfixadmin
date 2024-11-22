# Some screenshots of Postfixadmin 

## 1. Setup process

When you visit visit https://your-site.com/postfixadmin/setup.php you'll see this -

![Initial setup greeting page](setup-step1.png "Initial setup load")

After creating and adding the setup password hash into your config file, and then logging into the setup page with that password, you should see :

![Setup after auth](setup-step2.png "Setup after auth")

If there are any hosting errors, or issues with your environment, they may be listed here. 

Create a new admin account using your setup password .... then you can login as an admin and start creating domains and mailboxes.

## 2. As an Admin user

### Login

![Admin Login](admin-login.png "Admin Login")

### Welcome page

![Admin Welcome](admin-welcome.png "Admin welcome")

### View other admins

![Admin list](admin-list.png "Admin list")

### View domains

![Domain list](admin-domain-list.png "Domain list")


### View mailboxes and aliases for domain

![Virtual overview](mailboxes-and-forwards-for-domain.png "Viewing aliases and mailboxes for a domain")

### Add mailbox

You can create as many mailboxes as you want ... 

![Mailbox adding](mailbox-adding.png "Creating a new mailbox")


### Add aliases (forwards)

![Foward adding](create-new-alias.png "Creating a new forward")

### Add Fetchmail config for mailbox

![Setup Fetchmail](fetchmail-new-config.png "Fetchmail settings")

### Add a Domain Key for use with OpenDKIM

Specify the selector, domain it's for and the public and private keys (PEM encoded).

![Add Domain Key](dkim-add-domain-key.png "DKIM - add domain")

### Add a Sign Table Entry for use with OpenDKIM

![Add Sign Table Entry](dkim-add-sign-table-entry.png "DKIM - add signing table entry")


## View Log -&gt;  Entries for domain

PostfixAdmin mains a log of changes that have been applied to a domain's configuration -

![View Log - Example.com](admin-viewlog-domain.png "View Log for domain")

## Send Email -&gt; Send Broadcast email

You can email all mailboxes in known domain(s) -

![Send email - broadcast to users](admin-broadcast-email.png "Broadcast email")


## 3. As a User

### Login

![User loginl](users-login.png "User login")

### Welcome page


![User welcome](users-welcome.png "User welcome")

### Change your mail forward

![User - edit mail forward(s)](users-edit-mail-forward.png "User mail forwards")

### Set / Unset autoresponse (Vacation)

![User - autoresponder](users-enable-vacation-autoresponse.png "User setup autoresponder")


### Set / Unset TOTP Password

To help protect your postfixadmin account, you can enable TOTP (aka Google Authenticator etc) to make it harder for a third party to compromise your account.

![User - setup TOTP password](users-enable-totp.png "User enable TOtP")

### I forgot my password


![User - forgot password](users-forgotten-password.png "User forgot password")
