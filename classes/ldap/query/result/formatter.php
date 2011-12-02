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
	 * General constants
	 */
	/**
	 * @var int = 0 Unknown Level or Not Given (ROOT will be used by default)
	 */
	const LDAP_RESULT_LEVEL_DEFAULT = 0;

	/**
	 * @var int = 1 Ldap Result array First Level
	 */
	const LDAP_RESULT_LEVEL_ROOT = 1;

	/**
	 * @var int = 2 Ldap Result array Second Level
	 */
	const LDAP_RESULT_LEVEL_ITEM = 2;

	/**
	 * Bitwise operations constants
	 */
	/**
	 * @var int = 1 Tells the formatter to remove the 'count' items from all arrays.
	 */
	const LDAP_FORMAT_REMOVE_COUNTS = 1;

	/**
	 * @var int = 2 Tells the formatter to get rid of the numeric indexed attributes Microsoft's AD adds
	 * for every string key
	 */
	const LDAP_FORMAT_NO_NUM_INDEX = 2;

	/**
	 * @var int = 4 Tells the formatter to flatten the values that are arrays but only contain one value
	 */
	const LDAP_FORMAT_FLATTEN_VALUES = 4;

	/**
	 * @var int = 8 Tells the formatter to lower-case all keys in the resulting array
	 */
	const LDAP_FORMAT_KEYS_CASE_LOWER = 8;

	/**
	 * @var int = 16 Tells the formatter to upper-case all keys in the resulting array
	 */
	const LDAP_FORMAT_KEYS_CASE_UPPER = 16;

	/**
	 * @var int = 32 Tells the formatter to sort the array by attributes key using natcasesort
	 */
	const LDAP_FORMAT_SORT_BY_ATTRIBUTES = 32;

	/**
	 * Prevent direct instantiation.
	 */
	private function __construct()
	{
	}

	/**
	 * Formats the results array with the given flags. This is a non-destructive
	 * function as it returns the processed array but leaves the original intact
	 */
	public static function format(array $array, $flags = 0, $level = self::LDAP_RESULT_LEVEL_DEFAULT)
	{
		$return = static::process_flags($array, $flags, $level);

		return $return;
	}

	/**
	 * Process each given flag in the flags parameter with it's corresponding
	 * function. Every function is a non-destructive function.
	 */
	private static function process_flags(array $array, $flags, $level)
	{
		$return = $array;

		// Let's chain the processing methods here leaving the original array untouched
		// but do this only if we have an array.
		if (is_array($return))
		{
			// Format: Remove Counts
			// Works For All Levels
			if (static::is_flag_on($flags, self::LDAP_FORMAT_REMOVE_COUNTS))
			{
				$return = static::format_remove_counts_multi($return);
			}

			// Format: No Num Index
			// Different Function For Each Level
			if (static::is_flag_on($flags, self::LDAP_FORMAT_NO_NUM_INDEX))
			{
				switch($level)
				{
					case self::LDAP_RESULT_LEVEL_ROOT:
						$return = static::format_no_num_index_root($return);
					break;
					case self::LDAP_RESULT_LEVEL_ITEM:
						$return = static::format_no_num_index_item($return);
					break;
					default:
					// Default to root
						$return = static::format_no_num_index_root($return);
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
						$return = static::format_flatten_values_root($return);
					break;
					case self::LDAP_RESULT_LEVEL_ITEM:
						$return = static::format_flatten_values_item($return);
					break;
					default:
					// Default to root
						$return = static::format_flatten_values_root($return);
					break;
				}
			}

			// Format: Lower Case Keys
			// Works For All Levels
			if (static::is_flag_on($flags, self::LDAP_FORMAT_KEYS_CASE_LOWER))
			{
				$return = static::format_keys_case_lower_multi($return);
			}

			// Format: Upper Case Keys
			// Works For All Levels
			if (static::is_flag_on($flags, self::LDAP_FORMAT_KEYS_CASE_UPPER))
			{
				$return = static::format_keys_case_upper_multi($return);
			}

			// Format: Sort By Attribute
			// Different Function For Each Level
			if (static::is_flag_on($flags, self::LDAP_FORMAT_SORT_BY_ATTRIBUTES))
			{
				switch($level)
				{
					case self::LDAP_RESULT_LEVEL_ROOT:
						$return = static::format_sort_by_attributes_root($return);
					break;
					case self::LDAP_RESULT_LEVEL_ITEM:
						$return = static::format_sort_by_attributes_item($return);
					break;
					default:
					// Default to root
						$return = static::format_sort_by_attributes_root($return);
					break;
				}
			}
		}

		return $return;
	}

	/**
	 * Cehcks if the given flag is set in the given flags
	 */
	private static function is_flag_on($flags, $flag)
	{
		$return = false;

		// Work only with numeric and positive flags
		if (is_numeric($flags) && $flags >= 0 && is_numeric($flag) && $flag > 0)
		{
			// Verify if the given flag is a power of 2. If not then there can be weird
			// issues as then some numbers would share bits, so let's not complicate ourselfs
			// trying to guess and just throw an exception
			if (($flag & ($flag - 1)) === 0)
			{
				// Do some bitwise magic here and see if the bits that represent the flag are on!
				$return = (($flags & $flag) === $flag);
			}
			else
			{
				throw new \Fuel_Exception('The given flag is not a power of 2. Only power of 2 flags are valid.');
			}
		}

		return $return;
	}

	/**
	 * Removes the count indexes added for every level in the results
	 * As this is a private method we assume the given array is actually an array
	 */
	private static function format_remove_counts_multi(array $array)
	{
		$return = array();

		// For each value get rid of the count index
		foreach ($array as $key => $value)
		{
			if ($key !== 'count' && $key !== '__attributes')
			{
				$return[$key] = (is_array($value)) ? static::format_remove_counts_multi($value) : $value;
			}
			else
			{
				if (isset($array['__attributes']))
				{
					// remove the count attribute in __attributes
					$return['__attributes'] = array_values(array_diff($array['__attributes'], array('count')));
				}
			}
		}

		return $return;
	}

	/**
	 * Gets rid of the numeric indexed attributes Microsoft's AD adds for every
	 * string key. This just goes into the sencod level, no further.
	 * As this is a private method we assume the given array is actually an array.
	 */
	private static function format_no_num_index_root(array $array)
	{
		$return = array();

		// Main entry array (only numeric indexes so do nothing here)
		foreach ($array as $key => $value)
		{
			if (is_array($value))
			{
				$return[$key] = static::format_no_num_index_item($value);
			}
			else
			{
				$return[$key] = $value;
			}
		}

		return $return;
	}

	private static function format_no_num_index_item(array $array)
	{
		$return = array();

		// Get rid of those annoying numeric values which hold the keys of the values it
		// has
		foreach ($array as $key => $value)
		{
			// check for numeric indexes who's value is a set attribute and get rid of them
			if ( ! is_numeric($key) || ! isset($array[$array[$key]]))
			{
				$return[$key] = $value;
			}
		}

		return $return;
	}

	/**
	 * Flattens the values that are arrays but only contain one value.
	 */
	private static function format_flatten_values_root(array $array, $root = true)
	{
		$return = array();

		// For each item if is array then flatten (not root anymore) else just set the
		// value
		foreach ($array as $key => $value)
		{
			$return[$key] = ((is_array($value)) ? static::format_flatten_values_item($value, false) : $value);
		}

		return $return;
	}

	/**
	 * Flattens the values that are arrays but only contain one value.
	 */
	private static function format_flatten_values_item(array $array, $root = true)
	{
		$return = array();

		// Check each item on the array
		foreach ($array as $key => $value)
		{
			// If is an array try to flatten, if it has one value if not then flatten the
			// sub-arrays
			if (is_array($value) && $key !== '__attributes')
			{
				$return[$key] = ((count($value) == 1) ? reset($value) : $value);
			}
			else
			{
				$return[$key] = $value;
			}
		}

		return $return;
	}

	private static function format_keys_case_lower_multi(array $array)
	{
		$return = array();

		foreach ($array as $key => $value)
		{
			// If is an array but it's not the spceial __attributes array we change the case
			if (is_array($value))
			{
				if ($key !== '__attributes')
				{
					$return[strtolower($key)] = static::format_keys_case_lower_multi($value);
				}
				else
				{
					// upper case all __attributes values
					$attributes = array();
					foreach ($value as $attr)
					{
						$attributes[] = strtolower($attr);
					}
					$return[$key] = $attributes;
				}
			}
			else
			{
				$return[strtolower($key)] = $value;
			}
		}

		// We change the attributes so let's see if theres a __attributes array and
		// change them there too
		//if(isset($array['__a']))

		return $return;
	}

	private static function format_keys_case_upper_multi(array $array)
	{
		$return = array();

		foreach ($array as $key => $value)
		{
			// If is an array but it's not the special __attributes array we change the case
			if (is_array($value))
			{
				if ($key !== '__attributes')
				{
					$return[strtoupper($key)] = static::format_keys_case_upper_multi($value);
				}
				else
				{
					// upper case all __attributes values
					$attributes = array();
					foreach ($value as $attr)
					{
						$attributes[] = strtoupper($attr);
					}
					$return[$key] = $attributes;
				}
			}
			else
			{
				$return[strtoupper($key)] = $value;
			}
		}

		return $return;
	}

	private static function format_sort_by_attributes_root(array $array)
	{
		$return = array();

		foreach ($array as $value)
		{
			$return[] = static::format_sort_by_attributes_item($value);
		}

		return $return;
	}

	private static function format_sort_by_attributes_item(array $array)
	{
		$return = array();

		// Get the array keys to sort
		$keys = array_keys($array);
		// We do it case-insensitive
		natcasesort($keys);

		// Sort every key and sub arrays too
		foreach ($keys as $key)
		{
			if (is_array($array[$key]))
			{
				$return[$key] = static::format_sort_by_attributes_item($array[$key]);
			}
			else
			{
				$return[$key] = $array[$key];
			}

		}

		return $return;
	}

	/**
	 * The GUID functions where taken from the posts in
	 * http://php.net/manual/en/function.ldap-get-values-len.php
	 */

	/**
	 * This function is used to get a readable GUID string from a binary GUID.
	 * This is the string that most LDAP browsers and Active Directory display as the
	 * object's GUID.
	 *
	 */
	public static function guid_bin_to_str($bin_guid, $enclosed = false)
	{
		$return = '';

		$hex_guid = bin2hex($bin_guid);

		$hex_guid_to_guid_str = '';
		for ($k = 1; $k <= 4; ++$k)
		{
			$hex_guid_to_guid_str .= substr($hex_guid, 8 - 2 * $k, 2);
		}
		$hex_guid_to_guid_str .= '-';
		for ($k = 1; $k <= 2; ++$k)
		{
			$hex_guid_to_guid_str .= substr($hex_guid, 12 - 2 * $k, 2);
		}
		$hex_guid_to_guid_str .= '-';
		for ($k = 1; $k <= 2; ++$k)
		{
			$hex_guid_to_guid_str .= substr($hex_guid, 16 - 2 * $k, 2);
		}
		$hex_guid_to_guid_str .= '-'.substr($hex_guid, 16, 4);
		$hex_guid_to_guid_str .= '-'.substr($hex_guid, 20);

		$return = (($enclosed) ? static::guid_str_enclose(strtoupper($hex_guid_to_guid_str)) : strtoupper($hex_guid_to_guid_str));

		return $return;
	}

	public static function guid_str_enclose($guid_str)
	{
		$return = '';

		if (strpos($guid_str, '{') === false && strpos($guid_str, '}') === false)
		{
			$return = '{'.$guid_str.'}';
		}

		return $return;
	}

	/**
	 * The actual string to search in the LDAP directories is an octet string.
	 * This string is the same as the GUID str, except it has some bytes swapped.
	 * (Little-Endian vs. Big-Endian). This function converts a Readable GUID str to
	 * the search-ready formatted string. Use with $escaped = true for escaping the
	 * byte pairs with a '\'. This is needed for searching!
	 */
	public static function guid_str_to_octet_str($str_guid, $escaped = false)
	{
		$str_guid = str_replace('-', '', $str_guid);

		$octet_str = (($escaped) ? '\\' : '').substr($str_guid, 6, 2);
		$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, 4, 2);
		$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, 2, 2);
		$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, 0, 2);
		$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, 10, 2);
		$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, 8, 2);
		$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, 14, 2);
		$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, 12, 2);
		for ($i = 16; $i < strlen($str_guid); $i = $i + 2)
		{
			$octet_str .= (($escaped) ? '\\' : '').substr($str_guid, $i, 2);
		}

		return $octet_str;
	}

	public static function guid_octet_str_escape($octet_str)
	{
		$return = '';
		if (strpos($octet_str, '\\') === false)
		{
			if (strlen($octet_str) % 2 != 0)
			{
				$octet_str .= '0';
			}

			$len = strlen($octet_str);
			for ($i = 0; $i < $len - 1; $i = $i + 2)
			{
				$return .= '\\'.$octet_str[$i].$octet_str[$i + 1];
			}
		}

		return $return;
	}

	/**
	 * Takes an octet string and transforms it to a readable GUID string.
	 */
	public static function guid_octet_str_to_str($octet_str)
	{
		return static::guid_bin_to_str(static::guid_octet_str_to_bin($octet_str));
	}

	/**
	 * Takes an octet string and converts it to a binary GUID
	 */
	public static function guid_octet_str_to_bin($octet_str)
	{
		$octet_str = str_replace('\\', '', $octet_str);

		$bin = "";
		$i = 0;
		do
		{
			$bin .= chr(hexdec($octet_str{$i}.$octet_str{($i + 1)}));
			$i += 2;
		} while ( $i < strlen($octet_str) );
		return $bin;
	}

}
