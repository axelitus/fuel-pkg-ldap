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
 * NOTICE:
 *
 * If you need to make modifications to the default configuration, copy
 * this file to your app/config folder, and make them in there.
 *
 * This will allow you to upgrade this package without losing your custom config.
 */

// @formatter:off
/**
 * Ldap Config example
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
return array(
	'domain' => array(
		'suffix' => '',
		'controllers' => array(
			0 => 'db.debian.org'
		)
	),
	'connection' => array(
		'port' => 389,
		'timeout' => 60,
		'ssl' => false,
		'tls' => false
	),
	'master' => array(
		'user' => '',
		'pwd' => ''
	)
);
// @formatter:on
