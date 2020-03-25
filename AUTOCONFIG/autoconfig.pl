#!/usr/bin/env perl
## !/usr/local/bin/perl
## Created on 2020-03-04
## Copyright 2020 Jacques Deguest
## Distributed under the same licence as Postfix Admin
BEGIN
{
	use strict;
	use IO::File;
	use CGI qw( :standard );
	use Email::Valid;
	use Email::Address;
	use XML::LibXML;
	use XML::LibXML::PrettyPrint;
	use Data::Dumper;
	use Scalar::Util;
	use Data::UUID;
	use File::Basename ();
	use Cwd ();
	use File::Temp ();
	use File::Spec ();
	use File::Which ();
	use JSON;
	use DBI;
	use TryCatch;
	use Devel::StackTrace;
};

{
	our $DEBUG = 0;
	our $POSTFIXADMIN_CONF_FILE = File::Basename::dirname( __FILE__ ) . '/../config.inc.php';
	my $tmpdir = File::Spec->tmpdir();
	our $POSTFIXADMIN_PERL_FILE = "$tmpdir/autoconfig.pl";
	
	our $ERROR = 0;
	use utf8;
	our $out = IO::File->new();
	$out->fdopen( fileno( STDOUT ), 'w' );
	$out->binmode( ":utf8" );
	$out->autoflush( 1 );
	our $err = IO::File->new();
	$err->fdopen( fileno( STDERR ), 'w' );
	$err->binmode( ":utf8" );
	$err->autoflush( 1 );
	our $out_xml = IO::File->new();
	$out_xml->fdopen( fileno( STDOUT ), 'w' );
	$out_xml->autoflush( 1 );
	
	our $params =
	{
	with_prefix => 0,
	include_comment => 1,
	include_top_tag => 1,
	lowercase => 1,
	};
	
	our $q = CGI->new;
	our( $form, $post_data );
	$form = $q->Vars;
	our $email;
	
	our $dbh;
	
	$err->print( "Available drivers: '", join( "', '", DBI->available_drivers ), "'\n" ) if( $DEBUG );
	
	$err->print( "Reading config file $POSTFIXADMIN_CONF_FILE to $POSTFIXADMIN_PERL_FILE\n" ) if( $DEBUG );
	our $CONF = &read_config_file({ config_file => $POSTFIXADMIN_CONF_FILE, perl_config => $POSTFIXADMIN_PERL_FILE });
	try
	{
		## Not including database_password, because password can be blank
		my @required = qw( database_type database_name );
		push( @required, qw( database_host database_user ) ) if( $CONF->{database_type} eq 'mysql' || $CONF->{database_type} eq 'mysql' || $CONF->{database_type} eq 'pgsql' );
		foreach my $prop ( @required	 )
		{
			if( !$CONF->{ $prop } )
			{
				die( "Property $prop is not set in $POSTFIXADMIN_CONF_FILE\n" );
			}
		}
		my $dsn;
		$err->print( "Database type is: $CONF->{database_type}\n" ) if( $DEBUG );
		if( $CONF->{database_type} eq 'mysql' || $CONF->{database_type} eq 'mysqli' )
		{
			require DBD::mysql;
			my @opts = ( 'database=' . $CONF->{database_name} );
			push( @opts, 'host=' . $CONF->{database_host} ) if( $CONF->{database_host} );
			push( @opts, 'port=' . $CONF->{database_port} ) if( $CONF->{database_port} );
			$dsn = sprintf( 'dbi:mysql:%s', join( ';', @opts ) );
		}
		elsif( $CONF->{database_type} eq 'pgsql' )
		{
			require DBD::Pg;
			my @opts = ( 'dbname=' . $CONF->{database_name} );
			push( @opts, 'host=' . $CONF->{database_host} ) if( $CONF->{database_host} );
			push( @opts, 'port=' . $CONF->{database_port} ) if( $CONF->{database_port} );
			$dsn = sprintf( 'dbi:Pg:%s', join( ';', @opts ) );
		}
		elsif( $CONF->{database_type} eq 'sqlite' )
		{
			require DBD::SQLite;
			my @opts = ( 'dbname=' . $CONF->{database_name} );
			$dsn = sprintf( 'dbi:SQLite:%s', join( ';', @opts ) );
		}
		else
		{
			die( "Unknown database type \"$CONF->{database_type}\"\n" );
		}
		$dbh = DBI->connect( $dsn, $CONF->{database_user}, $CONF->{database_password}, { RaiseError => 0 } ) || die( "Unable to connect to database server with dsn \"$dsn\": ", DBI->errstr, "\n" );
		$dbh->{ShowErrorStatement} = 1;
		$dbh->{HandleError} = sub
		{
			my $err = shift( @_ );
			my $trace = Devel::StackTrace->new( skip_frames => 1, indent => 1 );
			bailout( "$err\n" . $trace->as_string );
		};
		## This app is read-only to the database
		$dbh->{ReadOnly} = 1;
	}
	catch( $e )
	{
		die( $e );
	}
	
	## May be not provided in the case of Outlook
	if( $form->{emailaddress} )
	{
		if( !Email::Valid->address( $form->{emailaddress} ) )
		{
			$form->{emailaddress} = '';
		}
		else
		{
			my @emails = Email::Address->parse( $form->{emailaddress} );
			$email = $emails[0] if( scalar( @emails ) );
		}
	}
	
	my $host = '';
	if( $email )
	{
		$host = $email->host;
	}
	elsif( $form->{domain} )
	{
		$host = $form->{domain};
	}
	elsif( $ENV{HTTP_HOST} )
	{
		$host = $ENV{HTTP_HOST};
	}
	
	our $data = {};
	my $xml;
	# my $mime_type = 'application/xml';
	my $mime_type = 'text/xml';
	my $download = 0;
	my $filename = '';
	## Outlook makes a post request with a xml payload including an e-mail address
	if( $form->{outlook} || $q->request_method eq 'POST' )
	{
		$err->print( "Received an outlook request.\n" ) if( $DEBUG );
		my $req_xml;
		my $hash = {};
		unless( $form->{emailaddress} )
		{
			## For debugging in CLI
			if( -t( STDIN ) && !$ENV{HTTP_HOST} )
			{
				my $io = IO::File->new( "<$form->{request}" ) || die( "Unable to open request file \"$form->{request}\": $!\n" );
				$req_xml = join( '', $io->getlines );
				$io->close;
				## $err->print( "xml request is:\n$req_xml\n" );
			}
			else
			{
				$req_xml = $q->param('POSTDATA');
			}
			my $dom = XML::LibXML->load_xml( string => $req_xml );
			$hash = &xml2hash( $dom );
		}
		
		if( !$hash->{autodiscover}->{request}->{emailaddress} && !$form->{emailaddress} )
		{
			bailout( "No email found in request." );
		}
		else
		{
			$form->{emailaddress} ||= $hash->{autodiscover}->{request}->{emailaddress};
			$err->print( "E-mail address found in outlook request is: \"$form->{emailaddress}\"\n" ) if( $DEBUG );
			## $err->printf( "Ok, found e-mail \"%s\"\n", $form->{emailaddress} );
			if( !Email::Valid->address( $form->{emailaddress} ) )
			{
				## $err->print( "E-mail address $form->{emailaddress} is invalid\n" );
				$form->{emailaddress} = '';
				$data = get_config_for_host( $host ) || bailout( "Unable to get configuration data for host \"$host\": $ERROR" );
			}
			else
			{
				my @emails = Email::Address->parse( $form->{emailaddress} );
				$email = $emails[0] if( scalar( @emails ) );
				$host  = $email->host || $ENV{HTTP_HOST};
				$err->print( "Getting configuration for outlook for host \"$host\"\n" ) if( $DEBUG );
				$data = get_config_for_host( $host ) || bailout( "Unable to get configuration data for host \"$host\": $ERROR" );
			}
		}
		$xml = &generate_outlook();
	}
	# http://www.rootmanager.com/iphone-ota-configuration/iphone-ota-setup-with-signed-mobileconfig.html
	# <IfModule mod_mime.c>
	# 	AddType application/x-apple-aspen-config .mobileconfig
	# </IfModule>
	elsif( $form->{mac_mail} )
	{
		$data = get_config_for_host( $host ) || bailout( "Unable to get configuration data for host \"$host\": $ERROR" );
		$xml = &generate_mac_mail();
		$mime_type = 'application/x-apple-aspen-config';
		$download++;
		$filename = sprintf( '%s.mobileconfig', $data->{provider_id} );
	}
	else
	{
		$data = get_config_for_host( $host ) || bailout( "Unable to get configuration data for host \"$host\": $ERROR" );
		$xml = &generate_thunderbird();
	}
	# $out->print( "Content-Type: $mime_type\n\n" );
	# $out->printf( "Content-Disposition: attachment;filename=%s.mobileconfig\n", $data->{provider_id} );
	if( $download && $filename )
	{
		my $cert_ref = {};
		if( $CONF->{autoconfig_sign} && $data->{sign_option} ne 'none' )
		{
			## Check the path to openssl
			my $openssl;
			if( !defined( $openssl = File::Which::which( 'openssl' ) ) )
			{
				bailout( "Unable to find the openssl binary anywhere in the PATH: ", join( ',', File::Spec->path() ) );
			}
			elsif( $DEBUG )
			{
				$err->print( "Ok, found openssl at $openssl\n" );
			}
			my @keys = qw( cert_filepath privkey_filepath chain_filepath );
			if( $data->{sign_option} eq 'local' && 
				$data->{cert_filepath} && 
				$data->{privkey_filepath} && 
				$data->{chain_filepath} )
			{
				@$cert_ref{ @keys } = @$data{ @keys };
			}
			elsif( ( $data->{sign_option} eq 'global' || !length( $data->{sign_option} ) ) && 
				$CONF->{autoconfig_cert} && 
				$CONF->{autoconfig_privkey} && 
				$CONF->{autoconfig_chain} )
			{
				my @keys_global = qw( autoconfig_cert autoconfig_privkey autoconfig_chain );
				@$cert_ref{ @keys } = @$CONF{ @keys_global };
			}
			# $err->print( Data::Dumper::Dumper( $cert_ref ), "\n" ); exit;
			## Do we have anything? Check the file path
			if( scalar( keys( %$cert_ref ) ) )
			{
				foreach my $k ( keys( %$cert_ref ) )
				{
					my $f = $cert_ref->{ $k };
					## It's a symbolic link. Resolve it
					if( -l( $f ) )
					{
						$err->print( "File \"$f\" is a symbolic link. Resolving it.\n" ) if( $DEBUG );
						my $f2;
						if( !defined( $f2 = Cwd::abs_path( $f ) ) )
						{
							bailout( "Unable to resolve the symbolic link \"$f\": $!" );
							$f = $f2;
						}
						else
						{
							$err->print( "Ok, resolved link is \"$f2\".\n" ) if( $DEBUG );
						}
					}
					if( !-e( $f ) )
					{
						bailout( "File \"$f\" does not exist." );
					}
					elsif( !-r( $f ) )
					{
						bailout( sprintf( "File \"$f\" does not have read permission for uid $>. Current permissions are: %04o", (stat( $f ))[2] & 07777 ) );
					}
				}
			}
			## By now, we are good and have everything
			my $xml_in = File::Temp->new( SUFFIX => '.mobileconfig' );
			my $mobile_config_file = $xml_in->filename;
			$xml_in->print( $xml->toString() );
			
			my $fh = File::Temp->new( SUFFIX => '.mobileconfig' );
			my $mobile_config_file_out = $fh->filename;
			my $res;
			# openssl smime \
			# -sign \
			# -signer your-cert.pem \
			# -inkey your-priv-key.pem \
			# -certfile TheCertChain.pem \
			# -nodetach \
			# -outform der \
			# -in ConfigProfile.mobileconfig \
			# -out ConfigProfile_signed.mobileconfig
			## https://www.steveneppler.com/blog/2011/02/09/signing-ios-mobileconfig-files-with-your-certificate
			$out->print( $q->header(
				-type => $mime_type,
				-content_disposition => "attachment;filename=${filename}",
				-expires => 'now',
			) );
			## Failed to sign it. Log an error on stderr and send out the unsigned version
			$err->print( "Executing the following command to sign the payload:\n$openssl smime -sign -signer $cert_ref->{cert_filepath} -inkey $cert_ref->{privkey_filepath} -certfile $cert_ref->{chain_filepath} -nodetach -outform der -in $mobile_config_file -out $mobile_config_file_out\n" ) if( $DEBUG );
			if( !defined( qx( $openssl smime -sign -signer $cert_ref->{cert_filepath} -inkey $cert_ref->{privkey_filepath} -certfile $cert_ref->{chain_filepath} -nodetach -outform der -in $mobile_config_file -out $mobile_config_file_out ) ) )
			{
				$err->print( "Unable to sign the mobileconfig file $mobile_config_file. An error occured when running the openssl command with binary at $openssl\n" );
				$out_xml->print( $xml->toString(), "\n" );
				exit( 0 );
			}
			chmod( 0600, $mobile_config_file_out );
			my $in = IO::File->new( "<$mobile_config_file_out" ) || bailout( "Unable to open signed mobileconfig file \"$mobile_config_file_out\": $!" );
			$in->binmode;
			my $bin_out = IO::File->new();
			$bin_out->fdopen( fileno( STDOUT ), 'w' );
			$bin_out->binmode();
			$bin_out->autoflush( 1 );
			while( defined( $bytes = $in->getline ) )
			{
				$bin_out-print( $bytes );
			}
			$in->close;
			exit( 0 );
		}
		else
		{
			$err->print( "Aucoconfig signature is not activated globally ($CONF->{autoconfig_sign}) or locally ($data->{sign_option}). Returning data in command line.\n" ) if( $DEBUG );
			$out->print( $q->header(
				-type => $mime_type,
				-content_disposition => "attachment;filename=${filename}",
				-expires => 'now',
				-charset => 'utf-8',
			) );
			$out_xml->print( $xml->toString(), "\n" );
		}
	}
	else
	{
		if( -t( STDIN ) && !$ENV{HTTP_HOST} )
		{
			$err->print( "Returning data in command line.\n" ) if( $DEBUG );
			my $pretty = XML::LibXML::PrettyPrint->new(
				indent_string => ' ' x 4,
			);
			my $pretty_xml = $pretty->pretty_print( $xml );
			$out_xml->print( $pretty_xml, "\n" );
		}
		else
		{
			$err->print( "Returning data to http client $ENV{HTTP_USER_AGENT}.\n" ) if( $DEBUG );
			$out->print( $q->header(
				-type => $mime_type,
				-expires => 'now',
				-charset => 'utf-8',
			) );
			$out_xml->print( $xml->toString(), "\n" );
		}
	}
	exit( 0 );
}

