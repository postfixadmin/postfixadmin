<?php
/**
 * This file is part of Smarty.
 *
 * (c) 2015 Uwe Tews
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Smarty Internal Plugin Compile Block Child Class
 *
 * @author Uwe Tews <uwe.tews@googlemail.com>
 */
//require_once SMARTY_SYSPLUGINS_DIR . 'smarty_internal_compile_child.php';
class Smarty_Internal_Compile_Block_Child extends Smarty_Internal_Compile_Child
{
    /**
     * Tag name
     *
     * @var string
     */
    public $tag = 'block_child';
}
