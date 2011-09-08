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
 * Ldap Package
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann
 * @copyright   2011 Axel Pardemann
 */
class Ldap_Query
{
	const DEFAULT_FILTER = '(objectClass=*)';
	const DEFAULT_FIELDS = 'objectguid, displayname';

	/**
	 * @var Ldap contains a reference to the Ldap instance
	 */
	private $_ldap;

	/**
	 * @var String the filter to use for the query
	 */
	private $_filter;

	/**
	 * Prevent direct instantiation
	 */
	private final function __construct($ldap)
	{
		if (get_class($ldap) === 'Ldap\Ldap')
		{
			$this->_ldap = $ldap;
		}
		// remember we have named and not-named instances
		else if (is_string($ldap) or is_numeric($ldap))
		{
			if (Ldap::has_instance($ldap))
			{
				$this->_ldap = Ldap::instance($ldap);
			}
			else
			{
				throw new \Fuel_Exception('The given name does not belong to an existing Ldap instance.');
			}
		}
		else
		{
			throw new \Fuel_Exception('An instance of Ldap is needed. Please verify that the given parameter is either an instance or a valid name for an existing instance.');
		}
	}

	/**
	 * Creates an instance of Ldap_Query. An Ldap instance must be supplied as it is
	 * needed to perform the query
	 */
	public static function forge($ldap)
	{
		return new Ldap_Query($ldap);
	}

	/**
	 * Sets the Query filter
	 */
	public function set_filter($filter)
	{
		if (is_string($filter))
		{
			$this->_filter = trim($filter);
		}
	}

	/**
	 * Gets the query filter
	 */
	public function get_filter()
	{
		return $this->_filter;
	}

	/**
	 * Executes the query
	 * $directory_dn is the DN where we wan't to execute the query to, without the
	 * base_dn part as it will be automatically concatenated at the end
	 * $limt = 0 means no limit, return all records found
	 */
	public function execute($directory_dn = '', $attributes = '', $limit = 0, $timeout = 0)
	{
		// TODO: forge the Ldap_Query_Result object
		$response = Ldap_Query_Result::forge($this->_ldap);

		// Are we even connected? Ensure a connection using the try_connect parameter
		if ($this->_ldap->is_connected(true))
		{
			// Are we binded? Ensure a binding using the try_bind parameter
			if ($this->_ldap->is_binded(true))
			{
				// Prepare path_dn
				$path_dn = ((is_string($directory_dn) && (trim($directory_dn) != '')) ? $directory_dn : '');
				$path_dn .= (($path_dn != '') ? ',' . $this->_ldap->get_base_dn() : $this->_ldap->get_base_dn());

				// Prepare filter
				$filter = ($this->_filter == '') ? self::DEFAULT_FILTER : $this->_filter;

				// Prepare fields
				$fields = ((is_string($attributes)) ? (($attributes == '') ? static::build_attr_array(self::DEFAULT_FIELDS) : static::build_attr_array($attributes)) : ((is_array($attributes) && !empty($attributes)) ? $attributes : static::build_attr_array(self::DEFAULT_FIELDS)));

				// Prepare limit
				$limit = max($limit, 0);

				// Prepare timeout
				// TODO: how to handle timeout with LDAP config?
				$timeout = max($timeout, 0);

				// Query the damn thing!
				$sr = @ldap_search($this->_ldap->get_connection(), $path_dn, $filter, $fields, 0, $limit, $timeout);

				// The query went ok?
				if (is_resource($sr) && get_resource_type($sr) == Ldap::LDAP_RESOURCE_RESULT)
				{
					// init the Ldap_Query_Result object
					$response->init($sr);
				}
				else
				{
					// TODO: change for an exception
					// set the Ldap_Query_Result error
					if ($this->_ldap->has_error())
					{
						$response->set_error($this->_ldap->get_error());
					}
					else
					{
						$response->set_error(array('number' => 0, 'message' => 'Unknown error in Ldap\Ldap_Query execute() function.'));
					}
				}
			}
			else
			{
				throw new \Fuel_Exception('Cannot query: there is no binding to LDAP server.');
			}
		}
		else
		{
			throw new \Fuel_Exception('Cannot query: there is no connection to LDAP server.');
		}

		return $response;
	}

	/**
	 * Builds an attributes array from a comma separated string
	 */
	public static function build_attr_array($attributes)
	{
		$response = array();

		if (is_string($attributes) && (($attributes = trim($attributes)) != ''))
		{
			$response = explode(",", $attributes);
			$response = array_map('trim', $response);
		}

		return $response;
	}

}
