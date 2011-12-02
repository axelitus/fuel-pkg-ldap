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

namespace Ldap;

/**
 * Ldap_Query_Builder
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
class Ldap_Query_Builder
{
	/**
	 * Some useful constants
	 */
	const OPERATOR_AND = '&';
	const OPERATOR_OR = '|';
	const OPERATOR_NOT = '!';

	/**
	 * Prevent direct instantiation
	 */
	private function __construct()
	{
	}

	/**
	 * Builds an Ldap query with the params array given.
	 *
	 * @param string|array optional the params to build the query from
	 * @return string the LDAP-syntax filter built.
	 */
	public static function build($params)
	{
		$return = $params;

		if (is_string($return))
		{
			$return = static::build_enclosure($return);
		}
		elseif (is_array($return))
		{
			$return = static::build_enclosure(static::build_level($return));
		}

		return $return;
	}

	/**
	 * Builds the proper enclosure (with parentheses) for the query or subqueries.
	 *
	 * @param string $filter the string to enclose.
	 * @return string the enclosed string.
	 */
	private static function build_enclosure($filter)
	{
		$return = '';
		
		if (is_string($filter) && $filter != '')
		{
			if (($first_char = substr($filter, 0, 1)) == '(')
			{
				if (substr($filter, - 1, 1) == ')')
				{
					// String begins with '(' and ends with ')' so just return the string.
					$return = $filter;
				}
				else
				{
					// String begins with '(' but does not end with ')' so return the string with a closing ')'.
					$return = $filter.')';
				}
			}
			elseif ($first_char == static::OPERATOR_AND || $first_char == static::OPERATOR_OR || $first_char == static::OPERATOR_NOT)
			{
				$return = '('.$filter.')';
			}
			else
			{
				if (substr($filter, - 1, 1) == ')')
				{
					// String does not begin with '(' but does end with ')' so return the string with a beginning '('.
					$return = '('.$filter;
				}
				else
				{
					// String does not begin with '(' nor does it end with ')' so return the string with full enclosure.
					$return = '('.$filter.')';
				}
			}
		}

		return $return;
	}

	/**
	 * Builds the multiple subquery levels.
	 *
	 * @param array $params the parameters to build the subquery from.
	 * @return string the filter string in proper LDAP-syntax.
	 */
	private static function build_level(array $params)
	{
		$return = '';

		if ( ! empty($params))
		{
			foreach ($params as $param)
			{
				if (is_string($param))
				{
					switch ($param)
					{
						case self::OPERATOR_AND:
						case self::OPERATOR_OR:
							$return .= $param;
						break;
						default:
							$return .= static::build_enclosure($param);
						break;
					}
				}
				else
				{
					$return .= static::build_enclosure(static::build_level($param));
				}
			}
		}

		return $return;
	}

}
