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
 * Ldap_Config
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
class Ldap_Config
{
	/**
	 * Some useful constants
	 */
	const CONFIG_GROUP_NAME = 'ldap-config';
	const DEFAULT_PORT = 389;
	const DEFAULT_PORT_SSL = 636;
	const DEFAULT_TIMEOUT = 60;
	const DEFAULT_SSL = false;
	const DEFAULT_TLS = false;

	/**
	 * Config Keys
	 */
	const KEY_DOMAIN = 'domain';
	const KEY_DOMAIN_SUFFIX = 'domain.suffix';
	const KEY_DOMAIN_CONTROLLERS = 'domain.controllers';
	const KEY_CONNECTION = 'connection';
	const KEY_CONNECTION_PORT = 'connection.port';
	const KEY_CONNECTION_TIMEOUT = 'connection.timeout';
	const KEY_CONNECTION_SSL = 'connection.ssl';
	const KEY_CONNECTION_TLS = 'connection.tls';
	const KEY_MASTER = 'master';
	const KEY_MASTER_USER = 'master.user';
	const KEY_MASTER_PASSWORD = 'master.password';

	/**
	 * @var bool whether it's possible to use the arrvalidator package or not
	 */
	protected static $_arvalidator_exists = false;
	
	/**
	 * @var array contains the config values
	 */
	protected $_config = array();

	/**
	 * Prevent direct instantiation
	 */
	private final function __construct()
	{
	}
	
	/**
	 * Initialize class
	 */
	public static function _init()
	{
		static::$_arvalidator_exists = Package::loaded('arrvalidator') || Package::load('arrvalidator');
	}
	
	/**
	 * Returns a config array with the default values.
	 */
	public static function defaults()
	{
		$return = array();
		\Arr::set($return, static::KEY_DOMAIN_SUFFIX, '');
		\Arr::set($return, static::KEY_DOMAIN_CONTROLLERS, array());
		\Arr::set($return, static::KEY_CONNECTION_PORT, static::DEFAULT_PORT);
		\Arr::set($return, static::KEY_CONNECTION_TIMEOUT, static::DEFAULT_TIMEOUT);
		\Arr::set($return, static::KEY_CONNECTION_SSL, static::DEFAULT_SSL);
		\Arr::set($return, static::KEY_CONNECTION_TLS, static::DEFAULT_TLS);
		\Arr::set($return, static::KEY_MASTER_USER, '');
		\Arr::set($return, static::KEY_MASTER_PASSWORD, '');

		return $return;
	}

	/**
	 * Forges a new Ldap_Config object using a value array or a config file string
	 * @see http://fuelphp.com/docs/classes/config.html
	 */
	public final static function forge($options = array())
	{
		$return = null;

		// Is string, array or something else?
		$config = $options;
		if (is_string($config))
		{
			// If $options is a string then we want to load from config file. We can have
			// multiple, so group them using the CONFIG_GROUP_NAME constant and the file name
			$config = \Config::load($config, self::CONFIG_GROUP_NAME . '-' . $config);
		}
		else if(!is_array($options))
		{
			throw new InvalidArgumentException('The $options parameter should be an array or a string of a config file to be loaded.');
		}

		// Parse the configuration array to validate it
		$config = static::parse($config);

		// Create the Ldap_Config instance
		$return = new Ldap_Config();

		// Initialize the config array
		$return->_init($config);

		return $return;
	}

	/**
	 * Initialize the Ldap_Config instance. Use with care as this bypasses all
	 * validation and just sets the config attribute to the given array.
	 */
	/*
	private function _init(Array $config)
	{
		$this->_config = $config;
	}
	 * */
	
	/**
	 * Sets the configuration values to the default ones.
	 * 
	 * @return void
	 */
	public function clear(){
		$this->_config = static::defaults();
	}

	/**
	 * Magic function that gets a config value. The underscores '_' in the name are
	 * transformed to a dot '.', so underscores mean sublevels. Only existent names
	 * are allowed.
	 */
	public function __get($name)
	{
		$return = null;

		$key = str_replace('_', '.', $name);
		if ($this->exists($key))
		{
			$return = $this->_get_item($key);
		}
		else
		{
			throw new Ldap_ConfigException('Undefined property [using __get() magic method]: ' . get_called_class() . '::$' . $name);
		}

		return $return;
	}

	/**
	 * Magic function that sets a config value. The underscores '_' in the name are
	 * transformed to a dot '.', so underscores mean sublevels. Only existent names
	 * are allowed.
	 */
	public function __set($name, $value)
	{
		$key = str_replace('_', '.', $name);
		if ($this->exists($key))
		{
			$this->_set_item_valid($key, $value);
		}
		else
		{
			throw new Ldap_ConfigException('Undefined property [using __set() magic method]: ' . get_called_class() . '::$' . $name);
		}
	}

