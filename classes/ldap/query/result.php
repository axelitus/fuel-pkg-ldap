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
 * Ldap_Query_Result
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
class Ldap_Query_Result implements \Countable, \Iterator, \SeekableIterator, \ArrayAccess
{
	/**
	 * @var Ldap a reference to the Ldap instance
	 */
	protected $_ldap = null;

	/**
	 * @var resource contains the ldap link resource reference
	 */
	protected $_search = null;

	/**
	 * @var array contains the search result entries
	 */
	protected $_result = array();

	/**
	 * @var int contains the results total rows
	 */
	protected $_total_rows = 0;

	// @formatter:off
	/**
	 * @var array contains the generated error or an empty error array
	 */
	protected $_error = array(
		'number' => 0,
		'message' => ''
	);
	// @formatter:on

	/**
	 * Prevent direct instantiation
	 */
	private function __construct($ldap)
	{
		if (get_class($ldap) === 'Ldap\Ldap')
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
	}

	/**
	 * Forges a ne instance of Ldap_Query_Result.
	 *
	 * @param string|Ldap $ldap the Ldap instance or an existing instance's name.
	 * @return Ldap_Query_Result the forged result object.
	 */
	public static function forge($ldap)
	{
		return new static($ldap);
	}

	/**
	 * Loads the results object with an LDAP search result. This function can only be called from an
	 * LDap_Query object.
	 *
	 * @param $search the ldap_search() function results.
	 * @return void
	 */
	public function load($search)
	{
		// Get the caller info
		$trace = debug_backtrace();
		$caller = $trace[1];

		// This method can only be called from within Ldap\Ldap_Query class!
		if (get_class($caller['object']) == 'Ldap\Ldap_Query')
		{
			// Do we have an authentic search result?
			if (is_resource($search) && get_resource_type($search) == Ldap::RESOURCE_RESULT)
			{
				$this->_search = $search;
				$this->_result = @ldap_get_entries($this->_ldap->get_connection(), $this->_search);

				if (isset($this->_result['count']))
				{
					$this->_total_rows = $this->_result['count'];
					unset($this->_result['count']);
				}

				// Now get the member variables for each result and store it in a special key
				// named __attributes
				foreach ($this->_result as $key => $value)
				{
					$attributes = array();
					foreach ($value as $attr => $attr_value)
					{
						if (is_numeric($attr))
						{
							if ( ! in_array($attr_value, $attributes))
							{
								$attributes[] = $attr_value;
							}
						}
						else
						{
							$attributes[] = $attr;
						}
					}

					natsort($attributes);
					$this->_result[$key]['__attributes'] = array_values($attributes);
				}
			}
		}
		else
		{
			throw new \FuelException('The load() function can only be called from the Ldap\Ldap_Query class.');
		}
	}

	/**
	 * Cleans the error. Normally before getting the entries again.
	 *
	 * @return void
	 */
	private function clean_error()
	{
		unset($this->_error);
		$this->_error = array();
	}

	/**
	 * Checks if there's an error.
	 *
	 * @return bool whether there's was an error or not.
	 */
	public function has_error()
	{
		$response = false;

		if ($this->_error['number'] != 0)
		{
			$response = true;
		}

		return $response;
	}

	/**
	 * Gets the error. If no error is given then an empty error array is returned:
	 * array(
	 *	'number' => 0,
	 *	'message' => ''
	 * );
	 *
	 * @return array the query result error.
	 */
	public function get_error()
	{
		return $this->_error;
	}

	/**
	 * Sets the errorr. This function can only be called from an Ldap_Query instance.
	 *
	 * @return void
	 */
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
	 * Gets the results array as it is. Beware that it's not safe to count the entries with a
	 * count($result) as Ldap adds some values to the array. Also note that the result does not have the
	 * main count item by design. Use Ldap_Query_Result->count() instead.
	 *
	 * @return array the raw results array.
	 */
	public function as_array()
	{
		return $this->_result;
	}

