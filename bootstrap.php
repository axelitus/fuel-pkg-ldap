<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.1
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

/**
 * Ldap
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
Autoloader::add_namespace('Ldap', __DIR__.'/classes/');

Autoloader::add_core_namespace('Ldap');

Autoloader::add_classes(array(
	'Ldap\\Ldap'          					=> __DIR__.'/classes/ldap.php',
	'Ldap\\LdapNotSupportedException'		=> __DIR__.'/classes/ldap.php',
	'Ldap\\Ldap_Query'						=> __DIR__.'/classes/ldap/query.php',
	'Ldap\\Ldap_Query_Builder'				=> __DIR__.'/classes/ldap/query/builder.php',
	'Ldap\\Ldap_Query_Result'				=> __DIR__.'/classes/ldap/query/result.php',
	'Ldap\\Ldap_Query_Result_Formatter'		=> __DIR__.'/classes/ldap/query/result/formatter.php'
));