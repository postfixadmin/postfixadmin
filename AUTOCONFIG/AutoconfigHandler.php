<?php
/*
Created on 2020-03-05
Copyright 2020 Jacques Deguest
Distributed under the same licence as Postfix Admin
*/
class AutoconfigHandler extends PFAHandler {
    protected $db_table_auto = 'autoconfig';
    protected $db_table_host = 'autoconfig_hosts';
    protected $db_table_text = 'autoconfig_text';
    protected $username;
    protected $is_admin = false;
    // All the domain names an admin is allwoed to manage
    public $all_domains = [];
    private $allowed_config_ids = [];
    // The domain names for this autoconfig
    protected $domains = [];
    protected $config_id;
    protected $db_data;
    public $error;
    public $debug = false;
    
    public function init($id) {
    }
    
    /**
     * @return void
     */
    protected function initStruct() {
        $this->struct = array(
            # field name                		 allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           		 editing?    form    list
            'encoding'					=> pacol( 0, 		1,      1,      'text', 'Autoconfig_encoding'				, 'XML encoding'                                , 'utf-8' ),
            'provider_id'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_provider_id'			, 'Provider ID'                                , '' ),
            'provider_domain'			=> pacol( 1, 		1,      1,      'text', 'Autoconfig_provider_domain'		, 'Applicable domain name'                                , '' ),
            'provider_name'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_provider_name'				, ''                                , '' ),
            'provider_short'            => pacol( 1, 		1,      1,      'text', 'Autoconfig_provider_short'				, ''                                , '' ),
            'incoming_server'			=> pacol( 1, 		1,      1,      'text', 'Autoconfig_incoming_server'				, ''                                , '' ),
            'outgoing_server'			=> pacol( 1, 		1,      1,      'text', 'Autoconfig_outgoing_server'				, ''                                , '' ),
            'type'						=> pacol( 1, 		1,      1,      'text', 'Autoconfig_type'				, ''                                , '' ),
            'hostname'					=> pacol( 1, 		1,      1,      'text', 'Autoconfig_hostname'				, ''                                , '' ),
            'port'						=> pacol( 1, 		1,      1,      'integer', 'Autoconfig_port'				, ''                                , '' ),
            'socket_type'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_socket_type'				, ''                                , '' ),
            'auth'						=> pacol( 1, 		1,      1,      'text', 'Autoconfig_auth'				, ''                                , '' ),
            'username'					=> pacol( 1, 		1,      1,      'text', 'Autoconfig_username'				, ''                                , '' ),
            'leave_messages_on_server'	=> pacol( 1, 		1,      1,      'boolean', 'Autoconfig_leave_messages_on_server'				, ''                                , '' ),
            'download_on_biff'			=> pacol( 1, 		1,      1,      'boolean', 'Autoconfig_download_on_biff'				, ''                                , '' ),
            'days_to_leave_messages_on_server'	=> pacol( 1, 		1,      1,      'integer', 'Autoconfig_days_to_leave_messages_on_server'				, ''                                , '' ),
            'check_interval'			=> pacol( 1, 		1,      1,      'integer', 'Autoconfig_check_interval'				, ''                                , '' ),
            'enable'					=> pacol( 1, 		1,      1,      'text', 'Autoconfig_enable'				, ''                                , '' ),
            'enable_status'				=> pacol( 1, 		1,      1,      'text', ''				, ''                                , '' ),
            'enable_url'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_enable_url'				, ''                                , '' ),
            'enable_instruction'		=> pacol( 1, 		1,      1,      'text', 'Autoconfig_enable_instruction'				, ''                                , '' ),
            'documentation'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_documentation'				, ''                                , '' ),
            'documentation_status'		=> pacol( 1, 		1,      1,      'text', ''				, ''                                , '' ),
            'documentation_url'			=> pacol( 1, 		1,      1,      'text', 'Autoconfig_documentation_url'				, ''                                , '' ),
            'documentation_desc'		=> pacol( 1, 		1,      1,      'text', 'Autoconfig_documentation_desc'				, ''                                , '' ),
            'webmail'					=> pacol( 1, 		1,      1,      'text', 'Autoconfig_webmail'				, ''                                , '' ),
            'webmail_login_page'		=> pacol( 1, 		1,      1,      'text', 'Autoconfig_webmail_login_page'				, ''                                , '' ),
            'webmail_login_page_info'	=> pacol( 1, 		1,      1,      'text', 'Autoconfig_webmail_login_page_info'				, ''                                , '' ),
            'lp_info_url'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_lp_info_url'				, ''                                , '' ),
            'lp_info_username'			=> pacol( 1, 		1,      1,      'text', 'Autoconfig_lp_info_username'				, ''                                , '' ),
            'lp_info_username_field_id'	=> pacol( 1, 		1,      1,      'text', 'Autoconfig_lp_info_username_field_id'				, ''                                , '' ),
            'lp_info_username_field_name'	=> pacol( 1, 		1,      1,      'text', 'Autoconfig_lp_info_username_field_name'				, ''                                , '' ),
            'lp_info_login_button_id'	=> pacol( 1, 		1,      1,      'text', 'Autoconfig_lp_info_login_button_id'				, ''                                , '' ),
            'lp_info_login_button_name'	=> pacol( 1, 		1,      1,      'text', 'Autoconfig_lp_info_login_button_name'				, ''                                , '' ),
            // Mac Mail specific fields
            'account_name'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_account_name'				, ''                                , '' ),
            // Typically 'email'
            'account_type'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_account_type'				, ''                                , '' ),
            'email'						=> pacol( 1, 		1,      1,      'text', 'Autoconfig_email'				, ''                                , '' ),
            'ssl_enabled'				=> pacol( 1, 		1,      1,      'boolean', 'Autoconfig_ssl'				, ''                                , '' ),
            // Will be empty obviously unless the user enters it in the form
            'password'					=> pacol( 1, 		1,      1,      'text', 'Autoconfig_password'				, ''                                , '' ),
            // Used for payload_description
            'description'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_description'				, ''                                , '' ),
            'organisation'				=> pacol( 1, 		1,      1,      'text', 'Autoconfig_organisation'				, ''                                , '' ),
            // regular account, or Microsoft Exchange
            'type'						=> pacol( 1, 		1,      1,      'text', 'Autoconfig_type'				, ''                                , '' ),
            'prevent_app_sheet'			=> pacol( 1, 		1,      1,      'boolean', 'Autoconfig_prevent_app_sheet'				, ''                                , '' ),
            'prevent_move'				=> pacol( 1, 		1,      1,      'boolean', 'Autoconfig_prevent_move'				, ''                                , '' ),
            'smime_enabled'				=> pacol( 1, 		1,      1,      'boolean', 'Autoconfig_smime_enabled'				, ''                                , '' ),
            'payload_remove_ok'			=> pacol( 1, 		1,      1,      'boolean', 'Autoconfig_payload_remove_ok'				, ''                                , '' ),
            // Outlook specific fields
            // domain_required -> Not sure this should be an option; false by default
            'spa'						=> pacol( 1, 		1,      1,      'text', 'Autoconfig_spa'				, ''                                , '' ),
        );
    }
    
