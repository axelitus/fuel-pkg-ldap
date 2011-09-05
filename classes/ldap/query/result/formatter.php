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
class Ldap_Query_Result_Formatter
{
	/**
	 * Useful constants
	 */
	// Unknown Level or Not Given (ROOT will be used by default)
	const LDAP_RESULT_LEVEL_DEFAULT = 0;
	// Ldap Result Array First Level
	const LDAP_RESULT_LEVEL_ROOT = 1;
	// Ldap Result Array Second Level
	const LDAP_RESULT_LEVEL_ITEM = 2;

	/**
	 * Some bitwise operations one can do with formatter
	 */
	const LDAP_FORMAT_REMOVE_COUNTS = 1;
	const LDAP_FORMAT_NO_NUM_INDEX = 2;
	const LDAP_FORMAT_FLATTEN_VALUES = 4;
	const LDAP_FORMAT_KEYS_CASE_LOWER = 8;
	const LDAP_FORMAT_KEYS_CASE_UPPER = 16;
	const LDAP_FORMAT_SORT_BY_ATTRIBUTES = 32;

	/**
	 * Formats the results array with the given flags. This is a non-destructive
	 * function as it returns the processed array but leaves the original intact
	 */
	public static function format(Array $array, $flags = 0, $level = self::LDAP_RESULT_LEVEL_DEFAULT)
	{
		$response = static::process_flags($array, $flags, $level);

		return $response;
	}

	/**
	 * Process each given flag in the flags parameter with it's corresponding
	 * function. Every function is a non-destructive function.
	 */
	private static function process_flags(Array $array, $flags, $level)
	{
		$response = $array;

		// Let's chain the processing methods here leaving the original array untouched
		// but do this only if we have an array.
		if (is_array($response))
		{
			// Format: Remove Counts
			// Works For All Levels
			if (static::is_flag_on($flags, self::LDAP_FORMAT_REMOVE_COUNTS))
			{
				$response = static::format_remove_counts_multi($response);
			}

			// Format: No Num Index
			// Different Function For Each Level
			if (static::is_flag_on($flags, self::LDAP_FORMAT_NO_NUM_INDEX))
			{
				switch($level)
				{
					case self::LDAP_RESULT_LEVEL_ROOT:
						$response = static::format_no_num_index_root($response);
						break;
					case self::LDAP_RESULT_LEVEL_ITEM:
						$response = static::format_no_num_index_item($response);
						break;
					default:
						// Default to root
						$response = static::format_no_num_index_root($response);
						break;
				}
			}

			// Format: Root Flatten Values
			// Different Function For Each Level
			if (static::is_flag_on($flags, self::LDAP_FORMAT_FLATTEN_VALUES))
			{
				switch($level)
				{
					case self::LDAP_RESULT_LEVEL_ROOT:
						$response = static::format_flatten_values_root($response);
						break;
					case self::LDAP_RESULT_LEVEL_ITEM:
						$response = static::format_flatten_values_item($response);
						break;
					default:
						// Default to root
						$response = static::format_flatten_values_root($response);
						break;
				}
			}

			// Format: Lower Case Keys
			// Works For All Levels
			if (static::is_flag_on($flags, self::LDAP_FORMAT_KEYS_CASE_LOWER))
			{
				$response = static::format_keys_case_lower_multi($response);
			}

			// Format: Upper Case Keys
			// Works For All Levels
			if (static::is_flag_on($flags, self::LDAP_FORMAT_KEYS_CASE_UPPER))
			{
				$response = static::format_keys_case_upper_multi($response);
			}

			// Format: Sort By Attribute
			// Different Function For Each Level
			if (static::is_flag_on($flags, self::LDAP_FORMAT_SORT_BY_ATTRIBUTES))
			{
				switch($level)
				{
					case self::LDAP_RESULT_LEVEL_ROOT:
						$response = static::format_sort_by_attributes_root($response);
						break;
					case self::LDAP_RESULT_LEVEL_ITEM:
						$response = static::format_sort_by_attributes_item($response);
						break;
					default:
						// Default to root
						$response = static::format_sort_by_attributes_root($response);
						break;
				}
			}
		}

		return $response;
	}

	/**
	 * Cehcks if the given flag is set in the given flags
	 */
	private static function is_flag_on($flags, $flag)
	{
		$response = false;

		// Work only with numeric and positive flags
		if (is_numeric($flags) && $flags >= 0 && is_numeric($flag) && $flag > 0)
		{
			// Verify if the given flag is a power of 2. If not then there can be weird
			// issues as then some numbers would share bits, so let's not complicate ourselfs
			// trying to guess and just throw an exception
			if (($flag & ($flag - 1)) === 0)
			{
				// Do some bitwise magic here and see if the bits that represent the flag are on!
				$response = (($flags & $flag) === $flag);
			}
			else
			{
				throw new \Fuel_Exception('The given flag is not a power of 2. Only power of 2 flags are valid.');
			}
		}

		return $response;
	}

