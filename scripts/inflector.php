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
        public static function camelize($lowerCaseAndUnderscoredWord) {
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
        public static function underscore($camelCasedWord) {
                $replace = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
                return $replace;
        }
}

