<?php

# $Id$
/**
 * class to display the database scheme (for usage in upgrade.php) in Cli
 *
 * extends the "Shell" class
 */

class CliScheme extends Shell {
    public $handler_to_use = "";
    public $new = 0;


    /**
    * Execution method always used for tasks
    */
    public function execute() {
        $module = preg_replace('/Handler$/', '', $this->handler_to_use);
        $module = strtolower($module);

        $handler =  new $this->handler_to_use($this->new);
        $struct = $handler->getStruct();

        foreach (array_keys($struct) as $field) {
            if ($field == 'created') {
                $struct[$field]['db_code'] = '{DATE}';
            } elseif ($field == 'modified') {
                $struct[$field]['db_code'] = '{DATECURRENT}';
            } else {
                switch ($struct[$field]['type']) {
                    case 'int':
                        $struct[$field]['db_code'] = '{BIGINT}';
                        break;
                    case 'bool':
                        $struct[$field]['db_code'] = '{BOOLEAN}';
                        break;
                    default:
                        $struct[$field]['db_code'] = 'VARCHAR(255) {LATIN1} NOT NULL';
                }
            }
        }

        $this->out("For creating a new table with upgrade.php:");
        $this->out("");

        $this->out('db_query_parsed("');
        $this->out('    CREATE TABLE {IF_NOT_EXISTS} " . table_by_key("' . $module . '") . " (');
        # TODO: $module is not really correct - $handler->db_table would be

        foreach (array_keys($struct) as $field) {
            if ($struct[$field]['not_in_db'] == 0 && $struct[$field]['dont_write_to_db'] == 0) {
                $this->out("        $field " . $struct[$field]['db_code'] . ",");
            }
        }

        $this->out("        INDEX domain(domain,username), // <--- change as needed");
        $this->out("        PRIMARY KEY (" . $handler->getId_field() . ")");
        $this->out('    ) {MYISAM} ');
        $this->out('");');

        $this->out('');
        $this->hr();
        $this->out('For adding fields with upgrade.php:');
        $this->out('');

        $prev_field = '';
        foreach (array_keys($struct) as $field) {
            if ($struct[$field]['not_in_db'] == 0 && $struct[$field]['dont_write_to_db'] == 0) {
                $this->out("        _db_add_field('$module', '$field',\t'" . $struct[$field]['db_code'] . "',\t'$prev_field');");
                $prev_field = $field;
            }
        }

        $this->out('');
        $this->hr();
        $this->out('Note that the above is only a template.');
        $this->out('You might need to adjust some parts.');
        return 0;
    }

    /**
    * Displays help contents
    */
    public function help() {
        $module = preg_replace('/Handler$/', '', $this->handler_to_use);
        $module = strtolower($module);

        $this->out(
"Usage:

    postfixadmin-cli $module scheme

        Print the $module database scheme in a way that can be 
        pasted into upgrade.php.

"
        );

        $this->_stop(1);
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
