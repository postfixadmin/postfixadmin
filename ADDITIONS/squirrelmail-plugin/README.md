# Squirrelmail Plugin Postfixadmin 

The Postfixadmin SquirrelMail plugin let users change their virtual alias,
vacation status/message and password 

Your users can therefore use this plugin within Squirrelmail to :

 * Turn vacation on/off
 * Change their email password
 * Setup forwarding rules


Note, this plugin does not require access to the Postfixadmin database. It communicates with Postfixadmin using the XMLRPC protocol. 

## Notes

 * We now depend upon the Zend Framework (preferably v1.9.x at the time of writing) (Download from http://framework.zend.com/download/latest - the minimal one should be sufficient)
 * Traffic to the XmlRpc interface needs encrypting (e.g. https) - this is something _you_ need to do
 * When trying to use the plugin, the user will be prompted to enter their mailbox password - this is necessary to authenticate with the remote XmlRpc? interface 


## REQUIREMENTS 

 * SquirrelMail 1.4x
 * PostfixAdmin version 3 or higher. 
 * PHP 5.4+ with XMLRPC support





