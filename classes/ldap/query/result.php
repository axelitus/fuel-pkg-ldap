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
class Ldap_Query_Result
{
	/**
	 * @var Ldap a reference to the Ldap instance
	 */
	private $_ldap = null;

	/**
	 * @var Resource contains the ldap link resource reference
	 */
	private $_search = null;

	/**
	 * @var Array contains the search result entries
	 */
	private $_result = array();

	/**
	 * @var Integer contains the results count
	 */
	private $_count = 0;

	/**
	 * @var Array contains the generated error or empty array
	 */
	private $_error = array();

	/**
	 * Prevent direct instantiation
	 */
	private final function __construct($ldap)
	{
		if (get_class($ldap) === 'Ldap\Ldap')
		{
			$this->_ldap = $ldap;
		}
		else if (is_string($ldap) or is_numeric($ldap))// remember we have named and not-named instances
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

	public static function forge($ldap)
	{
		$response = new Ldap_Query_Result($ldap);

		return $response;
	}

	public function init($search)
	{
		// Get the caller info
		$trace = debug_backtrace();
		$caller = $trace[1];

		// This method can only be called from within Ldap\Ldap_Query class!
		if (get_class($caller['object']) == 'Ldap\Ldap_Query')
		{
			// Do we have an authentic search result?
			if (is_resource($search) && get_resource_type($search) == Ldap::LDAP_RESOURCE_RESULT)
			{
				$this->_search = $search;
				$this->_result = @ldap_get_entries($this->_ldap->get_connection(), $this->_search);

				if (isset($this->_result['count']))
				{
					$this->_count = $this->_result['count'];
					unset($this->_result['count']);
				}
			}
		}
		else
		{
			throw new \Fuel_Exception('The init() function can only be called from the Ldap\Ldap_Query class.');
		}
	}

	/**
	 * Cleans the error. Normally before getting the entries again
	 */
	private function clean_error()
	{
		unset($this->_error);
		$this->_error = array();
	}

	public function has_error()
	{
		$response = false;

		if (is_array($this->_error) && !empty($this->_error))
		{
			$response = true;
		}

		return $response;
	}

	public function set_error($error)
	{
		// Get the caller info
		$trace = debug_backtrace();
		$caller = $trace[1];

		// This method can only be called from within Ldap\Ldap_Query class!
		if (get_class($caller['object']) == 'Ldap\Ldap_Query')
		{
			// Clean the error first
			$this->clean_error();

			// set the error. We won't bother checking the format of the error.
			$this->_error = $error;
		}
	}

	/**
	 * Gets the results array as it is. Beware that we cannot know the count of the
	 * entries with a count($result) as Ldap adds some values to the array. Also note
	 * that the result does not have the main count item by design. Use
	 * Ldap_Query_Result->count() instead.
	 */
	public function result()
	{
		return $this->_result;
	}

	/**
	 * Gets the results array and formats it according to the given flags.
	 * Beware that we cannot know the count of the entries with a count($result) as
	 * Ldap adds some values to the array. Also note that the result does not have
	 * the main count item by design. Use Ldap_Query_Result->count() instead.
	 */
	public function result_format($flags = 0)
	{
		return Ldap_Query_Result_Formatter::format($this->_result, $flags);
	}

	/**
	 * Gets the entries count as read in the results array. In theory we can trust
	 * this value as to having the correct count value
	 */
	public function count()
	{
		return $this->_count;
	}

}
