<?php
/*
 Virtual Mail Delete
 by George Vieira <george at citadelcomputer dot com dot au>

 You can run this from your crontab with something like

 0 4 * * * *    vmail    php -q virtualmaildel.php >/dev/null

 Changes:
    2017.08.31 updated to use PHP mysqli extension.
    2018.02.23 removing Sieve filters if exists.
        Tadas Ustinavičius <tadas at ring dot lt> ( https://github.com/postfixadmin/postfixadmin/pull/70 )

*/

$CONF = [];

// Either, uncomment this (and change to work)
//require_once('/path/to/postfixadmin/config.inc.php');

// OR uncomment this.
/*
$CONF = [
    'database_host' => 'localhost',
    'database_user' => 'someone',
    'database_password' => 'something',
    'database_name' => 'mydb'
];
 */


$MAKE_CHANGES = false; // change to true when you're happy this isn't going to trash your server.

if (empty($CONF)) {
    die("\nPlease configure me\n\n");
}

// Where's the homedir accounts stored. (GET THIS RIGHT OTHERWISE IT THINK NONE EXIST AND DELETES ALL)
$homedir    = '/home/virtual';

if (! is_dir($homedir)) {
    die("Cannot find home directory for virtual mailboxes in $homedir\n");
}

//
// Recursive Delete Function
//
function deldir($dir) {
    $current_dir = opendir($dir);
    while ($entryname = readdir($current_dir)) {
        if (is_dir("$dir/$entryname") and ($entryname != "." and $entryname!="..")) {
            deldir("{$dir}/{$entryname}");
        } elseif ($entryname != "." and $entryname!="..") {
            unlink("{$dir}/{$entryname}");
        }
    }
    closedir($current_dir);
    @rmdir($dir);
}

// --- Main Start ---

$dir = [];

//
// Get list of directories
//
$fr = opendir($homedir);

// TODO: Would glob($homedir . '/**/*/new') be somewhat quicker/shorter/less effort?

while (($domain = readdir($fr)) !== false) {
    //
    // Check if it's a dir
    //
    if ($domain == "." || $domain == ".." || filetype($homedir .'/'. $domain) != "dir") {
        continue;
    }
    //
    // Open the (assumed) DOMAIN directory
    //
    $ff = opendir($homedir .'/'. $domain);
    while (($user = readdir($ff)) !== false) {
        //
        // Check for directories assuming it's a user account
        //
        if ($user == "." || $user == ".." || filetype($homedir .'/'. $domain .'/'. $user) != "dir") {
            continue;
        }

        //
        // if the dir 'new' exists inside then it's an account
        //
        if (file_exists($homedir .'/'. $domain .'/'. $user .'/'. "new")) {
            $dir[$domain][$user] = "";
        } else {
            //
            // Alert that the dir doesn't have a 'new' dir, possibly not an account. Leave it.
            //
            echo "UNKNOWN  : " . $homedir ."/". $domain ."/". $user ."/new NOT FOUND. Possibly not an account. Leaving untouched\n";
        }
    }
}
//
// OK, got an array of accounts from the dir, Now connect to the DB and check them
//
$conx = mysqli_connect($CONF['database_host'], $CONF['database_user'], $CONF['database_password'], $CONF['database_name']);
//
// Is there a problem connecting?
//
if (! $conx || mysqli_connect_errno()) {
    echo "DB connection failed." . mysqli_connect_error() . "\n";
    die("Problem connecting to the database. ");
}

//
// Select all mailboxes to verify against dirs listed in array
//
$query = "SELECT * FROM mailbox";
$result = mysqli_query($conx, $query);

//
// Query the mailbox table
//
if (! $result) {
    die("Failed to query mailbox table.");
}

//
// Fetch the list of results
//
while ($row = mysqli_fetch_assoc($result)) {
    //
    // Pull apart the maildir field, needed to figure out the directory structure to compare
    //
    $strip = explode("/", $row['maildir']);
    //
    // Unset the array if it exists. This stops it being erased later.
    //
    unset($dir[ $strip[0] ][ $strip[1] ]);
}
//
// If there are results. unset the domain too.
//
if (count($dir[$strip[0]])==0 and mysqli_num_rows($result)>0) {
    unset($dir[$strip[0]]);
}

//
// OK, time to clean up. All known users/domains have been removed from the list.
//

//
// If the array still exists (incase nothing there)
//
if (is_array($dir)) {
    //
    // Go through each dir
    //
    foreach ($dir as $key => $value) {
        //
        // Is this a user array?
        //
        if (!is_array($value)) {
            continue;
        }

        //
        // Go through and nuke the folders
        //
        foreach ($value as $user => $value2) {
            // Nuke.. need any more explanations?
            $path = $homedir . '/' . $key . '/' . $user;
            $sieve_path = $homedir . '/.sieve/' . $key . '/' . $user;
            $sieve_exists = file_exists($sieve_path);
            // check if user has Sieve filters created
            if ($MAKE_CHANGES) {
                deldir($path);
                if ($sieve_exists) {
                    deldir($sieve_path);
                }
            } else {
                echo " - Would recursively delete : $path \n";
                if ($sieve_exists) {
                    echo " - Would recursively delete Sieve filters : $sieve_path \n";
                }
            }
        }
    }
}

echo "Cleanup process completed\n";
