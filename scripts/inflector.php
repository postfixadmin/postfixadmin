<?php
/**
 * -.
 *
 * Used by Cake's naming conventions throughout the framework.
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright 2005-2008, Cake Software Foundation, Inc.
 *                                                              1785 E. Sahara Avenue, Suite 490-204
 *                                                              Las Vegas, Nevada 89104
 * Modified for Postfixadmin by Valkum
 *
 * Copyright 2010
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright           Copyright 2005-2008, Cake Software Foundation, Inc.
 * @link                                http://postfixadmin.sourceforge.net/ Postfixadmin on Sourceforge
 * @package                     postfixadmin
 * @subpackage          -
 * @since                       -
 * @version                     $Revision$
 * @modifiedby          $LastChangedBy$
 * @lastmodified        $Date$
 * @license                     http://www.opensource.org/licenses/mit-license.php The MIT License
 */
class Inflector {
/**
 * Returns given $lower_case_and_underscored_word as a CamelCased word.
 *
 * @param string $lower_case_and_underscored_word Word to camelize
 * @return string Camelized word. LikeThis.
 * @access public
 * @static
 */
        function camelize($lowerCaseAndUnderscoredWord) {
                $replace = str_replace(" ", "", ucwords(str_replace("_", " ", $lowerCaseAndUnderscoredWord)));
                return $replace;
        }
/**
 * Returns an underscore-syntaxed ($like_this_dear_reader) version of the $camel_cased_word.
 *
 * @param string $camel_cased_word Camel-cased word to be "underscorized"
 * @return string Underscore-syntaxed version of the $camel_cased_word
 * @access public
 * @static
 */
        function underscore($camelCasedWord) {
                $replace = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
                return $replace;
        }
/**
 * Returns a human-readable string from $lower_case_and_underscored_word,
 * by replacing underscores with a space, and by upper-casing the initial characters.
 *
 * @param string $lower_case_and_underscored_word String to be made more readable
 * @return string Human-readable string
 * @access public
 * @static
 */
        function humanize($lowerCaseAndUnderscoredWord) {
                $replace = ucwords(str_replace("_", " ", $lowerCaseAndUnderscoredWord));
                return $replace;
        }
/**
 * Returns corresponding table name for given $class_name. ("posts" for the model class "Post").
 *
 * @param string $class_name Name of class to get database table name for
 * @return string Name of the database table for given class
 * @access public
 * @static
 */
        function tableize($className) {
                $replace = Inflector::pluralize(Inflector::underscore($className));
                return $replace;
        }
/**
 * Returns Cake model class name ("Post" for the database table "posts".) for given database table.
 *
 * @param string $tableName Name of database table to get class name for
 * @return string Class name
 * @access public
 * @static
 */
        function classify($tableName) {
                $replace = Inflector::camelize(Inflector::singularize($tableName));
                return $replace;
        }
/**
 * Returns camelBacked version of a string.
 *
 * @param string $string
 * @return string in variable form
 * @access public
 * @static
 */
        function variable($string) {
                $string = Inflector::camelize(Inflector::underscore($string));
                $replace = strtolower(substr($string, 0, 1));
                $variable = preg_replace('/\\w/', $replace, $string, 1);
                return $variable;
        }
/**
 * Returns a string with all spaces converted to $replacement and non word characters removed.
 *
 * @param string $string
 * @param string $replacement
 * @return string
 * @access public
 * @static
 */
        function slug($string, $replacement = '_') {
                if (!class_exists('String')) {
                        require LIBS . 'string.php';
                }
                $map = array(
                        '/à|á|å|â/' => 'a',
                        '/è|é|ê|ẽ|ë/' => 'e',
                        '/ì|í|î/' => 'i',
                        '/ò|ó|ô|ø/' => 'o',
                        '/ù|ú|ů|û/' => 'u',
                        '/ç/' => 'c',
                        '/ñ/' => 'n',
                        '/ä|æ/' => 'ae',
                        '/ö/' => 'oe',
                        '/ü/' => 'ue',
                        '/Ä/' => 'Ae',
                        '/Ü/' => 'Ue',
                        '/Ö/' => 'Oe',
                        '/ß/' => 'ss',
                        '/[^\w\s]/' => ' ',
                        '/\\s+/' => $replacement,
                        String::insert('/^[:replacement]+|[:replacement]+$/', array('replacement' => preg_quote($replacement, '/'))) => '',
                );
                $string = preg_replace(array_keys($map), array_values($map), $string);
                return $string;
        }
}
/**
 * Enclose a string for preg matching.
 *
 * @param string $string String to enclose
 * @return string Enclosed string
 */
        function __enclose($string) {
                return '(?:' . $string . ')';
        }
