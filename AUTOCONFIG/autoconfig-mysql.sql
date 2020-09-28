/*
Created on 2020-03-07
Copyright 2020 Jacques Deguest
Distributed under the same licence as Postfix Admin
*/
CREATE TABLE IF NOT EXISTS autoconfig (
	 id									SERIAL
	 -- https://stackoverflow.com/questions/43056220/store-uuid-v4-in-mysql
	,config_id							CHAR(36) NOT NULL
	-- If you prefer you can also use CHAR(36)
	-- ,config_id							CHAR(36) NOT NULL
	,encoding							VARCHAR(12)
	,provider_id						VARCHAR(255) NOT NULL
	-- Nice feature but not enough standard across other db. Instead, we'll use a separate table
	-- ,provider_domain					VARCHAR(255)[]
	,provider_name						VARCHAR(255)
	,provider_short						VARCHAR(120)
	-- enable section
	,enable_status						BOOLEAN
	,enable_url							VARCHAR(2048)
	-- documentation section
	,documentation_status				BOOLEAN
	,documentation_url					VARCHAR(2048)
	,webmail_login_page					VARCHAR(2048)
	-- webmail login page info
	,lp_info_url						VARCHAR(2048)
	,lp_info_username					VARCHAR(255)
	,lp_info_username_field_id			VARCHAR(255)
	,lp_info_username_field_name		VARCHAR(255)
	,lp_info_password_field				VARCHAR(255)
	,lp_info_login_button_id			VARCHAR(255)
	,lp_info_login_button_name			VARCHAR(255)
	-- Mac Mail specific fields
	,account_name						VARCHAR(255)
	-- Typically 'email'
	,account_type						VARCHAR(42)
	-- could be empty or could be a placeholder like %EMAILADDRESS%
	,email								VARCHAR(255)
	-- If not explicitly set, this will be guessed from host socket_type
	,ssl_enabled						BOOLEAN
	-- Will be empty obviously unless the user enters it in the form
	-- password may be provided by the user on the web interface, but not stored here
	-- Used for payload_description
	,description						TEXT
	,organisation						VARCHAR(255)
	-- payload type : regular account, or Microsoft Exchange, e.g. com.apple.mail.managed for mail account or com.apple.eas.account for exchange server
	,payload_type						VARCHAR(100)
	,prevent_app_sheet					BOOLEAN
	,prevent_move						BOOLEAN
	,smime_enabled						BOOLEAN
	,payload_remove_ok					BOOLEAN
	-- Outlook specific fields
	-- domain_required -> Not sure this should be an option; false by default
	,spa								BOOLEAN
	-- payload_enabled
	,active								BOOLEAN
	-- For signing of the Mac/iOS mobileconfig settings
	-- none, local or global
	-- none: do not sign
	-- local: use this configuration's certificate information
	-- global: use the server wide one in config.inc.php
	,sign_option						VARCHAR(7)
	,cert_filepath						VARCHAR(1024)
	,privkey_filepath					VARCHAR(1024)
	,chain_filepath						VARCHAR(1024)
	,created							TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	,modified							TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	,CONSTRAINT pk_autoconfig PRIMARY KEY (id)
	,CONSTRAINT idx_autoconfig UNIQUE (config_id)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS autoconfig_domains (
     id									SERIAL
	,config_id							CHAR(36) NOT NULL
	-- COLLATE here is crucial for the foreign key to work. It must be the same as the target
    ,domain								VARCHAR(255) NOT NULL COLLATE latin1_general_ci
    ,CONSTRAINT pk_autoconfig_domains PRIMARY KEY (id)
    ,CONSTRAINT idx_autoconfig_domains UNIQUE (config_id, domain)
    ,CONSTRAINT fk_autoconfig_domains_domain FOREIGN KEY (domain) REFERENCES domain(domain) ON DELETE CASCADE
    ,CONSTRAINT fk_autoconfig_domains_config_id FOREIGN KEY (config_id) REFERENCES autoconfig(config_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS autoconfig_hosts (
	 id									SERIAL
	,config_id							CHAR(36) NOT NULL
	-- imap, smtp, pop3
	,type								VARCHAR(12) NOT NULL
	,hostname							VARCHAR(255) NOT NULL
	,port								INTEGER NOT NULL
	,socket_type						VARCHAR(42)
	,auth								VARCHAR(42) DEFAULT 'none' NOT NULL
	-- possibly to contain some placeholder like %EMAILADDRESS%
	,username							VARCHAR(255)
	,leave_messages_on_server			BOOLEAN DEFAULT FALSE
	,download_on_biff					BOOLEAN DEFAULT FALSE
	,days_to_leave_messages_on_server	INTEGER
	,check_interval						INTEGER
	,priority							INTEGER
	,CONSTRAINT pk_autoconfig_hosts PRIMARY KEY (id)
	,CONSTRAINT idx_autoconfig_hosts UNIQUE (config_id, type, hostname, port)
    ,CONSTRAINT fk_autoconfig_hosts_config_id FOREIGN KEY (config_id) REFERENCES autoconfig(config_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS autoconfig_text (
	 id									SERIAL
	,config_id							CHAR(36) NOT NULL
	-- instruction or documentation
	,type								VARCHAR(17) NOT NULL
	-- iso 639 2-letters code
	,lang								CHAR(2) NOT NULL
	,phrase								TEXT
	,CONSTRAINT pk_autoconfig_text PRIMARY KEY (id)
	,CONSTRAINT idx_autoconfig_text UNIQUE (config_id, type, lang)
    ,CONSTRAINT fk_autoconfig_text_config_id FOREIGN KEY (config_id) REFERENCES autoconfig(config_id) ON DELETE CASCADE
) ENGINE = InnoDB;

