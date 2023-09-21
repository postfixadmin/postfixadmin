<?php


require_once('common.php');

authentication_require_role('admin');

/**
 * you need to install https://github.com/bnchdan/MailLog2MySQL
 */
$mailLog2MySQL_URL = "http://127.0.0.1:8888/api";



if (isset($_GET["table"]) ){
    if ($_GET["table"] == "auth_logs"){
        get_auth_logs();
    }
    if ($_GET["table"] == "dovecot_logs"){
        get_dovecot_logs();
    }

    if ($_GET["table"] == "postfix_logs"){
        get_postfix_logs();
    }
}


function get_domains(){
    $SESSID_USERNAME = authentication_get_username();

    if (authentication_has_role('global-admin')) {
        return list_domains();
    } 
    return list_domains_for_admin($SESSID_USERNAME);
}

/**
* if domain set, check if is admin for domain
*/
function validate_domain($var_name){
    if ( !isset($_GET[$var_name])){
        return 1;
    }
    if (!in_array($_GET[$var_name], get_domains())){
        return 0;
    }

    return 1;
    

}

function get_auth_logs(){
    if (validate_domain("domain") == 0 ){
        echo "Permission denied for domain ". $_GET["domain"];
        die();
    }
    $CONF = Config::getInstance()->getAll();
    $url = $CONF['mailLog2MySQL_URL']."?".explode("?", $_SERVER['REQUEST_URI'])[1];
    if (!isset($_GET["domain"]) && !authentication_has_role('global-admin')){
        $url.="&domain=".get_domains()[0];
    }

    $response = file_get_contents($url, false);
    if ($response == NULL){
        die("Error from MailLog2MySQL!<br>Wrong \$CONF['mailLog2MySQL_URL'] set or <a href='https://github.com/bnchdan/MailLog2MySQL'>MailLog2MySQL</a> not installed ");
    }
    echo $response;
   
    die();
}


function get_dovecot_logs(){
    if (validate_domain("domain") == 0 ){
        echo "Permission denied for domain ". $_GET["domain"];
        die();
    }
    $CONF = Config::getInstance()->getAll();
    $url = $CONF['mailLog2MySQL_URL']."?".explode("?", $_SERVER['REQUEST_URI'])[1];

    if (!isset($_GET["domain"]) && !authentication_has_role('global-admin')){
        $url.="&domain=".get_domains()[0];
    }

    $response =  file_get_contents($url, false);
    if ($response == NULL){
        die("Error from MailLog2MySQL!");
    }

    echo $response;
    die();
}


function get_postfix_logs(){
    if (validate_domain("mail_to_domain") == 0 && validate_domain("mail_from_domain") == 0 ){
        echo "Permission denied for domain ". $_GET["mail_to_domain"];
        die();
    }
    $CONF = Config::getInstance()->getAll();
    $url = $CONF['mailLog2MySQL_URL']."?".explode("?", $_SERVER['REQUEST_URI'])[1];

    if (!isset($_GET["mail_from_domain"]) && !isset($_GET["mail_to_domain"]) && !authentication_has_role('global-admin')){
        $url.="&mail_to_domain=".get_domains()[0];
    }
 
    $response = file_get_contents($url, false);
    if ($response == NULL){
        die("Error from MailLog2MySQL!");
    }

    echo $response;
    die(); 
}






  

$smarty = PFASmarty::getInstance();

$smarty->assign('smarty_template', 'maillog');
$smarty->display('index.tpl');