	/**
	 * Gets the results array and formats it accordingly to the given flags. Beware that it's not safe to
	 * count the entries with a count($result) as Ldap adds some values to the array. Also note that the
	 * result does not have the main count item by design. Use Ldap_Query_Result->count() instead.	 *
	 * @see Ldap_Query_Result_Formatter
	 *
	 * @param int $flags the flags to be used to format the results.
	 * @return array the formatted results array.
	 */
	public function as_array_format($flags = 0)
	{
		return Ldap_Query_Result_Formatter::format($this->_result, $flags);
	}

	// === Begin: Interface Countable ===

	/**
	 * Implements [Countable::count]
	 *
	 * Gets the entries count as read in the results array. In theory we can trust
	 * this value as to having the correct count value
	 *
	 * echo count($result);
	 *
	 * @return int
	 */
	public function count()
	{
		return $this->_total_rows;
	}

	// === End: Interface Countable ===

	// === Begin: Interface Iterator ===

	protected $_current_row = 0;

	/**
	 * Implements [Iterator::key], returns the current row number.
	 *
	 *     echo key($result);
	 *
	 * @return  int
	 */
	public function key()
	{
		return $this->_current_row;
	}

	/**
	 * Implements [Iterator::next], moves to the next row.
	 *
	 *     next($result);
	 *
	 * @return  $this
	 */
	public function next()
	{
		++$this->_current_row;
		return $this;
	}

	/**
	 * Implements [Iterator::prev], moves to the previous row.
	 *
	 *     prev($result);
	 *
	 * @return  $this
	 */
	public function prev()
	{
		--$this->_current_row;
		return $this;
	}

	/**
	 * Implements [Iterator::rewind], sets the current row to zero.
	 *
	 *     rewind($result);
	 *
	 * @return  $this
	 */
	public function rewind()
	{
		$this->_current_row = 0;
		return $this;
	}

	/**
	 * Implements [Iterator::valid], checks if the current row exists.
	 *
	 * [!!] This method is only used internally.
	 *
	 * @return  bool
	 */
	public function valid()
	{
		return $this->offsetExists($this->_current_row);
	}

	/**
	 * Implements [Iterator::current]
	 *
	 * @return bool|array
	 */
	public function current()
	{
		if ( ! $this->seek($this->_current_row))
		{
			return false;
		}

		// Return an array of the row
		return $this->_result[$this->_current_row];
	}

	// === End: Interface Iterator ===

	// === Begin: Interface SeekableIterator ===

	/**
	 * Implements SeekableIterator::seek]
	 *
	 * @return bool
	 */
	public function seek($offset)
	{
		if ($this->offsetExists($offset))
		{
			// Set the current row to the offset
			$this->_current_row = $offset;

			return true;
		}
		else
		{
			return false;
		}
	}

	// === End: Interface SeekableIterator ===

	// === Begin: Interface ArrayAccess ===

	/**
	 * Implements [ArrayAccess::offsetExists], determines if row exists.
	 *
	 *     if (isset($result[10]))
	 *     {
	 *         // Row 10 exists
	 *     }
	 *
	 * @return  bool
	 */
	public function offsetExists($offset)
	{
		return ($offset >= 0 && $offset < $this->_total_rows);
	}

	/**
	 * Implements [ArrayAccess::offsetGet], gets a given row.
	 *
	 *     $row = $result[10];
	 *
	 * @return  mixed
	 */
	public function offsetGet($offset)
	{
		if ( ! $this->seek($offset))
		{
			return null;
		}

		return $this->current();
	}

	/**
	 * Implements [ArrayAccess::offsetSet], throws an error.
	 *
	 * [!!] You cannot modify the results.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	final public function offsetSet($offset, $value)
	{
		throw new \FuelException('Ldap results are read-only');
	}

	/**
	 * Implements [ArrayAccess::offsetUnset], throws an error.
	 *
	 * [!!] You cannot modify the results.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	final public function offsetUnset($offset)
	{
		throw new \FuelException('Ldap results are read-only');
	}

	// === End: Interface ArrayAccess ===
}
