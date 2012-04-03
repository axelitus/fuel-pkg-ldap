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
 * Ldap_Query
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
class Ldap_Query
{
	const DEFAULT_FILTER = '(objectClass=*)';
	const DEFAULT_FIELDS = 'objectguid, samaccountname, displayname';

	/**
	 * @var Ldap contains a reference to the Ldap instance
	 */
	protected $_ldap;

	/**
	 * @var String the filter to use for the query
	 */
	protected $_filter;

	/**
	 * Prevent direct instantiation.
	 */
	private function __construct($ldap, $filter = '')
	{
		if (is_object($ldap) && get_class($ldap) === 'Ldap\Ldap')
		{
			$this->_ldap = $ldap;
		}
		else if (is_string($ldap))
		{
			if (Ldap::exists($ldap))
			{
				$this->_ldap = Ldap::instance($ldap);
			}
			else
			{
				throw new \FuelException('The given name does not belong to an existing Ldap instance.');
			}
		}
		else
		{
			throw new \FuelException('An instance of Ldap is needed. Please verify that the given parameter is either an instance or a valid name for an existing instance.');
		}
		
		$this->set_filter($filter);
	}

	/**
	 * Creates an instance of Ldap_Query. An Ldap instance must be supplied as it is needed to perform
	 * the query. This can be given as an object or the instance identifier.
	 *
	 * @param string|Ldap $ldap the Ldap instance or an existing instance's name.
	 * @param string $filter the filter to be used by the query.
	 * @return Ldap_Query the forged query object.
	 */
	public static function forge($ldap, $filter = '')
	{
		return new static($ldap);
	}

	/**
	 * Sets the Query filter
	 *
	 * @param string|array $filter the filter to be used by the query in Ldap syntax or an array to be
	 * used with Ldap_Query_Builder::build() method.
	 * @return Ldap_Query this instance for chaining.
	 */
	public function set_filter($filter)
	{
		if (is_string($filter))
		{
			$this->_filter = Ldap_Query_Builder::build($filter);
		}
		elseif (is_array($filter))
		{
			$this->_filter = Ldap_Query_Builder::build($filter);
		}

		return $this;
	}

	/**
	 * Gets the query filter.
	 *
	 * @return string the current query's filter.
	 */
	public function get_filter()
	{
		return $this->_filter;
	}

	/**
	 * Executes the query. The query takes the Ldap instance's timeout value.
	 *
	 * @param string $directory_dn is the DN where we wan't to execute the query to, without the base dn
	 * part as it will be automatically concatenated.
	 * @param string|array $attributes the attributes to fetch with the query.
	 * @param int $limt the result objects to fetch (0 means no limit and will return all found records).
	 */
	public function execute($directory_dn = '', $attributes = '', $limit = 0)
	{
		$return = Ldap_Query_Result::forge($this->_ldap);

		// Are we even connected? Ensure a connection using the $try_connect parameter
		if ($this->_ldap->is_connected(true))
		{
			// Are we binded? Ensure a binding using the $try_bind parameter
			if ($this->_ldap->is_binded(true))
			{
				// Prepare path_dn
				$path_dn = ((is_string($directory_dn) && (trim($directory_dn) != '')) ? $directory_dn : '');
				$path_dn .= (($path_dn != '') ? ','.$this->_ldap->get_base_dn() : $this->_ldap->get_base_dn());

				// Prepare filter
				$filter = ($this->_filter == '') ? self::DEFAULT_FILTER : $this->_filter;

				// Prepare fields
				$fields = ((empty($attributes))? static::DEFAULT_FIELDS : $attributes);
				$fields = static::build_attr_array($fields);

				// Prepare limit
				$limit = max($limit, 0);

				// Prepare timeout. Uses the LDap instance's timeout value
				$timeout = max($this->_ldap->config_get('connection.timeout', 0), 0);
				
				// Query the damn thing!
				$sr = @ldap_search($this->_ldap->get_connection(), $path_dn, $filter, $fields, 0, $limit, $timeout);
				
				// The query went ok?
				if (is_resource($sr) && get_resource_type($sr) == Ldap::RESOURCE_RESULT)
				{
					// load the Ldap_Query_Result object
					$return->load($sr, $filter);
				}
				else
				{
					// set the Ldap_Query_Result error
					if ($this->_ldap->has_error())
					{
						$return->set_error($this->_ldap->get_error());
					}
					else
					{
						$return->set_error(array('number' => -1, 'message' => 'Unknown error in Ldap\Ldap_Query execute() function.'));
					}
				}
			}
			else
			{
				throw new \FuelException('Cannot query: there is no binding to LDAP server.');
			}
		}
		else
		{
			throw new \FuelException('Cannot query: there is no connection to LDAP server.');
		}

		return $return;
	}

	/**
	 * Builds an attributes array from a comma separated string. If the given attributes are an array it
	 * is returned as-is.
	 *
	 * @param string $attributes a comma-separated attributes list.
	 * @return array with the exploded attributes.
	 */
	public static function build_attr_array($attributes)
	{
		$return = array();

		if (is_string($attributes) && (($attributes = trim($attributes)) != ''))
		{
			$return = explode(",", $attributes);
			$return = array_map('trim', $return);
		}
		elseif (is_array($attributes))
		{
			$return = $attributes;
		}

		return $return;
	}

}
