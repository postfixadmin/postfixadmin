<?php
/**
 * Postfix Admin Auto Discovery Configuration
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at http://postfixadmin.sf.net
 *
 * @version $Id$
 * @license GNU GPL v2 or later.
 *
 * File: autoconfig.php
 *
 * Allows admin to configure Autodiscovery settings for mail domain names and 
 * for Mac users or admins to generate the .mobile configuration file for Mac Mail.
 *
 * Template File: autoconfig.tpl, autoconfig-host-settings.tpl
 *
 * Template Variables:
 *
 * Form POST \ GET Variables:
 */

require_once( 'common.php' );
require_once( 'autoconfig_languages.php' );
const DEBUG = false;

authentication_require_role('admin');
$fUsername = authentication_get_username(); # enforce login
$Return_url = "list.php?table=domain";
mb_internal_encoding( 'UTF-8' );
// $smarty->error_reporting = E_ALL & ~E_NOTICE;
/*
if( authentication_has_role('admin') ) 
{
    $Admin_role = 1 ;
    $fDomain = safeget('domain');
    // $fUsername = safeget('username');
    // list(null $fDomain) = explode('@', $fUsername);
    $Return_url = "list-virtual.php?domain=" . urlencode( $fDomain );

    if( $fDomain == '' || !check_owner( authentication_get_username(), $fDomain ) ) 
    {
        die( "Invalid username!" ); # TODO: better error message
    }
} 
else 
{
    $Admin_role = 0 ;
    $Return_url = "main.php";
    authentication_require_role('user');
}
*/

// is autoconfig support enabled in $CONF ?
if( $CONF['autoconfig'] == 'NO' || !array_key_exists( 'autoconfig', $CONF ) )
{
    header( "Location: $Return_url" );
    exit( 0 );
}

date_default_timezone_set( @date_default_timezone_get() ); # Suppress date.timezone warnings

$error = 0;

$fDomain = safeget('domain');
$ah = new AutoconfigHandler( $fUsername );
$ah->debug = DEBUG;
$config_id = safeget('config_id');
if( !empty( $fDomain ) && empty( $config_id ) )
{
	$config_id = $ah->get_id_by_domain( $fDomain );
}

// if( !$config_id )
// {
// 	flash_error( $PALANG['pAutoconfig_no_config_found'] );
// 	$error = 1;
// }
$form = array();
if( count( $ah->all_domains ) == 0 ) 
{
    if( authentication_has_role( 'global-admin' ) ) 
    {
        flash_error( $PALANG['no_domains_exist'] );
    } 
    else 
    {
        flash_error( $PALANG['no_domains_for_this_admin'] );
    }
    header( "Location: list.php?table=domain" ); # no domains (for this admin at least) - redirect to domain list
    exit;
}
else
{
	$form['provider_domain_options'] = $ah->all_domains;
}