sub bailout
{
	my $error = join( '', @_ );
	$out->print( $q->header(
		-type => 'text/plain',
		-status => "500 Internal Server Error",
		-expires => 'now',
		-charset => 'utf-8',
	) );
	$out->print( "An unexpected error has occured. Please try again later.\n" );
	## Print to stderr to log it to web server log file
	$err->print( "$error\n" );
	exit( 0 );
}

# https://stackoverflow.com/questions/44373314/how-do-i-create-entity-references-in-the-doctype-using-perl-libxml
sub generate_mac_mail
{
	my $property_map =
	{
	provider_name		=> 'EmailAccountDescription',
	account_name		=> 'EmailAccountName',
	account_type		=> 'EmailAccountType',
	email				=> 'EmailAddress',
	incoming_server =>
		[
		auth			=> 'IncomingMailServerAuthentication',
		hostname		=> 'IncomingMailServerHostName',
		port			=> 'IncomingMailServerPortNumber',
		ssl_enabled		=> 'IncomingMailServerUseSSL',
		username		=> 'IncomingMailServerUsername',
		password		=> 'IncomingPassword',
		],
	outgoing_server =>
		[
		auth			=> 'OutgoingMailServerAuthentication',
		hostname		=> 'OutgoingMailServerHostName',
		port			=> 'OutgoingMailServerPortNumber',
		ssl_enabled		=> 'OutgoingMailServerUseSSL',
		username		=> 'OutgoingMailServerUsername',
		password		=> 'OutgoingPassword',
		],
	same_password		=> 'OutgoingPasswordSameAsIncomingPassword',
	payload_description	=> 'PayloadDescription',
	payload_name		=> 'PayloadDisplayName',
	payload_id			=> 'PayloadIdentifier',
	payload_org			=> 'PayloadOrganization',
	## com.apple.mail.managed
	payload_type		=> 'PayloadType',
	payload_uuid		=> 'PayloadUUID',
	payload_version		=> 'PayloadVersion',
	prevent_app_sheet	=> 'PreventAppSheet',
	prevent_move		=> 'PreventMove',
	smime_enabled		=> 'SMIMEEnabled',
	payload_remove_ok	=> 'PayloadRemovalDisallowed',
	payload_enabled		=> 'PayloadEnabled',
	};
	## password-cleartext, password-encrypted (CRAM-MD5 or DIGEST-MD5), NTLM (Windows), GSSAPI (Kerberos), client-IP-address, TLS-client-cert, none, smtp-after-pop (for smtp), OAuth2 (gmail)
	my $auth_map =
	{
	'none'					=> 'EmailAuthNone',
	'password-cleartext'	=> 'EmailAuthPassword',
	'password-encrypted'	=> 'EmailAuthCRAMMD5',
	'smtp-after-pop'		=> '',
	'client-ip-address'		=> '',
	'ntlm'					=> 'EmailAuthNTLM',
	'tls-client-cert'		=> '',
	## Made that one up. Wild guess...
	'oauth2'				=> 'EmailAuthOauth2',
	};
	my $boolean_properties =
	{
	ssl_enabled			=> 1,
	};
	my $integer_properties =
	{
	port => 1,
	};
	
	my $server_type_map =
	{
	imap	=> 'EmailTypeIMAP',
	pop3	=> 'EmailTypePOP',
	};
	my $doc = XML::LibXML::Document->new( '1.0', $data->{encoding} );
	local $add_elements = sub
	{
		my $key = shift( @_ );
		my $val = shift( @_ );
		my $p = {};
		if( ref( $_[0] ) eq 'HASH' )
		{
			$p = shift( @_ );
		}
		elsif( @_ && !( @_ % 2 ) )
		{
			$p = { @_ };
		}
		my $type2prop =
		{
		string => 'string',
		integer => 'integer',
		boolean => 'boolean',
		};
		my $keyProp = $doc->createElement( 'key' );
		$keyProp->appendText( $key );
		my $valProp;
		if( $p->{type} eq 'boolean' )
		{
			$valProp = $doc->createElement( $val ? 'true' : 'false' );
		}
		else
		{
			$valProp = $doc->createElement( $p->{type} );
			$valProp->appendText( $val );
		}
		if( $p->{parent} )
		{
			$p->{parent}->addChild( $keyProp );
			$p->{parent}->addChild( $valProp );
		}
		return({ key => $keyProp, value => $valProp });
	};
	
	my $dtd = $doc->createInternalSubset( 'plist', "-//Apple//DTD PLIST 1.0//EN", "http://www.apple.com/DTDs/PropertyList-1.0.dtd" );
	my $plist = $doc->createElement( 'plist' );
	$plist->setAttribute( version => 1 );
	my $dict = $doc->createElement( 'dict' );
	$plist->addChild( $dict );
	my $def = {};
	
	$data->{payload_uuid} ||= &_generate_uuid();
	$data->{payload_enabled} = 1;
	$def = $add_elements->( $property_map->{payload_uuid} => $data->{payload_uuid}, { type => 'string', parent => $dict } );
	## $def = $add_elements->( $property_map->{payload_type} => ( $data->{payload_type} || 'Configuration' ), { type => 'string', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_type} => 'Configuration', { type => 'string', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_org} => ( $data->{payload_org} || $data->{provider_name} ), { type => 'string', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_id} => $data->{payload_uuid}, { type => 'string', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_name} => ( $data->{payload_name} || $data->{provider_short} || 'Mail Account Proflie' ), { type => 'string', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_description} => ( $data->{payload_description} || 'Mail Account Settings' ), { type => 'string', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_version} => ( $data->{payload_version} || 1 ), { type => 'integer', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_enabled} => $data->{payload_enabled}, { type => 'boolean', parent => $dict } );
	$def = $add_elements->( $property_map->{payload_remove_ok} => $data->{payload_remove_ok}, { type => 'boolean', parent => $dict } );
	$plist->addChild( $dict );
	
	my $payloadContentKey = $doc->createElement( 'key' );
	$payloadContentKey->appendText( 'PayloadContent' );
	$dict->addChild( $payloadContentKey );
	my $array = $doc->createElement( 'array' );
	my $srv = $doc->createElement( 'dict' );
	$def = {};
	
	## We need a separate uuid for the server details
	my $payload_uuid = &_generate_uuid();
	$def = $add_elements->( $property_map->{payload_uuid} => $payload_uuid, { type => 'string', parent => $srv } );
# 	$def = $add_elements->( $property_map->{payload_type} => 'com.apple.eas.account', { type => 'string', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_type} => 'com.apple.mail.managed', { type => 'string', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_org} => &_interpolate_vars_thunderbird( $data->{payload_org} || $data->{provider_name} ), { type => 'string', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_id} => $payload_uuid, { type => 'string', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_name} => &_interpolate_vars_thunderbird( $data->{payload_name} || sprintf( "%s Account (%s)", uc( $data->{incoming_server}->[0]->{type} ), $data->{provider_name} ) ), { type => 'string', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_description} => &_interpolate_vars_thunderbird( $data->{payload_description} || "Mail Account Settings" ), { type => 'string', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_version} => ( $data->{payload_version} || 1 ), { type => 'integer', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_enabled} => $data->{payload_enabled}, { type => 'boolean', parent => $srv } );
	$def = $add_elements->( $property_map->{payload_name} => ( $data->{payload_name} || $data->{provider_short} || 'Mail Account Proflie' ), { type => 'string', parent => $srv } );
	
	
	if( $data->{provider_name} )
	{
		$def = $add_elements->( $property_map->{provider_name} => &_interpolate_vars_thunderbird( $data->{provider_name} ), { type => 'string', parent => $srv } );
	}
	if( !$data->{account_name} && !$form->{account_name} && $email )
	{
		my $local = $email->user;
		my @parts = split( /\./, $local );
		my $name = join( ' ', map( ucfirst( lc( $_ ) ), @parts ) );
		$data->{account_name} = $name;
	}
	if( $data->{account_name} || $form->{account_name} )
	{
		$def = $add_elements->( $property_map->{account_name} => &_interpolate_vars_thunderbird( ( $data->{account_name} || $form->{account_name} ) ), { type => 'string', parent => $srv } );
	}
	if( ( $data->{incoming_server} && 
		ref( $data->{incoming_server} ) eq 'ARRAY' && 
		scalar( @{$data->{incoming_server}} ) ) ||
		( $data->{outgoing_server} &&
		ref( $data->{outgoing_server} ) eq 'ARRAY' &&
		scalar( @{$data->{outgoing_server}} ) ) )
	{
		## $def = $add_elements->( email => ( $email ? $email->address : 'taro.urashima@' . $data->{provider_domain}->[0] ), { type => 'string', parent => $srv } );
		$def = $add_elements->( $property_map->{email} => ( $email ? $email->address : '' ), { type => 'string', parent => $srv } );
		foreach my $t ( qw( incoming_server outgoing_server ) )
		{
			## Of course there should be an incoming and outgoing server, but since we rely on the data being here, we check its existence and skip it if not there to avoid an untrapped error
			if( !$data->{ $t } )
			{
				next;
			}
			my $srv_conf = $data->{ $t }->[0];
			$def = $add_elements->( $property_map->{account_type} => $server_type_map->{ $srv_conf->{type} }, { type => 'string', parent => $srv } ) if( $t eq 'incoming_server' );
			$srv_conf->{ssl_enabled} = $srv_conf->{socket_type} =~ /^(SSL|STARTTLS|TLS)$/i ? 1 : 0;
			for( my $i = 0; $i < scalar( @{$property_map->{ $t }} ); $i += 2 )
			{
				my $src_prop = $property_map->{ $t }->[$i];
				my $tar_prop = $property_map->{ $t }->[$i + 1];
				## $err->print( "Processing property \"$tar_prop\" with value \"", $srv_conf->{ $src_prop }, "\"\n" );
				if( $src_prop =~ /^[[:blank:]]*$/ )
				{
					warn( "No source property defined for server type \"$t\" with host \"$ref->{hostname}\" !\n" );
					next;
				}
				if( $tar_prop =~ /^[[:blank:]]*$/ )
				{
					warn( "No target property defined for server type \"$t\" with host \"$ref->{hostname}\" !\n" );
					next;
				}
				
				## Check if password exists and is same as previous one in incoming_server
				if( $t eq 'outgoing_server' && 
					$src_prop eq 'password' &&
					$data->{incoming_server}->[0]->{password} eq $data->{outgoing_server}->[0]->{password} )
				{
					$def = $add_elements->( $property_map->{same_password} => 1, { type => 'boolean', parent => $srv } );
					next;
				}
				
				if( $src_prop eq 'auth' )
				{
					$srv_conf->{ $src_prop } = $auth_map->{ $srv_conf->{ $src_prop } };
				}
				
				if( exists( $boolean_properties->{ $src_prop } ) )
				{
					$def = $add_elements->( $tar_prop => $srv_conf->{ $src_prop }, { type => 'boolean', parent => $srv } );
				}
				elsif( exists( $integer_properties->{ $src_prop } ) )
				{
					$srv_conf->{ $src_prop } = 0 if( !CORE::length( $srv_conf->{ $src_prop } ) );
					$def = $add_elements->( $tar_prop => $srv_conf->{ $src_prop }, { type => 'integer', parent => $srv } );
				}
				else
				{
					$def = $add_elements->( $tar_prop => &_interpolate_vars_thunderbird( $srv_conf->{ $src_prop } ), { type => 'string', parent => $srv } );
				}
			}
		}
	}
	$def = $add_elements->( $property_map->{prevent_app_sheet} => $data->{prevent_app_sheet}, { type => 'boolean', parent => $srv } );
	$def = $add_elements->( $property_map->{prevent_move} => $data->{prevent_move}, { type => 'boolean', parent => $srv } );
	$def = $add_elements->( $property_map->{smime_enabled} => $data->{smime_enabled}, { type => 'boolean', parent => $srv } );
	$array->addChild( $srv );
	$dict->addChild( $array );
	$doc->addChild( $plist );
	return( $doc );
}

