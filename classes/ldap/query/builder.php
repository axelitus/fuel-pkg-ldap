<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Ldap;

/**
 * Ldap
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann
 * @copyright   2011 Axel Pardemann
 */
class Ldap_Query_Builder
{
	/**
	 * Some constants
	 */
	const OPERATOR_AND = '&';
	const OPERATOR_OR = '|';
	const OPERATOR_NOT = '!';

	/**
	 * Prevent direct instantiation
	 */
	private final function __construct()
	{
	}

	/**
	 * Builds an Ldap query with the params array given
	 */
	public static function build($params = array())
	{
		if (is_string($params) && $params != '')
		{
			$response = static::build_enclosure($params);
		}
		else
		{
			$response = static::build_enclosure(static::build_level($params));
		}
		return $response;
	}

	/**
	 * Builds the proper (paretheses) enclosure for the query or subqueries
	 */
	private static function build_enclosure($filter)
	{
		$response = '';

		if (is_string($filter) && $filter != '')
		{
			if ($filter[0] == '(')
			{
				$response = $filter;
			}
			else
			{
				$response = "(" . $filter . ")";
			}
		}

		return $response;
	}

	/**
	 * Builds the multiple subqueries levels
	 */
	private static function build_level($params)
	{
		$response = '';

		if (is_array($params) && !empty($params))
		{
			foreach ($params as $param)
			{
				if (is_string($param))
				{
					switch ($param)
					{
						case self::OPERATOR_AND:
						case self::OPERATOR_OR:
							$response .= $param;
							break;
						default:
							$response .= "(" . $param . ")";
							break;
					}
				}
				else
				{
					$response .= static::build_enclosure(static::build_level($param));
				}
			}
		}

		return $response;
	}

}
