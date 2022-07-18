# Some screenshots of Postfixadmin 

## 1. Setup process

When you visit visit https://your-site.com/postfixadmin/setup.php you'll see this -

![Initial setup greeting page](setup-step1.png?raw=true "Initial setup load")

After creating and adding the setup password hash into your config file, and then logging into the setup page with that password, you should see :

![Setup after auth](setup-step2.png?raw=true "Setup after auth")

If there are any hosting errors, or issues with your environment, they may be listed here. 

Create a new admin account using your setup password .... then you can login as an admin and start creating domains and mailboxes.

## 2. As an Admin user

### Login

![Admin Login](admin-login.png?raw=true "Admin Login")

### Welcome page

![Admin Welcome](admin-welcome.png?raw=true "Admin welcome")

### View other admins

![Admin list](admin-list.png?raw=true "Admin list")


### View mailboxes and aliases for domain

![Virtual overview](mailboxes-and-forwards-for-domain.png?raw=true "Viewing aliases and mailboxes for a domain")

### Add mailbox

You can create as many mailboxes as you want ... 

![Mailbox adding](mailbox-adding.png?raw=true "Creating a new mailbox")


### Add aliases (forwards)

![Foward adding](create-new-alias.png?raw=true "Creating a new forward")

### Add Fetchmail config for mailbox

![Setup Fetchmail](fetchmail-new-config.png?raw=true "Fetchmail settings")

### Add a Domain Key for use with OpenDKIM

![Add Domain Key](dkim-add-domain-key.png?raw=true "Fetchmail settings")

### Add a Sign Table Entry for use with OpenDKIM

![Add Sign Table Entry](dkim-add-sign-table-entry.png?raw=true "Fetchmail settings")


## 3. As a User

### Login

![User loginl](users-login.png?raw=true "User login")

### Welcome page


![User welcome](users-welcome.png?raw=true "User welcome")

### Change your mail forward

![User - edit mail forward(s)](users-edit-mail-forward.png?raw=true "User mail forwards")

### Set / Unset autoresponse (Vacation)

![User - autoresponder](users-enable-vacation-autoresponse.png?raw=true "User setup autoresponder")

### I forgot my password


![User - forgot password](users-forgotten-password.png?raw=true "User forgot password")