    /**
     * @param string $username
     */
    public function __construct($username) {
        $this->username = $username;
        if ( authentication_has_role('admin') ) {
            $this->is_admin = true;
            $this->all_domains = list_domains_for_admin( $username );
            // Get the list of configuration ids, if any, this admin is allowed to access
            $this->allowed_config_ids = $this->get_config_ids_for_user( $username );
        }
    }
    
    protected function initMsg() {
        // Need to develop this part
    }
    
    /**
     * @return array
     */
    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pAutoconfig_page_title',
            'formtitle_edit' => 'pAutoconfig_page_title',
            'create_button' => 'save',

            # various settings
            'required_role' => 'admin',
            'listview' => 'list-virtual.php',
            'early_init' => 1, # 0 for create-domain
        );
    }

    protected function validate_new_id() {
        # autoconfig can only be enabled if a domain name exists
        if ( $this->is_admin ) {
            if ( count( $this->all_domains ) > 0 ) {
                return( true );
            }
        } else {
            // Need to develop this part
            return( true );
        }

        # still here? This means the mailbox doesn't exist or the admin/user doesn't have permissions to view it
        $this->errormsg[] = Config::Lang('invalid_parameter');
        return( false );
    }
    
    public function get_config_ids_for_user($user) {
        $table_autoconfig = table_by_key('autoconfig');
        $table_autoconfig_domains = table_by_key('autoconfig_domains');
        $table_domain_admins = table_by_key('domain_admins');
        $table_domain = table_by_key('domain');
        // This is a super admin, so he/she has access to all configs
        if ( authentication_has_role( 'global-admin' ) ) {
            // $sql = "SELECT DISTINCT ad.config_id FROM $table_autoconfig_domains ad LEFT JOIN $table_domain d ON ad.domain = d.domain WHERE d.domain != 'ALL AND d.active IS TRUE'";
            // global admin has access to all config
            $sql = "SELECT c.config_id FROM $table_autoconfig c";
        }
        // This is a per-domain admin, so we use the table domain_admis to cross check which configuration he/she has access
        elseif ( authentication_has_role( 'admin' ) ) {
            $E_username = escape_string( $user );
            $sql = "SELECT DISTINCT ad.config_id FROM $table_domain d LEFT JOIN $table_autoconfig_domains ad ON ad.domain = d.domain WHERE d.active IS TUE AND d.username='$E_username'";
        }
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        if ( $this->debug ) {
            error_log( "get_config_ids_for_user() \$sth = " . print_r( $sth, true ) );
        }
        $all = $this->db_fetchall( $sth );
        $list = [];
        foreach ( $all as $row ) {
            $list[] = $row['config_id'];
        }
        return( $list );
    }
    
    public function has_permission_over_config_id($user, $this_config_id) {
        if ( empty( $user ) || empty( $this_config_id ) ) {
            if ( $this->debug ) {
                error_log( "has_permission_over_config_id() user is empty, or no config was provided." );
            }
            return( false );
        }
        $table_admin = table_by_key('admin');
        $table_autoconfig_domains = table_by_key('autoconfig_domains');
        $table_domain_admins = table_by_key('domain_admins');
        $E_username = escape_string( $user );
        $E_config_id = escape_string( $this_config_id );
        $sql_admin = "SELECT a.* FROM $table_admin a WHERE a.username = '$E_username'";
        $res = db_query( $sql_admin );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $row = $this->db_assoc( $sth );
        if ( !empty( $row ) ) {
            // Admin status is not active
            if ( !$row['active'] ) {
                if ( $this->debug ) {
                    error_log( "has_permission_over_config_id() config $this_config_id is not active." );
                }
                return( false );
            }
            // Admin is a super admin, so he has access to everything
            elseif ( $row['superadmin'] ) {
                if ( $this->debug ) {
                    error_log( "has_permission_over_config_id() user $user is a super admin, returning true." );
                }
                return( true );
            } else {
                $true = db_get_boolean( true );
                $sql = "SELECT c.config_id FROM $table_autoconfig_domains c LEFT JOIN $table_domain_admins da ON da.domain = c.domain WHERE da.username = '$E_username' AND da.active = '$true' AND c.config_id = '$E_config_id'";
                if ( $this->debug ) {
                    error_log( "has_permission_over_config_id() checking user '$user' permission over config '$this_config_id' with sql query: $sql" );
                }
                $res = db_query( $sql );
                if ( !empty( $res['error'] ) ) {
                    $this->error = $res['error'];
                    return( false );
                }
                $sth = $res['result'];
                $row = $this->db_assoc( $sth );
                return( !empty( $row ) );
            }
        }
        // This is a regular user
        else {
            return( false );
        }
    }
    
    public function allowed_ids() {
        return( $this->allowed_config_ids );
    }

    public function config_id($id) {
        if ( isset( $id ) ) {
            if ( $this->debug ) {
                error_log( "config_id() checking config id \"$id\"." );
            }
            $table_autoconfig = table_by_key('autoconfig');
            $E_id = escape_string( $id );
            $sql = "SELECT config_id FROM $table_autoconfig WHERE config_id = '$E_id'";
            $res = db_query( $sql );
            if ( !empty( $res['error'] ) ) {
                $this->error = $res['error'];
                return( false );
            }
            $sth = $res['result'];
            $row = $this->db_assoc( $sth );
            if ( empty( $row ) ) {
                if ( $this->debug ) {
                    error_log( "config_id() could not find config id \"$id\"." );
                }
                return( false );
            }
            if ( $this->debug ) {
                error_log( "config_id() config id \"$id\" found." );
            }
            $this->config_id = $row['config_id'];
        }
        return( $this->config_id );
    }
    
    public function db_assoc($sth) {
        if ( empty( $sth ) ) {
            throw( "No statement handler was provided." );
        }
        try {
            return( $sth->fetch( PDO::FETCH_ASSOC ) );
        } catch ( Exception $e ) {
            $this->error = $e->getMessage();
            return( false );
        }
    }
    
    public function db_fetchall($sth) {
        if ( empty( $sth ) ) {
            throw( "No statement handler was provided." );
        }

        try {
            return( $sth->fetchAll(PDO::FETCH_ASSOC) );
        } catch ( Exception $e ) {
            $this->error = $e->getMessage();
            return( false );
        }
    }
    
    public function db_rows($sth) {
        if ( empty( $sth ) ) {
            throw( "No statement handler was provided." );
        }
        try {
            return( $sth->rowCount() );
        } catch ( Exception $e ) {
            $this->error = $e->getMessage();
            return( false );
        }
    }
    
    public function error_as_string() {
        if ( is_array( $this->error ) ) {
            return( implode( ', ', $this->error ) );
        } else {
            return( $this->error );
        }
    }
    
    private function get_config($id) {
        if ( !isset( $id ) ) {
            $id = $this->config_id;
        }
        $table_autoconfig = table_by_key('autoconfig');
        $E_config_id = escape_string( $id );
        $sql = "SELECT * FROM $table_autoconfig WHERE config_id = '$E_config_id'";
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $row = $this->db_assoc( $sth );
        if ( empty( $row ) ) {
            return( false );
        }
        return( $row );
    }
    
    // Get the list of config id that this user has access
    public function get_config_ids() {
        $table_autoconfig = table_by_key('autoconfig');
        $table_autoconfig_domains = table_by_key('autoconfig_domains');
        $table_domain_admins = table_by_key('domain_admins');
        $true = db_get_boolean( true );
        $E_username = escape_string( $this->username );
        $sql = "SELECT distinct c.config_id, c.provider_id FROM $table_autoconfig_domains d LEFT JOIN $table_autoconfig c ON c.config_id = d.config_id LEFT JOIN $table_domain_admins da ON da.username = '$E_username' AND da.active = '$true' AND (da.domain = 'ALL' OR da.domain = d.domain)";
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $all = $this->db_fetchall( $sth );
        $list = [];
        foreach ( $all as $row ) {
            $list[$row['config_id']] = $row['provider_id'];
        }
        if ( $this->debug ) {
            error_log( "get_config_ids() returning '" . print_r( $list, true ) . "'" );
        }
        return( $list );
    }
    
    public function get_id_by_domain($thisDomain) {
        if ( !isset( $thisDomain ) ) {
            // Are the domain names for this autoconfig set ?
            if ( !isset( $this->domains ) ) {
                return( false );
            }
            // Pick one
            $thisDomain = $this->domains[0];
        }
        $E_domain = escape_string( $thisDomain );
        $table_autoconfig_domains = table_by_key('autoconfig_domains');
        $sql = "SELECT config_id FROM $table_autoconfig_domains WHERE domain = '$E_domain'";
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $row = $this->db_assoc( $sth );
        if ( empty( $row ) ) {
            return( false );
        }
        return( $row['config_id'] );
    }
    
    private function get_domains($id) {
        if ( count( $this->domains ) ) {
            return( $this->domains );
        } elseif ( !isset( $id ) ) {
            if ( !empty( $this->config_id ) ) {
                $id = $this->config_id;
            } else {
                return( false );
            }
        }
        $table_autoconfig_domains = table_by_key('autoconfig_domains');
        $table_domain_admins = table_by_key('domain_admins');
        $E_config_id = escape_string( $id );
        $E_username = escape_string( $this->username );
        // Make sure the admin can only get the list of domain names he is in charge of
        $sql = "SELECT d.domain FROM $table_autoconfig_domains AS d LEFT JOIN $table_domain_admins AS da ON da.username = '$E_username' AND (da.domain = 'ALL' OR da.domain = d.domain) WHERE d.config_id = '$E_config_id'";
        if ( $this->debug ) {
            error_log( "get_domains() executing following query to get the list of authorised domains: $sql" );
        }
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $all = $this->db_fetchall( $sth );
        $list = [];
        foreach ( $all as $row ) {
            $list[] = $row['domain'];
        }
        if ( $this->debug ) {
            error_log( "get_domains() returning '" . print_r( $list, true ) . "'" );
        }
        return( $list );
    }
    
    public function get_other_config_domains($id) {
        if ( is_null( $id ) ) {
            $id = $this->config_id;
        }
        $table_autoconfig_domains = table_by_key('autoconfig_domains');
        $table_domain_admins = table_by_key('domain_admins');
        $true = db_get_boolean( true );
        $E_username = escape_string( $this->username );
        if ( !empty( $id ) ) {
            $E_config_id = escape_string( $id );
            $sql = "SELECT d.domain FROM autoconfig_domains d LEFT JOIN domain_admins da ON da.username = '$E_username' AND da.active = '$true' AND (da.domain = 'ALL' OR da.domain = d.domain) WHERE d.config_id != '$E_config_id'";
        } else {
            $sql = "SELECT d.domain FROM autoconfig_domains d LEFT JOIN domain_admins da ON da.username = '$E_username' AND da.active = '$true' AND (da.domain = 'ALL' OR da.domain = d.domain)";
        }
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $all = $this->db_fetchall( $sth );
        $list = [];
        foreach ( $all as $row ) {
            $list[] = $row['domain'];
        }
        return( $list );
    }
    
    private function get_hosts($type, $id) {
        if ( empty( $type ) ) {
            return( false );
        } elseif ( $type != 'in' && $type != 'out' ) {
            return( false );
        }
        
        if ( !isset( $id ) ) {
            if ( !empty( $this->config_id ) ) {
                $id = $this->config_id;
            } else {
                return( false );
            }
        }
        
        $table_autoconfig_hosts = table_by_key('autoconfig_hosts');
        $E_config_id = escape_string( $id );
        if ( $type == 'in' ) {
            $sql = "SELECT *, id AS \"host_id\" FROM $table_autoconfig_hosts WHERE (type = 'imap' OR type = 'pop3') AND config_id = '$E_config_id' ORDER BY priority";
        } else {
            $sql = "SELECT *, id AS \"host_id\" FROM $table_autoconfig_hosts WHERE type = 'smtp' AND config_id = '$E_config_id' ORDER BY priority";
        }
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $all = $this->db_fetchall( $sth );
        $list = [];
        foreach ( $all as $row ) {
            $list[] = $row;
        }
        return( $list );
    }
    
    private function get_text($type, $id) {
        // Must provide explicitely the type
        if ( empty( $type ) ) {
            return( false );
        } elseif ( $type != 'instruction' && $type != 'documentation' ) {
            return( false );
        }
        if ( !isset( $id ) ) {
            if ( !empty( $this->config_id ) ) {
                $id = $this->config_id;
            } else {
                return( false );
            }
        }
        $table_autoconfig_text = table_by_key('autoconfig_text');
        $E_type = escape_string( $type );
        $E_config_id = escape_string( $id );
        $sql = "SELECT * FROM $table_autoconfig_text WHERE type = '$E_type' AND config_id = '$E_config_id'";
        $res = db_query( $sql );
        if ( !empty( $res['error'] ) ) {
            $this->error = $res['error'];
            return( false );
        }
        $sth = $res['result'];
        $all = $this->db_fetchall( $sth );
        $list = [];
        foreach ( $all as $row ) {
            $list[] = $row;
        }
        return( $list );
    }
    
    public function get_details($id) {
        if ( !$this->is_admin ) {
            return( false );
        }
        if ( empty( $id ) ) {
            if ( $this->debug ) {
                error_log( "No id provided, returning false." );
            }
            return( false );
        }
        // Some security: do not get the details for this configuration if the user does not have permission
        if ( !$this->has_permission_over_config_id( $this->username, $id ) ) {
            if ( $this->debug ) {
                error_log( sprintf( "Admin %s has no permission over this config %s, returning false.", $this->username, $id ) );
            }
            return( false );
        }
        $config = [];
        $conf_domains = [];
        $conf_hosts_in = [];
        $conf_hosts_out = [];
        $conf_texts_inst = [];
        $conf_texts_doc = [];
        if ( ( $config = $this->get_config( $id ) ) === false ) {
            if ( $this->debug ) {
                error_log( "Unable to get basic config data, returning false." );
            }
            return( false );
        }
        // If active is null, set it to true by default
        if ( is_null( $config['active'] ) ) {
            $config['active'] = true;
        }
        $booleanProperties = array( 'enable_status', 'documentation_status', 'ssl_enabled', 'prevent_app_sheet', 'prevent_move', 'smime_enabled', 'payload_remove_ok', 'active', 'spa' );
        $this->encode_boolean( $config, $booleanProperties );
        if ( ( $conf_domains = $this->get_domains( $id ) ) === false ) {
            if ( $this->debug ) {
                error_log( "Unable to get config domains, returning false." );
            }
            return( false );
        }
        if ( $this->debug ) {
            error_log( sprintf( "Found %d domains for config $id", count( $conf_domains ) ) );
        }
        $config['provider_domain'] = $conf_domains;
        // To build the domain names html menu
        $config['provider_domain_options'] = $this->all_domains;
        if ( $this->debug ) {
            error_log( sprintf( "Found %d total domains for config $id", count( $conf_domains ) ) );
        }
        
        // Get incoming servers
        if ( ( $conf_hosts_in = $this->get_hosts( 'in', $id ) ) === false ) {
            if ( $this->debug ) {
                error_log( "Unable to get config incoming hosts, returning false." );
            }
            return( false );
        }
        if ( $this->debug ) {
            error_log( sprintf( "Found %d incoming servers for config $id", count( $conf_hosts_in ) ) );
        }
        $booleanProperties = array( 'leave_messages_on_server', 'download_on_biff' );
        // foreach( $conf_hosts_in as $conf_hosts_ref )
        for ( $j = 0; $j < count( $conf_hosts_in ); $j++ ) {
            $conf_hosts_in[$j] = $this->encode_boolean( $conf_hosts_in[$j], $booleanProperties );
            if ( $this->debug ) {
                error_log( "get_details(): incoming host data is now: " . print_r( $conf_hosts_in[$j], true ) );
            }
        }
        $config['incoming_server'] = $conf_hosts_in;
        
        // Get outgoing servers
        if ( ( $conf_hosts_out = $this->get_hosts( 'out', $id ) ) === false ) {
            if ( $this->debug ) {
                error_log( "Unable to get config outgoing hosts, returning false." );
            }
            return( false );
        }
        if ( $this->debug ) {
            error_log( sprintf( "Found %d outgoing servers for config $id", count( $conf_hosts_out ) ) );
        }
        // foreach( $conf_hosts_out as $conf_hosts_ref )
        for ( $j = 0; $j < count( $conf_hosts_out ); $j++ ) {
            $conf_hosts_out[$j] = $this->encode_boolean( $conf_hosts_out[$j], $booleanProperties );
        }
        $config['outgoing_server'] = $conf_hosts_out;
        
        // Get enabling instructions, if any
        if ( ( $conf_texts_inst = $this->get_text( 'instruction', $id ) ) === false ) {
            if ( $this->debug ) {
                error_log( "Unable to get enabling instrucctions, returning false." );
            }
            return( false );
        }
        if ( $this->debug ) {
            error_log( sprintf( "Found %d enable instruction(s) for config $id", count( $conf_texts_inst ) ) );
        }
//     	$langs = array();
//     	foreach( $conf_texts_inst as $ref )
//     	{
//     		$langs[ $ref['lang'] ] = $ref['phrase'];
//     	}
        $config['enable'] = array(
            'url' => $config['enable_url'],
            'instruction' => $conf_texts_inst,
        );
        
        // Configuration support documentation
        if ( ( $conf_texts_doc = $this->get_text( 'documentation', $id ) ) === false ) {
            if ( $this->debug ) {
                error_log( "Unable to get documentation description, returning false." );
            }
            return( false );
        }
        if ( $this->debug ) {
            error_log( sprintf( "Found %d documentatoin description(s) for config $id", count( $conf_texts_doc ) ) );
        }
//     	$langs = array();
//     	foreach( $conf_texts_doc as $ref )
//     	{
//     		$langs[ $ref['lang'] ] = $ref['phrase'];
//     	}
        $config['documentation'] = array(
            'url' => $config['documentation_url'],
            'description' => $conf_texts_doc,
        );
        if ( $this->debug ) {
            error_log( sprintf( "Returning hash ref with %d keys", count( array_keys( $config ) ) ) );
        }
        return( $config );
    }
    
    public function remove_config($id) {
        global $CONF, $PALANG;
        $conf_data = array();
        if ( empty( $id ) ) {
            $this->error = $PALANG['pAutoconfig_no_config_id_provded'];
            return( false );
        } elseif ( ( $conf_data = $this->get_config( $id ) ) === false ) {
            $this->error = sprintf( $PALANG['pAutoconfig_config_id_not_found'], $id );
            return( false );
        } elseif ( !$this->has_permission_over_config_id( $this->username, $id ) ) {
            $this->error = sprintf( $PALANG['pAutoconfig_lack_permission_over_config_id'], $id );
            return( false );
        }
        $table_autoconfig = table_by_key('autoconfig');
        $ok = 0;
        try {
            // $ok = db_delete( $table_autoconfig, 'config_id', $id );
            // I need this to throw an exception so I can report the issue
            $ok = db_execute( "DELETE FROM $table_autoconfig WHERE config_id = ?", array($id), true );
        } catch ( Exception $e ) {
            if ( $this->debug ) {
                error_log( "remove_config(): An error occurred while trying to remove config id $id: " . $e->getMessage() );
            }
            $this->error = $e->getMessage();
            return( false );
        }
        return( $ok );
    }
    
    public function save_config(&$data) {
        global $CONF, $PALANG;
        // Number of rows changed
        $ok = 0;
        if ( !is_array( $data ) ) {
            $this->error = $PALANG['pAutoconfig_save_no_data_provided'];
            return( false );
        } elseif ( !isset( $data['provider_domain'] ) ) {
            $this->error = $PALANG['pAutoconfig_no_domain_names_have_been_selected'];
            return( false );
        }
        // Should not happen
        elseif ( !is_array( $data['provider_domain'] ) ) {
            $this->error = $PALANG['pAutoconfig_domain_data_provided_is_not_an_array'];
            return( false );
        } elseif ( count( $data['provider_domain'] ) == 0 ) {
            $this->error = $PALANG['pAutoconfig_no_domain_names_have_been_selected'];
            return( false );
        }
        $config_data = array(
            'encoding'			=> @$data['encoding'],
            'provider_id'		=> @$data['provider_id'],
            'provider_name'		=> @$data['provider_name'],
            'provider_short'	=> @$data['provider_short'],
            'enable_status'		=> @$data['enable_status'],
            'enable_url'		=> @$data['enable_url'],
            'documentation_status'		=> @$data['documentation_status'],
            'documentation_url'	=> @$data['documentation_url'],
            'webmail_login_page'	=> @$data['webmail_login_page'],
            'lp_info_url'		=> @$data['lp_info_url'],
            'lp_info_username_field_id' => @$data['lp_info_username_field_id'],
            'lp_info_username_field_name' => @$data['lp_info_username_field_name'],
            'lp_info_login_button_id' => @$data['lp_info_login_button_id'],
            'lp_info_login_button_name' => @$data['lp_info_login_button_name'],
            'account_name'		=> @$data['account_name'],
            'account_type'		=> @$data['account_type'],
            'email'				=> @$data['email'],
            'ssl_enabled'		=> @$data['ssl_enabled'],
            'description'		=> @$data['description'],
            'organisation'		=> @$data['organisation'],
            'payload_type'		=> @$data['payload_type'],
            'prevent_app_sheet'	=> @$data['prevent_app_sheet'],
            'prevent_move'		=> @$data['prevent_move'],
            'smime_enabled'		=> @$data['smime_enabled'],
            'payload_remove_ok'	=> @$data['payload_remove_ok'],
            'spa'				=> @$data['spa'],
            'active'			=> @$data['active'],
            'sign_option'		=> @$data['sign_option'],
            'cert_filepath'		=> @$data['cert_filepath'],
            'privkey_filepath'	=> @$data['privkey_filepath'],
            'chain_filepath'	=> @$data['chain_filepath'],
        );
        $dataError = null;
        if ( ( $dataError = $this->check_autoconfig_data( $config_data ) ) != null ) {
            $this->error = $dataError;
            return( false );
        }
        // In case of update
        elseif ( !empty( $data['config_id'] ) ) {
            // Should not be happening, but let's not assume anything
            if ( empty( $this->config_id ) ) {
                $this->error = $PALANG['pAutoconfig_no_config_id_declared'];
                return( false );
            } elseif ( $data['config_id'] != $this->config_id ) {
                if ( $this->debug ) {
                    error_log( sprintf( "save_config() config id submitted \"%s\" is not the same as our current id \"%s\"", $data['config_id'], $this->config_id ) );
                }
                $this->error = sprintf( $PALANG['pAutoconfig_config_id_submitted_is_unauthorised'], $data['config_id'] );
                return( false );
            }
        }
        // For the rest, there could be no imap, pop3 or smtp declared. That's up to the user who is always right
        // Likewise, there could be no login enable instruction or support documentation, so we don't make them mandatory
        if ( $this->debug ) {
            error_log( "Base config data are: " . print_r( $config_data, true ) );
        }
        
        $table_autoconfig = table_by_key('autoconfig');
        $table_autoconfig_domains = table_by_key('autoconfig_domains');
        $table_autoconfig_hosts = table_by_key('autoconfig_hosts');
        $table_autoconfig_text = table_by_key('autoconfig_text');
        
        // Start sql transaction
        db_begin();
        try {
            $is_new = empty( $this->config_id );
            if ( !$is_new ) {
                try {
                    $ok = db_update( 'autoconfig', 'config_id', $this->config_id, $config_data, array('modified'), true );
                } catch ( Exception $e ) {
                    $this->error = $e->getMessage();
                    db_rollback();
                    return( false );
                }
                $config_data['config_id'] = $this->config_id;
            }
            // New entry
            else {
                $this->config_id = $config_data['config_id'] = $data['config_id'] = $this->generate_uuid_v4();
                try {
                    $ok = db_insert( 'autoconfig', $config_data, array('created', 'modified'), true );
                } catch ( Exception $e ) {
                    $this->error = $e->getMessage();
                    db_rollback();
                    return( false );
                }
                if ( $ok == 0 ) {
                    $this->error = $PALANG['pAutoconfig_failed_to_add_config'];
                    db_rollback();
                    return( false );
                }
            }
            
            // Process domain names. We get the current list, first remove the ones that have been removed and add the new ones
            $selected_domains = $data['provider_domain'];
            if ( !$is_new ) {
                if ( $this->debug ) {
                    error_log( "save_config() get current domain names for this update." );
                }
                $current_domains = array();
                if ( ( $current_domains = $this->get_domains( $this->config_id ) ) === false ) {
                    $this->error = $PALANG['pAutoconfig_no_domain_authorised_for_this_admin'];
                    db_rollback();
                    return( false );
                }
                // First remove the ones that are not anymore in our selection
                // $E_config_id = escape_string( $this->config_id );
                foreach ( $current_domains as $domain ) {
                    if ( !in_array( $domain, $selected_domains ) ) {
                        try {
                            // $ok += db_delete( $table_autoconfig_domains, 'domain', $domain, "AND config_id = '$E_config_id'" );
                            $ok += db_execute( "DELETE FROM $table_autoconfig_domains WHERE domain = ? AND config_id = ?", array( $domain, $this->config_id ), true );
                        } catch ( Exception $e ) {
                            $this->error = $e->getMessage();
                            db_rollback();
                            return( false );
                        }
                    }
                }
            }
            // Now, add the ones selected that are not in the current domains
            $current_domains = array();
            if ( !empty( $this->config_id ) ) {
                if ( $this->debug ) {
                    error_log( "save_config() get current domain names for this config id \"" . $this->config_id . "\"." );
                }
                if ( ( $current_domains = $this->get_domains( $this->config_id ) ) === false ) {
                    if ( $this->debug ) {
                        error_log( "save_config() get_domains returned: '" . print_r( $current_domains, true ) . "'." );
                    }
                    $this->error = $PALANG['pAutoconfig_no_domain_authorised_for_this_admin'];
                    db_rollback();
                    return( false );
                }
            }
            
            foreach ( $selected_domains as $domain ) {
                if ( !in_array( $domain, $current_domains ) ) {
                    $this_data = array(
                        'config_id' => $this->config_id,
                        'domain' => $domain,
                    );
                    try {
                        $added = db_insert( 'autoconfig_domains', $this_data, [], true );
                    } catch ( Exception $e ) {
                        $this->error = $e->getMessage();
                        db_rollback();
                        return( false );
                    }
                    if ( $added == 0 ) {
                        $this->error = sprintf( $PALANG['pAutoconfig_failed_to_add_domain_to_config'], $domain );
                        db_rollback();
                        return( false );
                    } else {
                        $ok += $added;
                    }
                }
            }
            $config_data['provider_domain'] = $selected_domains;
            $config_data['provider_domain_options'] = $this->all_domains;
        
            // Process hosts, if any
            // First, get current host, and remove the ones that have been removed
            if ( !$is_new ) {
                $host_types = ['in','out'];
                foreach ( $host_types as $this_type ) {
                    $current_servers = $this->get_hosts( $this_type, $this->config_id );
                    // There must be at least one host for each type, even if blank
                    if ( !array_key_exists( 'host_id', $data ) ) {
                        error_log( "No host id could be found at all from the web data submitted for config id \"" . $this->config_id . "\" which is" . ( $is_new ? '' : ' not' ) . " new." );
                        $this->error = "No host id could be found at all from the web data submitted for config id \"" . $this->config_id . "\" which is" . ( $is_new ? '' : ' not' ) . " new.";
                        db_rollback();
                        return( false );
                    }
                    foreach ( $current_servers as $ref ) {
                        if ( !in_array( $ref['host_id'], $data['host_id'] ) ) {
                            try {
                                // $deleted = db_delete( $table_autoconfig_hosts, 'id', $ref['host_id'] );
                                $deleted = db_execute( "DELETE FROM $table_autoconfig_hosts WHERE id = ?", array( $ref['host_id'] ), true );
                            } catch ( Exception $e ) {
                                $this->error = $e->getMessage();
                                db_rollback();
                                return( false );
                            }
                        }
                    }
                }
            }
            
            if ( count( $data['hostname'] ) > 0 ) {
                // counter by type
                $counter = [];
                // To check for duplicates
                $processed = [];
                for ( $i = 0; $i < count( $data['hostname'] ); $i++ ) {
                    $host_data = array(
                        'host_id'				=> @$data['host_id'][$i],
                        'type'					=> @$data['type'][$i],
                        'hostname'				=> @$data['hostname'][$i],
                        'port'					=> @$data['port'][$i],
                        'socket_type'			=> @$data['socket_type'][$i],
                        'auth'					=> @$data['auth'][$i],
                        'username'				=> @$data['username'][$i],
                        'leave_messages_on_server'	=> @$data['leave_messages_on_server'][$i],
                        'download_on_biff'		=> @$data['download_on_biff'][$i],
                        'days_to_leave_messages_on_server'	=> @$data['days_to_leave_messages_on_server'][$i],
                        'check_interval'		=> @$data['check_interval'][$i],
                        'priority'				=> ++$counter[$data['type'][$i]],
                    );
                    if ( ( $dataError = $this->check_autoconfig_host_data( $host_data ) ) != null ) {
                        $this->error = $dataError;
                        db_rollback();
                        return( false );
                    } elseif ( array_key_exists( $host_data['hostname'], $processed ) &&
                        $processed[ $host_data['hostname'] ]['type'] == $host_data['type'] &&
                        $processed[ $host_data['hostname'] ]['port'] == $host_data['port'] ) {
                        $this->error = sprintf( $PALANG['pAutoconfig_duplicate_host'], $host_data['hostname'], $host_data['type'], $host_data['port'] );
                        db_rollback();
                        return( false );
                    }
                    $processed[ $host_data['hostname'] ] = array( 'type' => $host_data['type'], 'port' => $host_data['port'] );
                    
                    if ( !empty( $host_data['host_id'] ) ) {
                        // This was just temporary for checking. There is no host_id field
                        $this_id = $host_data['host_id'];
                        unset( $host_data['host_id'] );
                        try {
                            $ok += db_update( 'autoconfig_hosts', 'id', $this_id, $host_data, [], true );
                        } catch ( Exception $e ) {
                            $this->error = $e->getMessage();
                            db_rollback();
                            return( false );
                        }
                    } else {
                        unset( $host_data['host_id'] );
                        $host_data['config_id'] = $config_data['config_id'];
                        try {
                            $added = db_insert( 'autoconfig_hosts', $host_data, [], true );
                        } catch ( Exception $e ) {
                            $this->error = $e->getMessage();
                            db_rollback();
                            return( false );
                        }
                        if ( $added == 0 ) {
                            $this->error = sprintf( $PALANG['pAutoconfig_failed_to_add_host_to_config'], $host_data['hostname'] );
                            db_rollback();
                            return( false );
                        } else {
                            $ok += $added;
                        }
                    }
                }
            }
            $host_types = ['in','out'];
            foreach ( $host_types as $this_type ) {
                $current_servers = $this->get_hosts( $this_type, $this->config_id );
                if ( $this_type == 'in' ) {
                    $config_data['incoming_server'] = $current_servers;
                } else {
                    $config_data['outgoing_server'] = $current_servers;
                }
            }
            
            // First remove instructions or documentation that have been removed from the interface
            $textTypes = array( 'instruction', 'documentation' );
            if ( !$is_new ) {
                foreach ( $textTypes as $textType ) {
                    $all_text = [];
                    // No need to bother checking one by one, if there are no text at all
                    if ( !array_key_exists( "${textType}_id", $data ) ||
                        ( is_array( $data["${textType}_id"] ) && count( $data["${textType}_id"] ) == 0 ) ) {
                        try {
                            // $ok += db_delete( $table_autoconfig_text, 'config_id', $this->config_id );
                            $ok += db_execute( "DELETE FROM $table_autoconfig_text WHERE config_id = ?", array( $this->config_id ), true );
                        } catch ( Exception $e ) {
                            $this->error = $e->getMessage();
                            db_rollback();
                            return( false );
                        }
                        continue;
                    }
                    // An error occurred. Need to report it: TODO
                    if ( ( $all_text = $this->get_text( $textType, $this->config_id ) ) === false ) {
                        error_log( "An error occurred. Could not get all the text type ${textType} for config id \"" . $this->config_id . "\"." );
                        db_rollback();
                        $this->error = "An error occurred. Could not get all the text type ${textType} for config id \"" . $this->config_id . "\".";
                        return( false );
                    }
                    
                    // One by one. If our existing text id for this type is not in the array of ids, then remove it
                    foreach ( $all_text as $ref ) {
                        if ( empty( $ref['id'] ) ) {
                            error_log( "Somehow, I got an empty text id from function get_text() for config \"" . $this->config_id . "\"." );
                            db_rollback();
                            $this->error = "Somehow, I got an empty text id from function get_text() for config \"" . $this->config_id . "\".";
                            return( false );
                        }
                        if ( !in_array( $ref['id'], $data["${textType}_id"] ) ) {
                            try {
                                // $ok += db_delete( $table_autoconfig_text, 'id', $ref['id'] );
                                $ok += db_execute( "DELETE FROM $table_autoconfig_text WHERE id = ?", array( $ref['id'] ), true );
                            } catch ( Exception $e ) {
                                $this->error = $e->getMessage();
                                db_rollback();
                                return( false );
                            }
                        }
                    }
                }
            }
            
            // Now, do the additions
            // Process login enable instruction and support documentation
            foreach ( $textTypes as $textType ) {
                $existing_langs = [];
                if ( array_key_exists( "${textType}_lang", $data ) &&
                    is_array( $data["${textType}_lang"] ) &&
                    count( $data["${textType}_lang"] ) ) {
                    for ( $i = 0; $i < count( $data["${textType}_lang"] ); $i++ ) {
                        $text_data = array(
                            'type'		=> $textType,
                            'id'		=> @$data["${textType}_id"][$i],
                            'lang'		=> @$data["${textType}_lang"][$i],
                            'phrase'	=> @$data["${textType}_text"][$i],
                        );
                        // The text is empty: no need to go further
                        if ( preg_match( '/^[[:blank:]\r\n]*$/', $text_data['phrase'] ) ) {
                            continue;
                        }
                        // Found a language duplicate
                        elseif ( in_array( $text_data['lang'], $existing_langs ) ) {
                            $this->error = sprintf( $PALANG['pAutoconfig_text_language_already_used'], $text_data['lang'] );
                            db_rollback();
                            return( false );
                        }
                        $existing_langs[] = $text_data['lang'];
                        
                        if ( ( $dataError = $this->check_autoconfig_text_data( $text_data ) ) != null ) {
                            $this->error = $dataError;
                            db_rollback();
                            return( false );
                        }
                        if ( empty( $text_data['id'] ) ) {
                            $text_data['config_id'] = $config_data['config_id'];
                            unset( $text_data['id'] );
                            try {
                                $added = db_insert( 'autoconfig_text', $text_data, [], true );
                            } catch ( Exception $e ) {
                                $this->error = $e->getMessage();
                                db_rollback();
                                return( false );
                            }
                            if ( $added == 0 ) {
                                $this->error = sprintf( $PALANG['pAutoconfig_failed_to_add_text_to_config'], mb_substr( $text_data['phrase'], 0, 12 ) );
                                db_rollback();
                                return( false );
                            } else {
                                $ok += $added;
                            }
                        } else {
                            $textId = $text_data['id'];
                            unset( $text_data['id'] );
                            try {
                                $ok += db_update( 'autoconfig_text', 'id', $textId, $text_data, [], true );
                            } catch ( Exception $e ) {
                                $this->error = $e->getMessage();
                                db_rollback();
                                return( false );
                            }
                        }
                    }
                } else {
                    if ( $this->debug ) {
                        error_log( "save_config() No lang found for text $textType" );
                    }
                }
                
                $all_text = [];
                if ( ( $all_text = $this->get_text( $textType, $this->config_id ) ) === false ) {
                    error_log( "An error occurred. Could not get all the text type ${textType} for config id \"" . $this->config_id . "\"." );
                    db_rollback();
                    $this->error = "An error occurred. Could not get all the text type ${textType} for config id \"" . $this->config_id . "\".";
                    return( false );
                }
                
                if ( $textType == 'instruction' ) {
                    $config_data['enable'] = array(
                        'url' => $config_data['enable_url'],
                        'instruction' => $all_text,
                    );
                }
                // Otherwise this is the suppport documentation
                else {
                    $config_data['documentation'] = array(
                        'url' => $config_data['documentation_url'],
                        'description' => $all_text,
                    );
                }
            }
            // All clear, we commit the changes
            db_commit();
            return( $config_data );
        } catch ( Exception $e ) {
            db_rollback();
            $this->error = $e->getMessage();
            return( false );
        }
    }
    
    // Taken from StackOverflow: https://stackoverflow.com/a/44504979/4814971
    private function generate_uuid_v4() {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        $data = PHP_MAJOR_VERSION < 7 ? openssl_random_pseudo_bytes(16) : random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    public function check_autoconfig_data(&$data) {
        global $CONF, $PALANG;
        $errorList = [];
        if ( !empty( $data['config_id'] ) ) {
            if ( !$this->get_config( $data['config_id'] ) ) {
                $errorList[] = sprintf( $PALANG['pAutoconfig_config_id_not_found'], $data['config_id'] );
            } elseif ( !$this->has_permission_over_config_id( $this->username, $data['config_id'] ) ) {
                $errorList[] = sprintf( $PALANG['pAutoconfig_lack_permission_over_config_id'], $data['config_id'] );
            }
        }
        if ( !empty( $data['encoding'] ) && !preg_match( '/^[a-zA-Z][\w\-]+$/', $data['encoding'] ) ) {
            $errorList[] = sprintf( $PALANG['pAutoconfig_invalid_encoding'], $data['encoding'] );
        }
        if ( empty( $data['provider_id'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_empty_provider_id'];
        }
        // I do not check on purpose the file path of the cert, private key and chain (if any), because the user may have provided the information before setting up those files, and I do not want to annoy the user
        $booleanProperties = array( 'enable_status', 'documentation_status', 'ssl_enabled', 'prevent_app_sheet', 'prevent_move', 'smime_enabled', 'payload_remove_ok', 'active', 'spa' );
        $this->decode_boolean( $data, $booleanProperties );
        if ( count( $errorList ) == 0 ) {
            $this->empty2null( $data );
            return( null );
        } else {
            return( $errorList );
        }
    }
    
    public function check_autoconfig_domains($domain_list) {
        global $CONF, $PALANG;
        $errorList = [];
        if ( count( $this->all_domains ) == 0 ) {
            $errorList[] = $PALANG['pAutoconfig_no_domain_allocated_to_admin'];
            return( $errorList );
        } elseif ( !is_array( $domain_list ) ) {
            $errorList[] = $PALANG['pAutoconfig_data_provided_is_not_array'];
            return( $errorList );
        }
        // Nothing to check
        elseif ( !count( $domain_list ) ) {
            return( null );
        }
        
        $bad_domains = [];
        foreach ( $domain_list as $domain ) {
            if ( !in_array( $domain, $this->all_domains ) ) {
                $bad_domains[] = $domain;
            }
        }
        if ( count( $bad_domains ) > 0 ) {
            $errorList[] = sprintf( $PALANG['pAutoconfig_unauthorised_domains'], join( ', ', $bad_domains ) );
        }
        
        if ( count( $errorList ) == 0 ) {
            return( null );
        } else {
            return( $errorList );
        }
    }
    
    public function check_autoconfig_host_data(&$data) {
        global $PALANG;
        $errorList = [];
        if ( empty( $data['type'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_host_no_type_provided'];
        } elseif ( !preg_match( '/^imap|pop3|smtp$/i', $data['type'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_host_invalid_type_value'];
        } else {
            $data['type'] = strtolower( $data['type'] );
        }
        
        if ( !empty( $data['host_id'] ) ) {
            if ( !preg_match( '/^\d+$/', $data['host_id'] ) ) {
                $errorList[] = sprintf( $PALANG['pAutoconfig_host_id_is_not_an_integer'], $data['host_id'] );
            }
        }
        if ( empty( $data['hostname'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_host_no_hostname_provided'];
        }
        if ( strlen( $data['port'] ) == 0 ) {
            $errorList[] = $PALANG['pAutoconfig_host_no_port_provided'];
        } elseif ( !preg_match( '/^\d+$/', $data['port'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_host_port_is_not_an_integer'];
        }
        if ( !empty( $data['socket_type'] ) && !preg_match( '/^SSL|STARTTLS|TLS$/', $data['socket_type'] ) ) {
            $errorList[] = sprintf( $PALANG['pAutoconfig_host_invalid_socket_type'], $data['socket_type'] );
        }
        if ( empty( $data['auth'] ) ) {
            $data['auth'] = 'none';
        }
        // password-cleartext, password-encrypted (CRAM-MD5 or DIGEST-MD5), NTLM (Windows), GSSAPI (Kerberos), client-IP-address, TLS-client-cert, none, smtp-after-pop (for smtp), OAuth2
        elseif ( !preg_match( '/^(password-cleartext|password-encrypted|NTLM|GSSAPI|client-IP-address|TLS-client-cert|smtp-after-pop|oauth2|none)$/i', $data['auth'] ) ) {
            $errorList[] = sprintf( $PALANG['pAutoconfig_host_invalid_auth_scheme'], $data['auth'] );
        }
        // username may be blank in the case of authentication by ip for example
        $booleanProperties = array( 'leave_messages_on_server', 'download_on_biff' );
        $this->decode_boolean( $data, $booleanProperties );
        if ( strlen( $data['days_to_leave_messages_on_server'] ) > 0 && !preg_match( '/^\d+$/', $data['days_to_leave_messages_on_server'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_host_days_on_server_is_not_an_integer'];
        }
        if ( strlen( $data['check_interval'] ) > 0 && !preg_match( '/^\d+$/', $data['check_interval'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_host_check_interval_is_not_an_integer'];
        }
        
        if ( count( $errorList ) == 0 ) {
            $this->empty2null( $data );
            return( null );
        } else {
            return( $errorList );
        }
    }

    public function check_autoconfig_text_data(&$data) {
        global $PALANG;
        $errorList = [];
        if ( !empty( $data['id'] ) && !preg_match( '//', $data['id'] ) ) {
            $errorList[] = sprintf( $PALANG['pAutoconfig_text_id_is_not_an_integer'], $data['id'] );
        }
        if ( empty( $data['type'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_text_type_not_provided'];
        } elseif ( !preg_match( '/^(instruction|documentation)$/', $data['type'] ) ) {
            $errorList[] = sprintf( $PALANG['pAutoconfig_text_type_invalid'], $data['type'] );
        }
        if ( empty( $data['lang'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_text_lang_not_provided'];
        } elseif ( !preg_match( '/^[a-zA-Z]{2}$/', $data['lang'] ) ) {
            $errorList[] = sprintf( $PALANG['pAutoconfig_text_lang_invalid'], $data['lang'] );
        }
        if ( empty( $data['phrase'] ) ) {
            $errorList[] = $PALANG['pAutoconfig_text_text_not_provided'];
        }
        
        if ( count( $errorList ) == 0 ) {
            $this->empty2null( $data );
            return( null );
        } else {
            return( $errorList );
        }
    }
    
    // Set the boolean value for web
    private function encode_boolean(&$data, $booleanProperties) {
        foreach ( $booleanProperties as $prop ) {
            if ( strlen( $data[ $prop ] ) > 0 ) {
                $data[ $prop ] = ( $data[ $prop ] == true ? 1 : 0 );
            }
        }
        return( $data );
    }

    private function decode_boolean(&$data, $booleanProperties) {
        foreach ( $booleanProperties as $prop ) {

            if ( strlen( $data[ $prop ] ) > 0 ) {
                $data[ $prop ] = db_get_boolean( $data[ $prop ] == 1 ? true : false );
            }
            // Remove the boolean field since it is null. Null is different from false
            else {
                // unset( $data[ $prop ] );
                $data[ $prop ] = db_get_boolean( false );
            }
        }
        // Since we are dealing with data reference we should not need to return anything, but just in case.
        return( $data );
    }
    
    // Set to null empty strings, so they can be stored as NULL in sql
    private function empty2null(&$data) {
        foreach ( $data as $key => $val ) {
            if ( empty( $val ) && $val !== 0 ) {
                $data[ $key ] = null;
            }
        }
    }
};