if( $_SERVER['REQUEST_METHOD'] == "GET" || empty( $_SERVER['REQUEST_METHOD'] ) ) 
{
	if( DEBUG ) error_log( "config id submitted is: '$config_id'." );
	if( !empty( $config_id ) )
	{
		if( DEBUG ) error_log( "Getting configuration details with get_details()" );
		$form = $ah->get_details( $config_id );
		if( DEBUG ) error_log( "get_details() returned: " . print_r( $form, true ) );
    }
    if( empty( $form['account_type'] ) )
    {
    	$form['account_type'] = 'imap';
    }
    if( empty( $form['ssl_enabled'] ) )
    {
    	$form['ssl_enabled'] = 1;
    }
    if( empty( $form['active'] ) )
    {
    	$form['active'] = 1;
    }
    $form['placeholder'] = array(
    	'provider_id'		=> $ah->all_domains[0],
    	'provider_name'		=> $PALANG['pAutoconfig_placeholder_provider_name'],
    );
    $form['config_options'] = $ah->get_config_ids();
    if( DEBUG ) error_log( "config_options is: " . print_r( $form['config_options'], true ) );
    // $config_id could be null
	$form['provider_domain_disabled'] = $ah->get_other_config_domains( $config_id );
	if( DEBUG ) error_log( "provider_domain_disabled is: " . print_r( $form['provider_domain_disabled'], true ) );
	// Get defaults
	if( count( $form['enable']['instruction'] ) == 0 )
	{
		$form['enable']['instruction'] = array(
			array( 'lang' => 'en', 'phrase' => '' )
		);
		if( strlen( $form['enable_status'] ) == 0 ) $form['enable_status'] = 0;
	}
	else
	{
		if( strlen( $form['enable_status'] ) == 0 ) $form['enable_status'] = 1;
	}
	if( count( $form['documentation']['description'] ) == 0 )
	{
		$form['documentation']['description'] = array(
			array( 'lang' => 'en', 'phrase' => '' )
		);
		if( strlen( $form['documentation_status'] ) == 0 ) $form['documentation_status'] = 0;
	}
	else
	{
		if( strlen( $form['documentation_status'] ) == 0 ) $form['documentation_status'] = 1;
	}
    showAutoconfigForm( $form );
    exit( 0 );
}
elseif( $_SERVER['REQUEST_METHOD'] == "POST" ) 
{
    if( safepost('token') != $_SESSION['PFA_token'] ) 
    {
        die('Invalid token!');
    }
	if( !isset( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) || 
		strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) != 'xmlhttprequest' )
	{
		if( DEBUG ) error_log( "This request is not using Ajax." );
		flash_error( "Request is not using Ajax" );
		showAutoconfigForm( $_POST );
		exit( 0 );
	}
    if( isset( $_POST['config_id'] ) && !empty( $_POST['config_id'] ) )
    {
    	if( DEBUG ) error_log( "Got config_id: " . $_POST['config_id'] );
    	if( !$ah->config_id( $_POST['config_id'] ) )
    	{
    		json_reply( array( 'error' => sprintf( $PALANG['pAutoconfig_config_id_not_found'], $_POST['config_id'] ) ) );
    		exit( 0 );
    	}
    }
    
    $handler = null;
    if( isset( $_POST['handler'] ) )
    {
    	if( preg_match( '/^[a-z][a-z_]+$/', $_POST['handler'] ) )
    	{
			$handler = $_POST['handler'];
    	}
    	else
    	{
    		if( DEBUG ) error_log( "Illegal character provided in handler \"" . $_POST['handler'] . "\"." );
    		json_reply( array( 'error' => "Bad handler provided." ) );
    		exit( 0 );
    	}
    }
    
    if( DEBUG ) error_log( "handler is \"$handler\"." );
    
	if( $handler == 'autoconfig_save' )
	{
		if( DEBUG ) error_log( "Got here saving configuration." );
		if( !( $form = $ah->save_config( $_POST ) ) )
		{
			if( DEBUG ) error_log( "Failed to save config: " . $ah->error_as_string() );
			json_reply( array( 'error' => sprintf( $PALANG['pAutoconfig_server_side_error'], $ah->error_as_string() ) ) );
		}
		else
		{
			if( DEBUG ) error_log( "Ok, config saved." );
			// We return the newly created ids so the user can perform a follow-on update
			// The Ajax script will take care of setting those values in the hidden fields
			json_reply( array(
				'success' => $PALANG['pAutoconfig_config_saved'],
				'config_id' => $form['config_id'],
				'incoming_server' => $form['incoming_server'],
				'outgoing_server' => $form['outgoing_server'],
				'instruction' => $form['enable']['instruction'],
				'documentation' => $form['documentation']['description'],
			) );
		}
	}
	elseif( $handler == 'autoconfig_remove' )
	{
		if( DEBUG ) error_log( "Got here removing configuration id " . $_POST['config_id'] );
		if( empty( $_POST['config_id'] ) )
		{
			json_reply( array( 'error' => $PALANG['pAutoconfig_no_config_yet_to_remove'] ) );
			exit( 0 );
		}
		if( !$ah->remove_config( $_POST['config_id'] ) )
		{
			if( DEBUG ) error_log( "Failed to remove config: " . $ah->error_as_string() );
			json_reply( array( 'error' => sprintf( $PALANG['pAutoconfig_server_side_error'], $ah->error_as_string() ) ) );
		}
		else
		{
			if( DEBUG ) error_log( "Ok, config removed." );
			json_reply( array( 'success' => $PALANG['pAutoconfig_config_removed'] ) );
		}
		exit( 0 );
	}
	else
	{
		json_reply( array( 'error' => 'Unknown handler provided "' . $handler . '".' ) );
	}
	exit( 0 );
}

function json_reply( $data )
{
	if( !array_key_exists( 'error', $data ) && 
		!array_key_exists( 'info', $data ) && 
		!array_key_exists( 'success', $data ) )
	{
		error_log( "json_reply() missing message type: error, info or success" );
		return( false );
	}
	$allowed_domain = 'http'
		. ( ( array_key_exists( 'HTTPS', $_SERVER )
			&& $_SERVER[ 'HTTPS' ] 
			&& strtolower( $_SERVER[ 'HTTPS' ] ) !== 'off' ) 
				? 's' 
				: null )
		. '://' . $_SERVER[ 'HTTP_HOST' ];
	header( "Access-Control-Allow-Origin: $allowed_domain" );
	header( 'Content-Type: application/json;charset=utf-8' );
	if( DEBUG ) error_log( "Returning to client the payload: " . json_encode( $data ) );
	echo json_encode( $data );
	return( true );
}

function showAutoconfigForm( &$form )
{
	global $PALANG, $CONF, $languages, $smarty;
	if( DEBUG ) error_log( "showAutoconfigForm() received form data: " + print_r( $form, true ) );
	if( $form == null ) $form = array();
	if( array_key_exists( 'enable', $form ) )
	{
		if( array_key_exists( 'instruction', $form['enable'] ) )
		{
			if( count( $form['enable']['instruction'] ) == 0 )
			{
				$form['enable']['instruction'][] = array( 'lang' => 'en' );
			}
		}
	}
	
	if( array_key_exists( 'documentation', $form ) )
	{
		if( array_key_exists( 'description', $form['documentation'] ) )
		{
			if( count( $form['documentation']['description'] ) == 0 )
			{
				$form['documentation']['description'][] = array( 'lang' => 'en' );
			}
		}
	}
	$smarty->assign( 'form', $form );
	$smarty->assign( 'language_options', $languages );
	$smarty->assign( 'default_lang', 'en' );
	$smarty->assign( 'smarty_template', 'autoconfig' );
	$smarty->display( 'index.tpl');
	exit( 0 );
}

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */

?>
