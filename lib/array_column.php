<?php
function array_column(array $array, $column) {
    $retval = array();
    foreach ($array as $row) {
        $retval[] = $row[$column];
    }
    return $retval;
}
