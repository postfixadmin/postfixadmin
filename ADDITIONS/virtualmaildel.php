<?php
//
// Virtual Mail Delete
// by George Vieira <george at citadelcomputer dot com dot au>
//
// You can run this from your crontab with something like
//
// 0 4 * * * *    vmail    php -q virtualmaildel.php >/dev/null

	//
	// Setup location of postfixadmin config files. Needed to login to mysql
	//
	$conf		= '/home/httpd/mail/admin/config.inc.php';

	//
	// Where's the homedir accounts stored. (GET THIS RIGHT OTHERWISE IT THINK NONE EXIST AND DELETES ALL)
	//
	$homedir	= '/home/virtual';

	//
	// Make sure everything is everything before continuing
	//
	if ( ! file_exists( $conf ) )
		die( "Cannot find config file $conf\n" );

	if ( ! is_dir( $homedir ) )
		die( "Cannot find home directory for virtual mailboxes in $homedir\n" );

	//
	// Load mysql authentication from postfixadmin
	//
	include( $conf );

	//
	// Recursive Delete Function
	//
	function deldir($dir)
	{
		$current_dir = opendir($dir);
		while($entryname = readdir($current_dir))
		{
			if(is_dir("$dir/$entryname") and ($entryname != "." and $entryname!=".."))
			{
				deldir("${dir}/${entryname}");
			}
			elseif($entryname != "." and $entryname!="..")
			{
				unlink("${dir}/${entryname}");
			}
		}
		closedir($current_dir);
		@rmdir(${dir});
	}

// --- Main Start ---

	//
	// Get list of directories
	//
	$fr = opendir( $homedir );
	while ( ($domain = readdir($fr)) !== false)
	{
		//
		// Check if it's a dir
		//
		if ( $domain != "." and $domain != ".." and filetype($homedir .'/'. $domain) == "dir" )
		{
			//
			// Open the (assumed) DOMAIN directory
			//
			$ff = opendir( $homedir .'/'. $domain );
			while ( ($user = readdir($ff)) !== false)
			{
				//
				// Check for directories assuming it's a user account
				//
				if ( $user!="." and $user!=".." and filetype($homedir .'/'. $domain .'/'. $user) == "dir" )
				{
					//
					// if the dir 'new' exists inside then it's an account
					//
					if ( file_exists($homedir .'/'. $domain .'/'. $user .'/'. "new") )
					{
						$dir[$domain][$user] = "";
					}
					else
					{
						//
						// Alert that the dir doesn't have a 'new' dir, possibly not an account. Leave it.
						//
						echo "UNKNOWN  : " . $homedir ."/". $domain ."/". $user ."/new NOT FOUND. Possibly not an account. Leaving untouched\n";
					}
				}
			} 
		}
	} 
	//
	// OK, got an array of accounts from the dir, Now connect to the DB and check them
	//
	$conx = mysql_connect( $CONF['database_host'],$CONF['database_user'],$CONF['database_password'] );
	//
	// Is there a problem connecting?
	//
	if ( $conx != false )
	{
		//
		// Select the database
		//
		mysql_select_db( $CONF['database_name'] , $conx) or die ("Can't access database postfix : " . mysql_error()); 

		//
		// Select all mailboxes to verify against dirs listed in array
		//
		$query = "SELECT * FROM mailbox";
		$result = mysql_query( $query );

		//
		// Query the mailbox table
		//
		if ( $result != false )
		{
			//
			// Fetch the list of results
			//
			while ( $row = mysql_fetch_assoc( $result ) )
			{
				//
				// Pull apart the maildir field, needed to figure out the directory structure to compare
				//
				$strip = explode("/",$row['maildir']);
				//
				// Unset the array if it exists. This stops it being erased later.
				//
				unset( $dir[ $strip[0] ][ $strip[1] ] );
			}
			//
			// If there are results. unset the domain too.
			//
			if ( count($dir[$strip[0]])==0 and mysql_num_rows($result)>0 )
				unset( $dir[$strip[0]] );
		}
		else
			die( "Failed SELECT in mailboxes\n" );
	}
	else
		die( 'Cannot connect to the database!\n' );

	//
	// OK, time to clean up. All known users/domains have been removed from the list.
	//

	//
	// If the array still exists (incase nothing there)
	//
	if ( is_array($dir) )
	{
		//
		// Go through each dir
		//
		foreach ( $dir as $key => $value )
		{
			//
			// Is this a user array?
			//
			if ( is_array( $value) )
			{
				//
				// Go through and nuke the folders
				//
				foreach ( $value as $user => $value2 )
				{
					//
					// Nuke.. need any more explanations?
					//
					echo "REMOVING : " . $homedir."/".$key."/".$user."\n" ;
					deldir( $homedir."/".$key."/".$user ) ;
				}
			}
		}
	}
	//
	// And we are outta here....
	//
	echo "Cleanup process completed\n";
?>
