<?php
/**
 * Smarty shared plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Function: smarty_needle
 * Purpose:  Used to find a string in a string
 * Options:  enter "case" to make case senstative
 * Example:  needle( 'Gabe-was-here', 'here' ) returns true
 * Example2: needle( 'Gabe was here', 'gabe' ) returns true
 * Example:  needle ('Gabe was there', 'sde') returns false
 * Smarty Sample: {$haystack|needle:"string"}
 * Smarty Sample: {$haystack|needle:"string":"case"}
 * @author Gabe LeBlanc "raven"
 * @param string
 * @return boolean
 */
function smarty_modifier_needle($haystack, $needle, $cases = "nocase") {
   if(!empty($haystack) ) {

      if($cases == "nocase") {

         if(stristr($haystack, $needle)) {

            return true;

         }else{

              return false;
         }

         }elseif($cases == "case") {

         if(strstr($haystack, $needle)) {

            return true;

         }else{

               return false;
         }
      }

   }else{

      return false;
   }
}   


?>