## https://www.ullright.org/ullWiki/show/providing-email-client-autoconfiguration-information-from-moens-ch
sub generate_outlook
{
	my $property_map =
	{
	server =>
		[
		hostname		=> 'Server',
		port			=> 'Port',
		domain_required => 'DomainRequired',
		spa				=> 'SPA',
		ssl_enabled		=> 'SSL',
		auth_required 	=> 'AuthRequired',
		username		=> 'LoginName',
		],
	};
	
	my $boolean_properties =
	{
	auth_required	=> 1,
	domain_required	=> 1,
	spa				=> 1,
	ssl_enabled		=> 1,
	};
	my $doc = XML::LibXML::Document->new( '1.0', $data->{encoding} );
	my $disco = $doc->createElement( 'Autodiscover' );
	$disco->setAttribute( xmlns => 'http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006' );
	my $resp = $doc->createElement( 'Response' );
	$resp->setAttribute( xmlns => 'http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a' );
	my $provider = $doc->createElement( 'User' );
	
	my $providerName = $doc->createElement( 'DisplayName' );
	$providerName->appendText( &_interpolate_vars_thunderbird( $data->{provider_name} ) );
	$provider->addChild( $providerName );
	$resp->addChild( $provider );
	
	my $acct = $doc->createElement( 'Account' );
	
	my $acctType = $doc->createElement( 'AccountType' );
	$acctType->appendText( 'email' );
	$acct->addChild( $acctType );
	
	my $settings = $doc->createElement( 'Action' );
	$settings->appendText( 'settings' );
	$acct->addChild( $settings );
	
	foreach my $t ( qw( incoming_server outgoing_server ) )
	{
		next if( !exists( $data->{ $t } ) );
		if( ref( $data->{ $t } ) ne 'ARRAY' )
		{
			warn( "Data provided for server type \"$t\" is not an array reference.\n" );
			next;
		}
		foreach my $ref ( @{$data->{ $t }} )
		{
			## Same property whether this is an incoming or outgoing mail server
			my $srv = $doc->createElement( 'Protocol' );
			# Generate some data if necessary
			if( !length( $ref->{ssl_enabled} ) )
			{
				if( $ref->{socket_type} =~ /^(?:SSL|STARTTLS|TLS)$/i )
				{
					$ref->{ssl_enabled} = 1;
				}
			}
			if( !length( $ref->{auth_required} ) )
			{
				$ref->{auth_required} = lc( $ref->{auth} ) eq 'none' ? 0 : 1;
			}
			if( !length( $ref->{domain_required} ) )
			{
				$ref->{domain_required} = 0;
			}
			if( !length( $ref->{spa} ) )
			{
				$ref->{spa} = 0;
			}
			
			for( my $i = 0; $i < scalar( @{$property_map->{server}} ); $i += 2 )
			{
				my $src_prop = $property_map->{server}->[$i];
				my $tar_prop = $property_map->{server}->[$i + 1];
				if( $src_prop =~ /^[[:blank:]]*$/ )
				{
					warn( "No source property defined for server type \"$t\" with host \"$ref->{hostname}\" !\n" );
					next;
				}
				if( $tar_prop =~ /^[[:blank:]]*$/ )
				{
					warn( "No target property defined for server type \"$t\" with host \"$ref->{hostname}\" !\n" );
					next;
				}
				
				if( $ref->{ $src_prop } =~ /^[[:blank:]]*$/ )
				{
					warn( "Property value for \"$src_prop\" for server type \"$t\" ($ref->{hostname}) is empty, skipping it.\n" );
					next;
				}
				if( exists( $boolean_properties->{ $src_prop } ) )
				{
					$ref->{ $src_prop } = $ref->{ $src_prop } ? 'on' : 'off';
				}
				elsif( $src_prop eq 'type' )
				{
					$ref->{ $src_prop } = uc( $ref->{ $src_prop } );
				}
				my $prop = $doc->createElement( $tar_prop );
				$prop->appendText( &_interpolate_vars_thunderbird( $ref->{ $src_prop } ) );
				$srv->addChild( $prop );
			}
			$acct->addChild( $srv );
		}
	}
	
	$resp->addChild( $acct );
	
	$disco->addChild( $resp );
	$doc->addChild( $disco );
	return( $doc );
}

