<?php
/*
* to do :   - add pagination
*	          - sort by mounth
* 	        - remove old logs
*/

require_once('common.php');

authentication_require_role('admin');

$CONF = Config::getInstance()->getAll();
$smarty = PFASmarty::getInstance();

$PALANG = $CONF['__LANG'];

$SESSID_USERNAME = authentication_get_username();
if (authentication_has_role('global-admin')) {
    $list_domains = list_domains();
} else {
    $list_domains = list_domains_for_admin($SESSID_USERNAME);
}


/*
foreach ($list_domains as $domain){
	echo $domain. '<br>';
}
*/

$fDomain=$list_domains[0];

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    if (isset($_GET['fDomain'])) {
        $fDomain_aux = escape_string($_GET['fDomain']);
        $flag_fDomain = 0;
	//check if domain exists
        if ((is_array($list_domains) and sizeof($list_domains) > 0)) {
            foreach ($list_domains as $domain) {
                if ($domain == $fDomain_aux) {
                    $fDomain=$domain;
                    $flag_fDomain=1;
                    break;
                }
            }
        }
    
        if ($flag_fDomain == 0 ) {
            die('Unknown domain');
        }
	
	if (strpos($fDomain, '/') !== false) { 
                        die("Unknown path");
                }

    	//check if file exists
   
   	if (isset($_GET['get_log'])){
		// remove path from filename
		if (strpos($_GET['get_log'], '/') !== false) { 
    			die("Unknown file");
		}
		
	
		$file = '../maillog/'.$fDomain.'/'.$_GET['get_log'];
		
		if (!file_exists($file)) { die("The file does not exists") ; }
		
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.basename($file));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		ob_clean();
		flush();
		readfile($file);
    			


   	}

    }
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (isset($_POST['fDomain'])) {
        $fDomain_aux = escape_string($_POST['fDomain']);
    	$flag_fDomain = 0;
        if ((is_array($list_domains) and sizeof($list_domains) > 0)) {
            foreach ($list_domains as $domain) {
                if ($domain == $fDomain_aux) {
                    $fDomain=$domain;
                    $flag_fDomain=1;
                    break;
                }
            }
        }
    
        if ($flag_fDomain == 0 ) {
            die('Unknown domain');
        }
	
	if (strpos($fDomain, '/') !== false) { 
                        die("Unknown domain");
        }


    }
} else {
    die('Unknown request method');
}


$path = '../maillog/'.$fDomain; 
  
//check if folder exists
$log_list='';
$log_size_list=array();
if (file_exists($path)){
   //read logs from path
   $logs=scandir($path, 1);
   //remove . and ..  from result
   $log_list = array_diff( $logs,array('.', '..') );	

   //first 60 files -30 days
   $log_list=array_slice($log_list, 0, 60);
	
   $i=0;
   foreach ($log_list as $log){
	$log_size_list[$i++] = round ( filesize($path.'/'.$log)/ 1024, 3);	
   }
}



$smarty->assign("domain_list", $list_domains);
$smarty->assign('domain_selected', $fDomain);

$smarty->assign('log_list', $log_list);
$smarty->assign('log_size_list', $log_size_list);


$smarty->assign('smarty_template', 'maillog');
$smarty->display('index.tpl');




?>
