<?php

# XXX This file is what is left from fetchmail.php after moving everything to fetchmail-class.php and the baseclass.
# XXX It's more or less a TODO list ;-)
# XXX Note that templates/fetchmail.php is not yet converted to a class.

require_once('common.php');

authentication_require_role('admin');


# import control GET/POST variables. Form values are imported below.
$new     = (int) safeget ("new")     == 1  ? 1:0;
$edit    = (int) safeget ("edit");
$delete  = (int) safeget ("delete");
$save    =       safepost("save")   != "" ? 1:0;
$cancel  =       safepost("cancel") != "" ? 1:0;


# labels and descriptions are taken from $PALANG['pFetchmail_field_xxx'] and $PALANG['pFetchmail_desc_xxx']

# TODO: After pressing save or cancel in edit form, date and returned text are not displayed in list view.
# TODO: Reason: $display_status is set before $new and $edit are reset to 0.
# TODO: Fix: split the "display field?" column into "display in list" and "display in edit mode".

$SESSID_USERNAME = authentication_get_username();
if (!$SESSID_USERNAME )
   exit;


$row_id = 0;
if ($delete) {
   $row_id = $delete;
} elseif ($edit) {
   $row_id = $edit;
}

if ($row_id) {
   $result = db_query ("SELECT ".implode(",",escape_string(array_keys($fm_struct)))." FROM fetchmail WHERE id=" . $row_id);
   if ($result['rows'] > 0) {
      $edit_row = db_array ($result['result']);
      $account = $edit_row['src_user'] . " @ " . $edit_row['src_server'];
   }
   
   $edit_row_domain = explode('@', $edit_row['mailbox']);
   if ($result['rows'] <= 0 || !check_owner($SESSID_USERNAME, $edit_row_domain[1])) { # owner check for $edit and $delete
      flash_error(sprintf($PALANG['pFetchmail_error_invalid_id'], $row_id));
      $edit = 0; $delete = 0;
   }
}


if ($cancel) { # cancel $new or $edit
   $edit=0;
   $new=0;
} elseif ($delete) { # delete an entry
} elseif ( ($edit || $new) && $save) { # $edit or $new AND save button pressed
} elseif ($edit) { # edit entry form
   $formvars = $edit_row;
   $formvars['src_password'] = '';
} elseif ($new) { # create entry form
   foreach (array_keys($fm_struct) as $value) {
      if (isset($fm_defaults[$value])) {
         $formvars[$value] = $fm_defaults[$value];
      } else {
         $formvars[$value] = '';
      }
   }
}

include ("./templates/header.php");
include ("./templates/menu.php");
include ("./templates/fetchmail.php");
include ("./templates/footer.php");

/* vim: set expandtab softtabstop=3 tabstop=3 shiftwidth=3: */
?>