## https://wiki.mozilla.org/Thunderbird:Autoconfiguration:ConfigFileFormat
sub generate_thunderbird
{
# 	local $Data::Dumper::Sortkeys = 1;
# 	$err->print( Data::Dumper::Dumper( $data ), "\n" );
# 	exit;
	my $property_map =
	{
	incoming_server => 'incomingServer',
	outgoing_server => 'outgoingServer',
	server =>
		[
		hostname	=> 'hostname',
		port		=> 'port',
		socket_type	=> 'socketType',
		auth		=> 'authentication',
		username	=> 'username',
		],
	leave_messages_on_server 			=> 'leaveMessagesOnServer',
	download_on_biff 					=> 'downloadOnBiff',
	days_to_leave_messages_on_server	=> 'daysToLeaveMessagesOnServer',
	check_interval 						=> 'checkInterval',
	webmail => 'webMail',
	login_page => 'loginPage',
	login_page_info => 'loginPageInfo',
	username => 'username',
	username_field => 'usernameField',
	password_field => 'passwordField',
	login_button => 'loginButton',
	};

	my $doc = XML::LibXML::Document->new( '1.0', $data->{encoding} );
	## <clientConfig version="1.1">
	my $config = $doc->createElement( 'clientConfig' );
	$config->setAttribute( version => '1.1' );
	$doc->addChild( $config );
	
	## Email provider
	my $provider = XML::LibXML::Element->new( 'emailProvider' );
	if( $data->{provider_id} )
	{
		$provider->setAttribute( id => &_interpolate_vars_thunderbird( $data->{provider_id} ) );
	}
	$config->addChild( $provider );
	
	if( $data->{provider_domain} && ref( $data->{provider_domain} ) )
	{
		foreach my $domain ( @{$data->{provider_domain}} )
		{
			next if( $domain =~ /^[[:blank:]]*$/ );
			my $providerDomain = $doc->createElement( 'domain' );
			$providerDomain->appendText( &_interpolate_vars_thunderbird( $domain ) );
			$provider->addChild( $providerDomain );
		}
	}
	
	if( $data->{provider_name} )
	{
		my $providerName = $doc->createElement( 'displayName' );
		$providerName->appendText( &_interpolate_vars_thunderbird( $data->{provider_name} ) );
		$provider->addChild( $providerName );
	}
	
	if( $data->{provider_short} )
	{
		my $providerShort = $doc->createElement( 'displayShortName' );
		$providerShort->appendText( &_interpolate_vars_thunderbird( $data->{provider_short} ) );
		$provider->addChild( $providerShort );
	}
	
	## Process incoming and outgoing servers
	foreach my $t ( qw( incoming_server outgoing_server ) )
	{
		next if( !exists( $data->{ $t } ) );
		if( ref( $data->{ $t } ) ne 'ARRAY' )
		{
			warn( "Data provided for server type \"$t\" is not an array reference.\n" );
			next;
		}
		foreach my $ref ( @{$data->{ $t }} )
		{
			my $srv = $doc->createElement( $property_map->{ $t } );
			$srv->setAttribute( type => $ref->{type} );
			for( my $i = 0; $i < scalar( @{$property_map->{server}} ); $i += 2 )
			{
				my $src_prop = $property_map->{server}->[$i];
				my $tar_prop = $property_map->{server}->[$i + 1];
				if( $src_prop =~ /^[[:blank:]]*$/ )
				{
					warn( "No source property defined for server type \"$t\" with host \"$ref->{hostname}\" !\n" );
					next;
				}
				if( $tar_prop =~ /^[[:blank:]]*$/ )
				{
					warn( "No target property defined for server type \"$t\" with host \"$ref->{hostname}\" !\n" );
					next;
				}
				
				if( $ref->{ $src_prop } =~ /^[[:blank:]]*$/ )
				{
					warn( "Property value for \"$src_prop\" for server type \"$t\" ($ref->{hostname}) is empty, skipping it.\n" );
					next;
				}
				$ref->{ $src_prop } = lc( $ref->{ $src_prop } ) if( $src_prop eq 'auth' );
				my $prop = $doc->createElement( $tar_prop );
				$prop->appendText( &_interpolate_vars_thunderbird( $ref->{ $src_prop } ) );
				$srv->addChild( $prop );
			}
			
			## If this is a pop3 and at least there is one pop3 property set, ie not null, we activate this block
			if( $ref->{type} eq 'pop3' && 
				( length( $ref->{leave_messages_on_server} ) ||
				  length( $ref->{download_on_biff} ) ||
				  length( $ref->{days_to_leave_messages_on_server} ) ||
				  length( $ref->{check_interval} )
				) )
			{
				my $pop3 = $doc->createElement( 'pop3' );
# 				leave_messages_on_server => 1,
# 				download_on_biff => 1,
# 				days_to_leave_messages_on_server => 14,
# 				check_interval => { minutes => 15 },
				foreach my $prop_name ( qw( leave_messages_on_server download_on_biff ) )
				{
					## Does the property exists and has a non blank value ?
					## A blank value could be construed as false, which is incorrect. False must be expressed with 0
					if( length( $ref->{ $prop_name } ) )
					{
						if( $ref->{ $prop_name } =~ /^[[:blank:]]*$/ )
						{
							warn( "Property \"$prop_name\" for this popr3 server \"$ref->{hostname}\" is blank and ignored.\n" );
							next;
						}
						my $prop = $doc->createElement( $property_map->{ $prop_name } );
						$prop->appendText( $ref->{ $prop_name } ? 'true' : 'false' );
						$pop3->addChild( $prop );
					}
				}
				foreach my $int_prop ( qw( days_to_leave_messages_on_server check_interval ) )
				{
					if( length( $ref->{ $int_prop } ) )
					{
						if( $ref->{ $int_prop } !~ /^\d+$/ )
						{
							warn( "The property \"$int_prop\" value for this pop3 server \"$ref->{hostname}\" (", $ref->{ $int_prop }, ") is not an integer and is ignored.\n" );
						}
						else
						{
							my $prop = $doc->createElement( $property_map->{ $int_prop } );
							if( $int_prop eq 'days_to_leave_messages_on_server' )
							{
								$prop->appendText( $ref->{ $int_prop } );
							}
							else
							{
								$prop->setAttribute( minutes => $ref->{ $int_prop } );
							}
							$pop3->addChild( $prop );
						}
					}
				}
				$srv->addChild( $pop3 );
			}
			$provider->addChild( $srv );
		}
	}

	if( exists( $data->{enable} ) && 
		( !length( $data->{enable_status} ) || ( length( $data->{enable_status} ) && $data->{enable_status} ) ) )
	{
		my $enable = $doc->createElement( 'enable' );
		## Not going to check this is a valid url. This is the responsibility of the user
		$enable->setAttribute( visiturl => $data->{enable}->{url} ) if( $data->{enable}->{url} );
		if( $data->{enable}->{instruction} && ref( $data->{enable}->{instruction} ) eq 'HASH' )
		{
			foreach my $lang ( sort( keys( %{$data->{enable}->{instruction}} ) ) )
			{
				if( $data->{enable}->{instruction}->{ $lang } =~ /^[[:blank:]]*$/ )
				{
					warn( "Instruction text to enable login for language \"$lang\" is empty, skipping\n" );
					next;
				}
				my $help = $doc->createElement( 'instruction' );
				$help->setAttribute( lang => $lang );
				$help->appendText( &_interpolate_vars_thunderbird( $data->{enable}->{instruction}->{ $lang } ) );
				$enable->addChild( $help );
			}
		}
		$provider->addChild( $enable );
	}
	
	if( $data->{documentation} && ref( $data->{documentation} ) eq 'HASH' && 
		( !length( $data->{documentation_status} ) || ( length( $data->{documentation_status} ) && $data->{documentation_status} ) ) )
	{
		my $support_data = $data->{documentation};
		my $support = $doc->createElement( 'documentation' );
		## Not going to check this is a valid url. This is the responsibility of the user
		$support->setAttribute( url => $support_data->{url} ) if( $support_data->{url} );
		if( $support_data->{description} && ref( $support_data->{description} ) eq 'HASH' )
		{
			foreach my $lang ( sort( keys( %{$support_data->{description}} ) ) )
			{
				if( $support_data->{description}->{ $lang } =~ /^[[:blank:]]*$/ )
				{
					warn( "Support documentation text for language \"$lang\" is empty, skipping\n" );
					next;
				}
				my $desc = $doc->createElement( 'descr' );
				$desc->setAttribute( lang => $lang );
				$desc->appendText( &_interpolate_vars_thunderbird( $support_data->{description}->{ $lang } ) );
				$support->addChild( $desc );
			}
		}
		$provider->addChild( $support );
	}
	
	if( $data->{webmail} && ref( $data->{webmail} ) eq 'HASH' )
	{
		my $webmail = $doc->createElement( $property_map->{webmail} );
		my $this = $data->{webmail};
		if( $this->{login_page} )
		{
			my $loginPage = $doc->createElement( $property_map->{login_page} );
			$loginPage->setAttribute( url => &_interpolate_vars_thunderbird( $this->{login_page} ) );
			$webmail->addChild( $loginPage );
		}
		if( $this->{login_page_info} && ref( $this->{login_page_info} ) eq 'HASH' )
		{
			my $ref = $this->{login_page_info};
			my $loginInfo = $doc->createElement( $property_map->{login_page_info} );
			$loginInfo->setAttribute( url => &_interpolate_vars_thunderbird( $ref->{url} ) ) if( $ref->{url} );
			if( $ref->{username} )
			{
				if( $email )
				{
					$ref->{username} = &_interpolate_vars_thunderbird( $ref->{username} );
				}
				my $username = $doc->createElement( $property_map->{username} );
				$username->appendText( &_interpolate_vars_thunderbird( $ref->{username} ) );
				$loginInfo->addChild( $username );
			}
			if( $ref->{username_field} && ref( $ref->{username_field} ) eq 'HASH' )
			{
				my $that = $ref->{username_field};
				my $usernameField = $doc->createElement( $property_map->{username_field} );
				$usernameField->setAttribute( id => &_interpolate_vars_thunderbird( $that->{id} ) ) if( $that->{id} );
				$usernameField->setAttribute( name => &_interpolate_vars_thunderbird( $that->{name} ) ) if( $that->{name} );
				$loginInfo->addChild( $usernameField );
			}
			if( $ref->{password_field} )
			{
				my $pwdField = $doc->createElement( $property_map->{password_field} );
				$pwdField->setAttribute( name => &_interpolate_vars_thunderbird( $ref->{password_field} ) );
				$loginInfo->addChild( $pwdField );
			}
			if( $ref->{login_button} && ref( $ref->{login_button} ) eq 'HASH' )
			{
				my $that = $ref->{login_button};
				my $loginButton = $doc->createElement( $property_map->{login_button} );
				$loginButton->setAttribute( id => &_interpolate_vars_thunderbird( $that->{id} ) ) if( $that->{id} );
				$loginButton->setAttribute( name => &_interpolate_vars_thunderbird( $that->{name} ) ) if( $that->{name} );
				$loginInfo->addChild( $loginButton );
			}
			$webmail->addChild( $loginInfo );
		}
		$config->addChild( $webmail );
	}
	## Get the overall xml as string and return it
	# my $xml = $doc->toString();
	return( $doc );
}