	/**
	 * Gets the specified item from the config array
	 */
	private function _get_item($key, $default = null)
	{
		return \Arr::get($this->_config, $key, $default);
	}

	/**
	 * Sets the specified item to the config array. This does not handle
	 * validation., Use _set_item_valid() instead if you need to validate the value.
	 */
	private function _set_item($key, $value)
	{
		\Arr::set($this->_config, $key, $value);
	}

	/**
	 * Sets the specified item to the config array prior data validation. The
	 * validation is taken from the $key parameter. As keys are '.' dot-separated,
	 * the function replaces them for '_' underscores. A validation function has to
	 * exist in the form:'_is_valid_[key_with_underscores]' matching the given key.
	 */
	private function _set_item_valid($key, $value)
	{
		if (static::is_valid($key, $value))
		{
			$this->_set_item($key, $value);
		}
	}

	/**
	 * Validates a given value against the given key validator if exists.
	 * If the validator for the key does not exist then false will be returned.
	 */
	public static function is_valid($key, $value)
	{
		$return = false;
		$_key = str_replace('.', '_', $key);

		$class = get_called_class();
		$validation = '_is_valid_' . $_key;
		if (is_callable($class . '::' . $validation))
		{
			$return = call_user_func(array($class, $validation), $value);
		}

		return $return;
	}

	/**
	 * Verifies if a given string represents a key for a value in the configuration.
	 * Subkeys are separated by '.' -> first.second.third...
	 */
	public function exists($key)
	{
		$return = false;

		if (is_string($key) && ($parts = explode('.', trim($key, ' .'))) !== false)
		{
			$item = $this->_config;
			foreach ($parts as $part)
			{
				if (!isset($item[$part]))
				{
					return $return;
				}
				$item = $item[$part];
			}

			$return = true;
		}

		return $return;
	}

	

	/**
	 * Parses a given array.
	 * For all keys not set in array, the defaults will be used.
	 * For the given values it will depend whether they are valid or else the default
	 * will be used.
	 */
	public static function parse(Array $array)
	{
		$return = static::defaults();

		foreach ($return as $key => $value)
		{
			if (isset($array[$key]))
			{
				if (is_array($return[$key]))
				{
					$return[$key] = static::_parse_level($array[$key], $return[$key], $key);
				}
				else
				{
					$return[$key] = (static::is_valid($key, $array[$key]) ? $array[$key] : $return[$key]);
				}
			}
		}

		return $return;
	}

	private static function _parse_level(Array $array, Array $defaults, $parent = '')
	{
		$return = $defaults;
		foreach ($return as $key => $value)
		{
			if (isset($array[$key]))
			{
				if (is_array($return[$key]) && !static::_parse_as_value(static::_glue_keys($parent, $key)))
				{
					$return[$key] = static::_parse_level($array[$key], $return[$key], static::_glue_keys($parent, $key));
				}
				else
				{
					$return[$key] = (static::is_valid(static::_glue_keys($parent, $key), $array[$key]) ? $array[$key] : $return[$key]);
				}
			}
		}

		return $return;
	}

	private static function _parse_as_value($key)
	{
		$return = false;

		$return = ($key == static::_glue_keys(static::KEY_DOMAIN, static::KEY_DOMAIN_CONTROLLERS));

		return $return;
	}

	private static function _glue_keys()
	{
		$args = func_get_args();
		$glue = '.';

		$return = '';
		foreach ($args as $arg)
		{
			if (is_string($arg) && $arg != '')
			{
				$return .= $glue . $arg;
			}
		}
		$return = trim($return, ' .');

		return $return;
	}

	/**
	 * Sets a new config array. The function parses the array first, so all common
	 * config array rules apply.
	 * The whole config array is first set to default values and then the new ones
	 * are overwritten. If you only want to change certain values use the function
	 * replace() instead.
	 */
	public function set_config(Array $config)
	{
		$this->_config = static::parse($config);
	}

	/**
	 * This function sets new values for the items present in the given array leaving
	 * the other values untouched. The values that are not part of this config class
	 * will be ignored. The array must follow the default structure.
	 */
	public function replace_items(Array $array)
	{
		foreach ($array as $key => $value)
		{
			if (isset($this->_config[$key]))
			{
				if (is_array($value))
				{
					$this->_config[$key] = static::_replace_items_level($array[$key], $this->_config[$key], $key);
				}
				else
				{
					$this->_config[$key] = (static::is_valid($key, $array[$key]) ? $array[$key] : $this->_config[$key]);
				}
			}
		}
	}

	private function _replace_items_level(Array $array, Array $config, $parent = '')
	{
		$return = $config;

		foreach ($array as $key => $value)
		{
			if (isset($return[$key]))
			{
				if (is_array($array[$key]) && !static::_parse_as_value(static::_glue_keys($parent, $key)))
				{
					$return[$key] = static::_replace_items_level($array[$key], $return[$key], static::_glue_keys($parent, $key));
				}
				else
				{
					$return[$key] = (static::is_valid(static::_glue_keys($parent, $key), $array[$key]) ? $array[$key] : $return[$key]);
				}
			}
		}

		return $return;
	}
	
