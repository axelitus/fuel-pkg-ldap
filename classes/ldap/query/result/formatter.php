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
	 * Some bitwise operations one can do with formatter
	 */
	const LDAP_FORMAT_REMOVE_COUNTS = 1;
	const LDAP_FORMAT_NO_NUM_INDEX = 2;
	const LDAP_FORMAT_FLATTEN_VALUES = 4;

	/**
	 * Formats the results array with the given flags. This is a non-destructive
	 * function as it returns the processed array but leaves the original intact
	 */
	public static function format(Array $array, $flags = 0)
	{
		$response = static::process_flags($array, $flags);

		return $response;
	}

	/**
	 * Process each given flag in the flags parameter with it's corresponding
	 * function. Every function is a non-destructive function.
	 */
	private static function process_flags(Array $array, $flags)
	{
		$response = $array;

		// Let's chain the processing methods here leaving the original array untouched
		// but do this only if we have an array.
		if (is_array($response))
		{
			// Format: Remove Counts
			if (static::is_flag_on($flags, self::LDAP_FORMAT_REMOVE_COUNTS))
			{
				$response = static::format_remove_counts($response);
			}

			// Format: No Num Index
			if (static::is_flag_on($flags, self::LDAP_FORMAT_NO_NUM_INDEX))
			{
				$response = static::format_no_num_index($response);
			}

			// Format: Flatten Values
			if (static::is_flag_on($flags, self::LDAP_FORMAT_FLATTEN_VALUES))
			{
				$response = static::format_flatten_values($response);
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
	private static function format_remove_counts(Array $array)
	{
		$response = array();

		// For each value get rid of the count index
		foreach ($array as $key => $value)
		{
			if ($key !== 'count')
			{
				$response[$key] = (is_array($value)) ? static::format_remove_counts($value) : $value;
			}
		}

		return $response;
	}

	/**
	 * Gets rid of the numeric indexed attributes Microsoft's AD adds for every
	 * string key. This just goes into the sencod level, no further.
	 * As this is a private method we assume the given array is actually an array.
	 */
	private static function format_no_num_index(Array $array)
	{
		$response = array();

		// Main entry array (only numeric indexes so do nothing here)
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$response[$key] = array();

				// second-level. Look for every sub-values
				foreach ($value as $subkey => $subvalue)
				{
					// check for numeric indexes who's value is a set attribute and get rid of them
					if (!is_numeric($subkey) || !isset($value[$value[$subkey]]))
					{
						$response[$key][$subkey] = $subvalue;
					}
				}
			}
			else
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
	private static function format_flatten_values(Array $array, $root = true)
	{
		$response = array();

		// Are we running on root? Let's skip a level
		if ($root)
		{
			// For each item if is array then flatten (not root anymore) else just set the value
			foreach ($array as $key => $value)
			{
				$response[$key] = ((is_array($value)) ? static::format_flatten_values($value, false) : $value);
			}
		}
		else
		{
			// Check each item on the array
			foreach ($array as $key => $value)
			{
				// If is an array try to flatten if it has one value if not then flatten the sub-arrays
				if (is_array($value))
				{
					$response[$key] = ((count($value) == 1) ? $value[0] : static::format_flatten_values($array, false));
				}
				else
				{
					$response[$key] = $value;
				}
			}
		}

		return $response;
	}

}