sub get_config_for_host
{
	my $host = shift( @_ ) || return( _error( "No host provided to get its configuration data." ) );
	my $sth = $dbh->prepare_cached( "SELECT c.* FROM autoconfig_domains d LEFT JOIN autoconfig c ON c.config_id = d.config_id WHERE d.domain = ?" ) || bailout( "Unable to prepare sql statement: ", $dbh->errstr );
	my @parts = split( /\./, $host );
	my $ref;
	for( my $i = 0; $i < scalar( @parts ); $i++ )
	{
		my $this_host = join( '.', @parts[$i..$#parts] );
		$sth->execute( $this_host ) || bailout( "An error occurred while trying to execute query: ", $sth->errstr );
		$ref = $sth->fetchrow_hashref;
		if( ref( $ref ) )
		{
			last if( !scalar( keys( %$ref ) ) );
			$ref->{domain} = $this_host;
			last;
		}
	}
	$sth->finish;
	return( _error( "No configuration data found for host $host" ) ) if( !$ref || ( ref( $ref ) && !scalar( keys( %$ref ) ) ) );
	
	my $dom_sth = $dbh->prepare_cached( "SELECT domain FROM autoconfig_domains WHERE config_id = ?" ) || bailout( "Unable to prepare sql statement to get all domains for this configurtion: ", $dbh->errstr );
	$dom_sth->execute( $ref->{config_id} ) || bailout( "An error occurred while trying to get the list of all domains for this configuration: ", $dom_sth->errstr );
	my $all_domains = $dom_sth->fetchall_arrayref( {} );
	$dom_sth->finish;
	my $domains = [map( $_->{domain}, @$all_domains )];
	$ref->{provider_domain} = $domains;
	
	my $hosts_in_sth = $dbh->prepare_cached( "SELECT * FROM autoconfig_hosts WHERE config_id = ? AND ( type = 'imap' OR type = 'pop3' ) ORDER BY priority" ) || bailout( "Unable to prepare the sql statements to get hosts configuration details for config id $ref->{config_id}: ", $dbh->errstr );
	$hosts_in_sth->execute( $ref->{config_id} ) || bailout( "An error occurred while trying to execute the sql query to get host details for config id $ref->{config_id}: ", $hosts_in_sth->errstr );
	my $all_hosts_in = $hosts_in_sth->fetchall_arrayref( {} );
	$hosts_in_sth->finish;
	$ref->{incoming_server} = $all_hosts_in;
	
	my $hosts_out_sth = $dbh->prepare_cached( "SELECT * FROM autoconfig_hosts WHERE config_id = ? AND type = 'smtp' ORDER BY priority" ) || bailout( "Unable to prepare the sql statements to get hosts configuration details for config id $ref->{config_id}: ", $dbh->errstr );
	$hosts_out_sth->execute( $ref->{config_id} ) || bailout( "An error occurred while trying to execute the sql query to get host details for config id $ref->{config_id}: ", $hosts_out_sth->errstr );
	my $all_hosts_out = $hosts_out_sth->fetchall_arrayref( {} );
	$hosts_out_sth->finish;
	$ref->{outgoing_server} = $all_hosts_out;
	
	my $text_sth = $dbh->prepare_cached( "SELECT * FROM autoconfig_text WHERE config_id = ? AND type = ?" ) || bailout( "Unable to get the list of text, if any, for enabling login: ", $dbh->errstr );
	foreach my $t ( qw( enable documentation ) )
	{
		my $textType = $t eq 'enable' ? 'instruction' : 'description';
		my $sqlType  = $t eq 'enable' ? 'instruction' : 'documentation';
		$text_sth->execute( $ref->{config_id}, $sqlType ) || bailout( "An error occurred while executing query to get the list of enabling $sqlType: ", $text_sth->errstr );
		my $all_text = $text_sth->fetchall_arrayref( {} );
		$err->printf( "%d text elements found for type $t and config id $ref->{config_id}: %s\n", scalar( @$all_text ), Data::Dumper::Dumper( $all_text ) ) if( $DEBUG );
		$text_sth->finish;
		if( $ref->{ "${t}_url" } )
		{
			$ref->{ $t } =
			{
			url => $ref->{ "${t}_url" },
			$textType => {},
			};
			foreach my $this ( @$all_text )
			{
				$ref->{ $t }->{ $textType }->{ $this->{lang} } = $this->{phrase};
			}
		}
	}
	$text_sth->finish;
	
	## Tweak the data layout a bit
	$ref->{webmail} =
	{
	login_page => $ref->{webmail_login_page},
	login_page_info =>
		{
		url => $ref->{lp_info_url},
		username => $ref->{lp_info_username},
		username_field => 
			{
			id => $ref->{lp_info_username_field_id},
			name => $ref->{lp_info_username_field_name},
			},
		password_field => $ref->{lp_info_password_field},
		login_button => 
			{
			id => $ref->{lp_info_login_button_id},
			name => $ref->{lp_info_login_button_name},
			},
		},
	};
	return( $ref );
}

sub read_config_file
{
	bailout( "Requires an hash reference to be provided as unique argument with key config_file and perl_config." ) if( ref( $_[0] ) ne 'HASH' );
	my $opts = shift( @_ );
	my $file = $opts->{config_file};
	my $save_to = $opts->{perl_config};
	my $file_mtime = ( stat( $file ) )[9];
	if( -e( $save_to ) && !-z( $save_to ) && $file_mtime == ( stat( $save_to ) )[9] )
	{
		$err->printf( "PHP config file \"$file\" modification time $file_mtime is not the same as the per file \"%s\".\n", ( stat( $save_to ) )[9] ) if( $DEBUG );
		try
		{
			local $CONF;
			require( $save_to );
			return( $CONF );
		}
		catch( $e )
		{
			bailout( "Error reading the perl configuration file: $e" );
		}
	}
	my $fh = File::Temp->new( SUFFIX => '.php' );
	my $fname = $fh->filename;
	$fh->print( <<EOT );
<?php
include('$file');
echo json_encode( \$CONF, JSON_PRETTY_PRINT );
?>
EOT
	my $json_data = '';
	my $io = IO::File->new( "php $fname|" ) || bailout( "Unable to execute temporary php script to transcode postfixadmin confi file into json: $!" );
	$json_data .= $_ while( defined( $_ = $io->getline ) );
	$io->close;
	my $json = JSON->new->allow_nonref;
	my $perl = $json->utf8->decode( $json_data );
	# $out->print( Data::Dumper::Dumper( $perl ), "\n" );
	my $fh2 = IO::File->new( ">$save_to" ) || bailout( "Unable to write to file \"$save_to\": $!" );
	$fh2->binmode( 'utf-8' );
	local $Data::Dumper::Sortkeys = 1;
	$fh2->print( Data::Dumper->Dump( [$perl], [qw(CONF)] ), "\n" );
	$fh2->close;
	chmod( 0600, $save_to );
	## Set the last modification time to be the same as the original file, so we can compare next time.
	utime( time(), $file_mtime, $save_to );
	return( $perl );
}

sub _generate_uuid
{
	return( uc( Data::UUID->new->create_str ) );
}

sub _interpolate_vars_thunderbird
{
	my $this = shift( @_ );
	return( '' ) if( !length( $this ) );
	## No need to bother if there is no sign of placeholder in the string
	return( $this ) if( index( $this, '%' ) == -1 );
	## No need to bother if no email address was provided
	return( $this ) if( !$email );
	$this =~ s/\%EMAILADDRESS\%/$email->address/ge;
	$this =~ s/\%EMAILLOCALPART\%/$email->user/ge;
	$this =~ s/\%EMAILDOMAIN\%/$email->host/ge;
	return( $this );
}

sub _error
{
	my $err = join( '', @_ );
	$ERROR = $err;
	## Return undef or empty list depending on how we were called
	return;
}

sub xml2hash
{
	my $elem = shift( @_ );
	my $opts = {};
	$opts = pop( @_ ) if( ref( $_[-1] ) eq 'HASH' && !Scalar::Util::blessed( $_[-1] ) );
	if( !Scalar::Util::blessed( $elem ) )
	{
		warn( "An unblessed value was provided. I was expected a XML::LibXML object.\n" );
		return;
	}
	my $doc = $elem->isa( 'XML::LibXML::Document' ) ? $elem->documentElement : $elem;
	foreach my $o ( qw( include_comment include_top_tag lowercase with_prefix ) )
	{
		$opts->{ $o } = $params->{ $o } if( !exists( $opts->{ $o } ) );
	}
	my $ref = &_xml2hash( $doc, 0, $opts );
	my $ref_top = {};
	if( $opts->{include_top_tag} )
	{
		my $k = ( $opts->{with_prefix} ? $doc->nodeName : $doc->getLocalName );
		$k = lc( $k ) if( $opts->{lowercase} );
		$ref_top->{ $k } = $ref;
		return( $ref_top );
	}
	return( $ref );
}

sub _xml2hash
{
	my $doc  = shift( @_ ) || return( {} );
	my $level = shift( @_ ) || 0;
	my $opts = {};
	$opts = pop( @_ ) if( ref( $_[-1] ) eq 'HASH' );
	my $pref  = ( "." x $level ) . "L${level} ";
	## $err->print( "${pref}Received an object of type '", ref( $elem ), "' and processing an object of type '", ref( $doc ), "'\n" );
	my $ref = {};
	local $_;
	if( $doc->hasChildNodes or $doc->hasAttributes ) 
	{
		## $err->print( "${pref}Has child nodes or attributes\n" );
		my $attr = {};
		foreach my $a ( $doc->attributes ) 
		{
			my $k = ( $opts->{with_prefix} ? $a->nodeName : ( $a->getLocalName || $a->nodeName ) );
			## $err->print( "${pref}Found attribute \"$k\" for node \"$a\"\n" );
			$attr->{ $k } = $a->getValue;
		}
		$ref->{ '_attributes' } = $attr if( scalar( keys( %$attr ) ) );
		
		my @childs = $doc->childNodes;
		## $err->print( "${pref}%d child nodes found", scalar( @childs ), "\n" );
		for( $doc->childNodes ) 
		{
			my $class = ref( $_ );
			my $key = '';
			## my $nn;
			if( $class eq 'XML::LibXML::Text' || 
				$class eq 'XML::LibXML::CDATASection' ) 
			{
				$key = '_text';
			}
			elsif( $class eq 'XML::LibXML::Comment' ) 
			{
				if( $opts->{include_comment} )
				{
					$key = '_comment';
				}
				else
				{
					next;
				}
			}
			else 
			{
				$key = ( $opts->{with_prefix} ? $_->nodeName : $_->getLocalName );
				$key = lc( $key ) if( $opts->{lowercase} );
			}
			## $err->print( "${pref}${key} calling xml2hash with value '$_'\n" );
			my $child = &_xml2hash( $_, $level + 1, $opts );
			## $ref->{ $key } = [];
			## if (( $X2A or $X2A{$nn} ) and !$res->{$nn}) { $res->{$nn} = [] }
			if( exists( $ref->{ $key } ) ) 
			{
				## Move previous entry from string to array
				$ref->{ $key } = [ $ref->{ $key } ] unless( ref( $ref->{ $key } ) eq 'ARRAY' );
				push( @{ $ref->{ $key } }, $child ) if( defined( $child ) );
			} 
			else 
			{
				if( $key eq '_text' ) 
				{
					$ref->{ $key } = $child if( length( $child ) );
				} 
				else 
				{
					$ref->{ $key } = $child;
				}
			}
		}
		if( exists( $ref->{ '_text' } ) && ref( $ref->{ '_text' } ) eq 'ARRAY' )
		{
			$ref->{ '_text' } = join( '', @{$ref->{ '_text' }} );
			delete( $ref->{ '_text' } ) if( !length( $ref->{ '_text' } ) );
		}
		delete( $ref->{ '_text' } ) if( scalar( keys( %$ref ) ) > 1 && exists( $ref->{ '_text' } ) && !length( $ref->{ '_text' } ) );
		return( $ref->{ '_text' } ) if( scalar( keys( %$ref ) ) == 1 && exists( $ref->{ '_text' } ) );
		## $err->print( "${pref}Returning: ", Dumper( $ref ), "\n" );
		return( $ref );
	}
	else 
	{
		my $text = $doc->textContent;
		$text =~ s/^[[:blank:]\r\n]+|[[:blank:]\r\n]+$//g;
		## $err->print( "${pref}Returning text '$text'\n" );
		return( $text );
	}
}

sub xpc
{
	my $xml = shift( @_ );
	return( $xpc ) if( $xpc );
	## An error ocured
	my $top = $xml->firstChild;
	my @ns = $top->getNamespaces;
	our $xpc = XML::LibXML::XPathContext->new( $xml );
	foreach my $n ( @ns )
	{
		my $localName = $n->getLocalName;
		my $val = $n->value;
		next unless( $localName && $val );
		## $err->print( "Setting name space $localName => $val\n" );
		$xpc->registerNs( $localName => $val );
	}
	return( $xpc );
}

__END__