	/**
	 * Returns the Ldap_Config instance as an associative array
	 */
	public function as_array()
	{
		return $this->_config;
	}
	
	/**
	 * Gets a value $retVal = (condition) ? a : b ;fom the config. Separate
	 * subvalues with a dot like this:
	 * $config->get('first_level.second_level').
	 */
	public function get_item($key, $default = null)
	{
		$return = $default;

		if ($this->exists($key))
		{
			$key = str_replace('.', '_', $key);

			$return = $this->{$key};
		}

		return $return;
	}

	private final static function _is_complete_domain($value)
	{
		return isset($value[static::KEY_DOMAIN_SUFFIX]) && isset($value[static::KEY_DOMAIN_CONTROLLERS]);
	}

	private final static function _is_valid_domain($value)
	{
		$return = false;

		if (is_array($value) && static::_is_complete_domain($value))
		{
			$return = true;
			foreach ($value as $subkey => $subvalue)
			{
				// Remeber that the keys are absolute, so concat KEY_DOMAIN and $subkey
				if (!static::is_valid(static::_glue_keys(static::KEY_DOMAIN, $subkey), $subvalue))
				{
					$return = false;
					break;
				}
			}
		}

		return $return;
	}

	private final static function _is_valid_domain_suffix($value)
	{
		$return = false;

		$return = is_string($value);

		if (!$return)
		{
			$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

	private final static function _is_valid_domain_controllers($value)
	{
		$return = false;

		$return = is_array($value);

		if (!$return)
		{
			$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

	private final static function _is_complete_connection($value)
	{
		return isset($value[static::KEY_CONNECTION_PORT]) && isset($value[static::KEY_CONNECTION_TIMEOUT]) && isset($value[static::KEY_CONNECTION_SSL]) && isset($value[static::KEY_CONNECTION_TLS]);
	}

	private final static function _is_valid_connection($value)
	{
		$return = false;

		if (is_array($value) && static::_is_complete_connection($value))
		{
			$return = true;
			foreach ($value as $subkey => $subvalue)
			{
				// Remeber that the keys are absolute, so concat KEY_CONNECTION and $subkey
				if (!static::is_valid(static::_glue_keys(static::KEY_CONNECTION, $subkey), $subvalue))
				{
					$return = false;
					break;
				}
			}
		}

		return $return;
	}

	private final static function _is_valid_connection_port($value)
	{
		$return = false;

		$return = is_numeric($value) && $value >= 0 && $value <= 65535;

		if (!$return)
		{
			$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

	private final static function _is_valid_connection_timeout($value)
	{
		$return = false;

		$return = is_numeric($value) && $value >= 0;

		if (!$return)
		{
			$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

	private final static function _is_valid_connection_ssl($value)
	{
		$return = false;

		$return = is_bool($value);

		if (!$return)
		{
			$$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

	private final static function _is_valid_connection_tls($value)
	{
		$return = false;

		$return = is_bool($value);

		if (!$return)
		{
			$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

	private final static function _is_complete_master($value)
	{
		return isset($value[static::KEY_MASTER_USER]) && isset($value[static::KEY_MASTER_PASSWORD]);
	}

	private final static function _is_valid_master($value)
	{
		$return = false;

		if (is_array($value) && static::_is_complete_master($value))
		{
			$return = true;
			foreach ($value as $subkey => $subvalue)
			{
				// Remeber that the keys are absolute, so concat KEY_MASTER and $subkey
				if (!static::is_valid(static::_glue_keys(static::KEY_MASTER, $subkey), $subvalue))
				{
					$return = false;
					break;
				}
			}
		}

		return $return;
	}

	private final static function _is_valid_master_user($value)
	{
		$return = false;

		$return = is_string($value);

		if (!$return)
		{
			$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

	private final static function _is_valid_master_password($value)
	{
		$return = false;

		$return = is_string($value);

		if (!$return)
		{
			$trace = debug_backtrace();
			$data = array( array('class' => $trace[0]['class'], 'type' => $trace[0]['type'], 'function' => $trace[0]['function']));
			if (isset($trace[1]['class']) && isset($trace[1]['type']) && isset($trace[1]['function']))
			{
				$data[] = array('class' => $trace[1]['class'], 'type' => $trace[1]['type'], 'function' => $trace[1]['function']);
			}
			$warning = "Invalid config value found. Key: '" . static::KEY_CONNECTION . "." . static::KEY_CONNECTION_PORT . "' | Value: '{$value}' in {$data[0]['class']}{$data[0]['type']}{$data[0]['function']}()" . ((isset($data[1])) ? "called from {$data[1]['class']}{$data[1]['type']}{$data[1]['function']}()." : "");
			\Log::warning($warning);
		}

		return $return;
	}

}