	/**
	 * Removes the count indexes added for every level in the results
	 * As this is a private method we assume the given array is actually an array
	 */
	private static function format_remove_counts_multi(Array $array)
	{
		$response = array();

		// For each value get rid of the count index
		foreach ($array as $key => $value)
		{
			if ($key !== 'count' && $key !== '__attributes')
			{
				$response[$key] = (is_array($value)) ? static::format_remove_counts_multi($value) : $value;
			}
			else
			{
				if (isset($array['__attributes']))
				{
					// remove the count attribute in __attributes
					$response['__attributes'] = array_values(array_diff($array['__attributes'], array('count')));
				}
			}
		}

		return $response;
	}

	/**
	 * Gets rid of the numeric indexed attributes Microsoft's AD adds for every
	 * string key. This just goes into the sencod level, no further.
	 * As this is a private method we assume the given array is actually an array.
	 */
	private static function format_no_num_index_root(Array $array)
	{
		$response = array();

		// Main entry array (only numeric indexes so do nothing here)
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$response[$key] = static::format_no_num_index_item($value);
			}
			else
			{
				$response[$key] = $value;
			}
		}

		return $response;
	}

	private static function format_no_num_index_item(Array $array)
	{
		$response = array();

		// Get rid of those annoying numeric values which hold the keys of the values it
		// has
		foreach ($array as $key => $value)
		{
			// check for numeric indexes who's value is a set attribute and get rid of them
			if (!is_numeric($key) || !isset($array[$array[$key]]))
			{
				$response[$key] = $value;
			}
		}

		return $response;
	}

	/**
	 * Flattens the values that are arrays but only contain one value
	 * As this is a private method we assume the given array is actually an array
	 */
	private static function format_flatten_values_root(Array $array, $root = true)
	{
		$response = array();

		// For each item if is array then flatten (not root anymore) else just set the
		// value
		foreach ($array as $key => $value)
		{
			$response[$key] = ((is_array($value)) ? static::format_flatten_values_item($value, false) : $value);
		}

		return $response;
	}

	/**
	 * Flattens the values that are arrays but only contain one value
	 * As this is a private method we assume the given array is actually an array
	 */
	private static function format_flatten_values_item(Array $array, $root = true)
	{
		$response = array();

		// Check each item on the array
		foreach ($array as $key => $value)
		{
			// If is an array try to flatten, if it has one value if not then flatten the
			// sub-arrays
			if (is_array($value) && $key !== '__attributes')
			{
				$response[$key] = ((count($value) == 1) ? reset($value) : $value);
			}
			else
			{
				$response[$key] = $value;
			}
		}

		return $response;
	}

	private static function format_keys_case_lower_multi(Array $array)
	{
		$response = array();

		foreach ($array as $key => $value)
		{
			// If is an array but it's not the spceial __attributes array we change the case
			if (is_array($value))
			{
				if ($key !== '__attributes')
				{
					$response[strtolower($key)] = static::format_keys_case_lower_multi($value);
				}
				else
				{
					// upper case all __attributes values
					$attributes = array();
					foreach ($value as $attr)
					{
						$attributes[] = strtolower($attr);
					}
					$response[$key] = $attributes;
				}
			}
			else
			{
				$response[strtolower($key)] = $value;
			}
		}

		// We change the attributes so let's see if theres a __attributes array and
		// change them there too
		//if(isset($array['__a']))

		return $response;
	}

	private static function format_keys_case_upper_multi(Array $array)
	{
		$response = array();

		foreach ($array as $key => $value)
		{
			// If is an array but it's not the special __attributes array we change the case
			if (is_array($value))
			{
				if ($key !== '__attributes')
				{
					$response[strtoupper($key)] = static::format_keys_case_upper_multi($value);
				}
				else
				{
					// upper case all __attributes values
					$attributes = array();
					foreach ($value as $attr)
					{
						$attributes[] = strtoupper($attr);
					}
					$response[$key] = $attributes;
				}
			}
			else
			{
				$response[strtoupper($key)] = $value;
			}
		}

		return $response;
	}

	private static function format_sort_by_attributes_root(Array $array)
	{
		$response = array();

		foreach ($array as $value)
		{
			$response[] = static::format_sort_by_attributes_item($value);
		}

		return $response;
	}

	private static function format_sort_by_attributes_item(Array $array)
	{
		$response = array();

		// Get the array keys to sort
		$keys = array_keys($array);
		natcasesort($keys);
		// We do it case-insensitive

		// Sort every key and sub arrays too
		foreach ($keys as $key)
		{
			if (is_array($array[$key]))
			{
				$response[$key] = static::format_sort_by_attributes_item($array[$key]);
			}
			else
			{
				$response[$key] = $array[$key];
			}

		}

		return $response;
	}

}
