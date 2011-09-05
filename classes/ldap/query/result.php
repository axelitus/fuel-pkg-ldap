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
class Ldap_Query_Result implements \Countable, \Iterator, \SeekableIterator, \ArrayAccess
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
	 * @var Integer contains the results total rows
	 */
	private $_total_rows = 0;

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
		else if (is_string($ldap) or is_numeric($ldap))// remember we have named and
		// not-named instances
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
							if (!in_array($attr_value, $attributes))
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
	public function as_array()
	{
		return $this->_result;
	}

	/**
	 * Gets the results array and formats it according to the given flags.
	 * Beware that we cannot know the count of the entries with a count($result) as
	 * Ldap adds some values to the array. Also note that the result does not have
	 * the main count item by design. Use Ldap_Query_Result->count() instead.
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
	 * @return  integer
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
	 * @return  boolean
	 */
	public function valid()
	{
		return $this->offsetExists($this->_current_row);
	}

	/**
	 * Implements [Iterator::current]
	 */
	public function current()
	{
		if (!$this->seek($this->_current_row))
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
	 * @return  boolean
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
		if (!$this->seek($offset))
		{
			return null;
		}

		return $this->current();
	}

	/**
	 * Implements [ArrayAccess::offsetSet], throws an error.
	 *
	 * [!!] You cannot modify a database result.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	final public function offsetSet($offset, $value)
	{
		throw new \Fuel_Exception('Ldap results are read-only');
	}

	/**
	 * Implements [ArrayAccess::offsetUnset], throws an error.
	 *
	 * [!!] You cannot modify a database result.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	final public function offsetUnset($offset)
	{
		throw new \Fuel_Exception('Ldap results are read-only');
	}

	// === End: Interface ArrayAccess ===
}
