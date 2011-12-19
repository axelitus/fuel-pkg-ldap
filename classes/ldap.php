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

// @formatter:off
class LdapNotSupportedException extends \FuelException {}

class LdapConnectionException extends \FuelException {}
// @formatter:on

/**
 * Ldap
 *
 * @package     Fuel
 * @subpackage  Ldap
 * @author      Axel Pardemann (http://github.com/axelitus)
 * @link        http://github.com/axelitus/fuel-pkg-ldap
 */
class Ldap
{
	const VERSION = '1.0';
	
	/**
	 * Some useful LDAP constants
	 */
	const ORGANIZATIONAL_UNIT = 'OU';
	const CONTAINER = 'CN';
	const RESOURCE_LINK = 'ldap link';
	const RESOURCE_RESULT = 'ldap result';

	/**
	 * @var array contains references to Ldap instances if multiple were loaded
	 */
	protected static $_instances = array();

	// @formatter:off
	/**
	 * @var array contains the default config values
	 */
	protected static $_config_defaults = array(
		'domain' => array(
			'suffix' => '',
			'controllers' => array()
		),
		'connection' => array(
			'port' => 389,
			'timeout' => 60,
			'ssl' => false,
			'tls' => false
		),
		'master' => array(
			'user' => '',
			'pwd' => '')
		);
	// @formatter:on

	/**
	 * @var string|int contains the name of the instance (string or numeric index)
	 */
	protected $_name = '';

	/**
	 * @var array contains the instance configuration object
	 */
	protected $_config = array();

	/**
	 * @var resource contains the LDAP link identifier when connected
	 */
	protected $_connection = null;

	/**
	 * @var bool contains a flag whether there's a binding to Ldap or not
	 */
	protected $_binded = false;

	/**
	 * @var string contains the Base DN of the Ldap binding
	 */
	protected $_base_dn = '';

	/**
	 * Prevent direct instantiation
	 */
	private function __construct($name)
	{
		$this->_name = $name;
	}

	/**
	 * Detects if Ldap is supported or not
	 *
	 * @return bool whether ldap is supported in the PHP install or not.
	 */
	public final static function ldap_supported()
	{
		return function_exists('ldap_connect');
	}

	/**
	 * Initializes the class. This is automatically called by the Autoloader class.
	 *
	 * @return void
	 */
	public static function _init()
	{
		if ( ! static::ldap_supported())
		{
			\Log::warning('Ldap\Ldap::_init() - Ldap is not supported. Please make sure that the extension php_ldap is correctly loaded.');
		}
	}

	/**
	 * Clears the instance (this means that it unbinds from Ldap server and kills the connection). The
	 * instance name is the only thing that gets not _cleared. This is by design, as it could break the
	 * functionality.
	 *
	 * @param bool $clear_config optional whether to also clear the config or not.
	 */
	private function _clear($clear_config = false)
	{
		$this->unbind();
		$this->_connection = null;
		$this->_binded = false;
		$this->_base_dn = '';
		if ($clear_config)
		{
			$this->config_clear();
		}
	}

	/**
	 * Forges a new instance of Ldap or retrieves the existing one. If the $overwrite flag is set to
	 * true, then the instance will be overwritten.
	 *
	 * @param string $name the Ldap instance identifier (non-empty string).
	 * @param bool $overwrite optional whether to force an instance overwritting if one already exists.
	 * @param string|array optional the Ldap config to load as a path to a file or the array itself (this
	 * will be used only if a NEW instance is forged, to change the config of an existing instance see
	 * Ldap::config_set() or Ldap::config_array() methods).
	 * @return Ldap the forged object or the previously existing one.
	 */
	public static function forge($name, $overwrite = false, $config = '')
	{
		if ( ! static::ldap_supported())
		{
			throw new LdapNotSupportedException('Ldap is not supported. Make sure that the extension php_ldap is loaded.');
		}

		// Validate the name
		if ( ! is_string($name) || $name == '')
		{
			throw new \InvalidArgumentException('The $name param must be a non-empty string.');
		}

		// If we set the overwrite flag get rid of the instance if it already exists
		if ($overwrite === true)
		{
			static::remove($name);
		}

		// Create the instance if it does not exist
		if ( ! static::exists($name))
		{
			$instance = new static($name);

			// Did we pass the config array or path with the overwrite param
			if (is_string($overwrite) || is_array($overwrite))
			{
				$config = $overwrite;
			}

			// Load the config if needed
			$instance->config_load($config);

			// Place the instance in the instances array
			static::$_instances[$name] = $instance;
		}

		return static::$_instances[$name];
	}

	/**
	 * Gets an instance by name. If no instance is found by the given name, a new one will be forged.
	 * If name is ommitted then the first element in the instances array will be returned if there's one.
	 *
	 * @param string $name optional the Ldap instance identifier.
	 * @param string|array optional the Ldap config to load as a path to a file or the array itself (this
	 * will be used only if a NEW instance is forged, to change the config of an existing instance see
	 * Ldap::config_set() or Ldap::config_array() methods).
	 * @return Ldap the existing instance or a newly forged one.
	 */
	public static function instance($name = '', $config = '')
	{
		// If we don't have any instances forge a new one only if name is not empty.
		if (empty(static::$_instances))
		{
			if ($name != '')
			{
				return static::forge($name, $config);
			}
			else
			{
				throw new InvalidArgumentException('There are no instances loaded and one cannot be created with an empty name');
			}
		}

		// Do we want the first instance?
		if ($name === '')
		{
			return reset(static::$_instances);
		}

		// If exists with given name
		if (static::exists($name))
		{
			return static::$_instances[$name];
		}

		// Nothing was found so forge a new one
		return static::forge($name, $config);
	}

	/**
	 * Verifies if the instance exists.
	 *
	 * @param string $name the name of the Ldap instance to check for.
	 * @return bool whether the named Ldap instance exists or not.
	 */
	public static function exists($name)
	{
		// We don't use \Arr::key_exists() because we don't have a multi-dimensional array
		return array_key_exists($name, static::$_instances);
	}

	/**
	 * Removes a loaded Ldap instance.
	 *
	 * @param string $name the Ldap instance identifier to remove.
	 * @return void
	 */
	public static function remove($name)
	{
		if ( ! empty(static::$_instances))
		{
			// We don't use \Arr::delete() because we don't have a multi-dimensional array
			unset(static::$_instances[$name]);
		}
	}

	/**
	 * Gets the instance's name.
	 *
	 * @return string the name of the instance
	 */
	public function get_name()
	{
		return $this->_name;
	}

	/**
	 * Gets the instance's connection.
	 *
	 * @return resource (ldap link) the instance's connection.
	 */
	public function get_connection()
	{
		return $this->_connection;
	}

	/**
	 * Gets the instance's raw binded flag.
	 *
	 * @return bool whether the isntance is binded to Ldap.
	 */
	public function get_binded()
	{
		return $this->_binded;
	}

	/**
	 * Gets the instance's name.
	 *
	 * @return string the name of the instance
	 */
	public function get_base_dn()
	{
		return $this->_base_dn;
	}

	/**
	 * Gets a config value using dot-notated key or $default if $key is not found.
	 *
	 * @param string $key the dot-notated config value key.
	 * @param mixed $default optional default value to return if config key is not found.
	 * @return mixed the config value.
	 */
	public function config_get($key, $default = null)
	{
		return \Arr::get($this->_config, $key, $default);
	}

	/**
	 * Sets a config value using dot-notated key.
	 *
	 * @param string $key the dot-notated config value key.
	 * @param mixed $value the value for the config key.
	 * @return void
	 */
	public function config_set($key, $value)
	{
		\Arr::set($this->_config, $key, $value);
	}

	/**
	 * Sets the config to the default values.
	 *
	 * @return void
	 */
	public function clear_config()
	{
		$this->_config = static::$_config_defaults;
	}

	/**
	 * Gets the config array or sets it if a $replace array was given.
	 * The replace array is merged with the default config values so it is possible to give a partial or
	 * even an empty array to reset the values to the config defaults.
	 *
	 * @param array $replace optional the array to replace the config values for the isntance.
	 * @return array the current config array.
	 */
	public function config_array($replace = null)
	{
		if (is_array($replace))
		{
			$this->_config = \Arr::merge(static::$_config_defaults, $replace);
		}

		return $this->_config;
	}

	/**
	 * Loads a config into the Ldap instance.
	 *
	 * @param string|array $config the config path or array to be loaded.
	 * @return array the loaded config array.
	 */
	public function config_load($config)
	{
		if (is_string($config) && $config != '')
		{
			$this->config_file_load($config);
		}
		elseif (is_array($config))
		{
			$this->config_array($config);
		}

		return $this->_config;
	}

	/**
	 * Loads an Ldap config file into the config array.
	 * The replace array is merged with the default config values so it is possible to give a partial
	 * array.
	 *
	 * @return array the loaded config array.
	 */
	public function config_file_load($path)
	{
		return $this->config_array(\Config::load($path));
	}

	/**
	 * Saves the Ldap config array to file.
	 *
	 * @return bool see \Config::save() method
	 */
	public function config_file_save($path)
	{
		return \Config::save($path, $this->_config);
	}

	/**
	 * Checks if there's a connection to Ldap (Note: it is not the same as binded!)
	 *
	 * @param bool $try_connect optional whether to try to connect if no conenction is found.
	 * @return bool whether there's a connection or not.
	 */
	public function is_connected($try_connect = false)
	{
		$return = false;

		if (isset($this->_connection) && is_resource($this->_connection) && get_resource_type($this->_connection) == self::RESOURCE_LINK)
		{
			$return = true;
		}
		else if ($try_connect)
		{
			$return = $this->connect();
		}

		return $return;
	}

	/**
	 * Checks if we are binded to Ldap (Note: it is not the same as just connected!)
	 *
	 * @param bool $try_bind optional whether to try a binding if the instance is not binded.
	 * @param bool $anonymous optional whether the try to bind should be an anonymous try.
	 * @return bool whether the instance is binded or not.
	 */
	public function is_binded($try_bind = false, $anonymous = false)
	{
		$return = false;

		if (isset($this->_binded) && $this->_binded == true)
		{
			$return = true;
		}
		else if ($try_bind)
		{
			$return = $this->bind($anonymous);
		}

		return $return;
	}

	/**
	 * Makes a connection to Ldap. Please refer to PHP's ldap_connect function (@link
	 * http://php.net/manual/en/function.ldap-connect.php).
	 *
	 * @param bool $chain optional whether to return the instance for chainng or the default return
	 * values.
	 * @return bool|Ldap depends on the $chain param. True if it connected successfully or false if not.
	 */
	public function connect($chain = false)
	{
		$return = false;

		// Aren't we already connected?
		if ( ! ($return = $this->is_connected()))
		{
			// Get some configuration values needed to connect
			$domain_controller = trim($this->_random_domain_controller());
			if ($domain_controller !== '')
			{
				$host = $domain_controller;
				if (\Arr::get($this->_config, 'connection.ssl', false))
				{
					$host = "ldaps://".$host;
				}

				$port = \Arr::get($this->_config, 'connection.port', 389);

				// Connect to that damn Ldap!
				$this->_connection = @ldap_connect($host, $port);
				if ($return = $this->is_connected())
				{
					// Set some ldap options for correct communication
					ldap_set_option($this->_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
					ldap_set_option($this->_connection, LDAP_OPT_REFERRALS, 0);

					// Start TLS if configured
					if (\Arr::get($this->_config, 'connection.tls', false))
					{
						ldap_start_tls($this->_connection);
					}
				}
			}
			else
			{
				throw new LdapConnectionException('Cannot connect: There are no domain controllers to connect to. Please check your configuration.');
			}
		}

		// If we want to chain methods then set the response to this instance
		$return = (($chain) ? $this : $return);

		return $return;
	}

	/**
	 * Bind to Ldap anonymously or by using the master credentials from config.
	 *
	 * @param bool $anonymous optional whether to bind anonymously or by using the master credentials
	 * form config.
	 * @param bool $chain optional whether to return the instance for chainng or the default return
	 * values.
	 * @return bool|Ldap depends on the $chain param. True if it binded successfully or false if not.
	 */
	public function bind($anonymous = false, $chain = false)
	{
		$return = false;

		// Are we connected? If not, then try to ensure we have a connection by using the try_connect
		// parameter
		if ($this->is_connected(true))
		{
			if ($anonymous)
			{
				// We don't need user and password as we are binding anonymously don't we?
				$master_user = '';
				$master_pwd = '';
			}
			else
			{
				// Prevent anonymous binding by checking if a username and password has been set
				if (($master_user = trim(\Arr::get($this->_config, 'master.user', ''))) == '' || ($master_pwd = trim(\Arr::get($this->_config, 'master.pwd', ''))) == '')
				{
					return $return;
				}
				else
				{
					$master_user = static::full_qualified_id($master_user, \Arr::get($this->_config, 'domain_suffix', ''));
				}
			}

			// Bind the damn thing to Ldap!
			$this->_binded = @ldap_bind($this->_connection, $master_user, $master_pwd);
			if ($this->_binded)
			{
				// Succesful binding! Let's try to get the Base DN!
				$this->_base_dn = $this->_find_base_dn();
				if ($this->_base_dn != '')
				{
					$return = true;
				}
			}
		}
		else
		{
			throw new \FuelException('Cannot bind: there is no connection to LDAP server.');
		}

		// if we want to chain methods then set the response to this instance
		$return = (($chain) ? $this : $return);

		return $return;
	}

	/**
	 * Bind to Ldap with the given credentials. This is used for user authentication in LDAP server.
	 *
	 * @param string $username_or_email the username or email to be used to authenticate.
	 * @param string $password the password to be used to authenticate.
	 * @param bool $rebind_as_master whether to re-bind as master after user authentication.
	 * @return bool whether the authentication succeeded or not.
	 */
	public function bind_credentials($username_or_email, $password, $rebind_as_master = true)
	{
		$return = false;

		// Are we connected? If not, then try to ensure we have a connection by using the
		// try_connect parameter
		if ($this->is_connected(true))
		{
			// Prevent anonymous binding by checking if a username and password has been set
			$full_id = static::full_qualified_id($username_or_email, \Arr::get($this->_config, 'domain.suffix', ''));
			$password = (is_string($password) ? trim($password) : '');
			
			// Verify that the full_id or password are not empty
			if ($full_id != '' && $password != '')
			{
				// Bind the damn thing to Ldap with credentials!
				$return = $this->_binded = @ldap_bind($this->_connection, $full_id, $password);
				if ($this->_binded)
				{
					// Succesful binding! Let's try to get the Base DN!
					$this->_base_dn = $this->_find_base_dn();
				}

				if ($rebind_as_master)
				{
					//  Try to rebind as master. There's no way to know if re-binding was correct
					// using the return value. Use $ldap->is_binded() after function call for that
					$this->bind();
				}
			}
		}
		else
		{
			throw new \FuelException('Cannot bind: there is no connection to LDAP server.');
		}

		return $return;
	}

	/**
	 * Unbinds the Ldap instance.
	 *
	 * @return bool whether the unbinding was succesfull or not.
	 */
	public function unbind()
	{
		// if Ldap is binded try to unbind it before so we free resources
		if ($this->_binded)
		{
			try
			{
				$this->_binded = ! @ldap_unbind($this->_connection);
			}
			catch(Exception $e)
			{
				// Do nothing here. This catch is left blank on purpose
			}
		}

		return ! $this->_binded;
	}

	/**
	 * Disconnects (and unbinds) the Ldap connection. This acts like a public alias to the function
	 * _clear() (which handles our unbinding and variable cleanup).
	 *
	 * @return void
	 */
	public function disconnect()
	{
		$this->_clear();
	}

	/**
	 * Checks if there's an Ldap error.
	 *
	 * @return bool whether there's an Ldap error or not.
	 */
	public function has_error()
	{
		$return = false;

		if ($this->is_connected() && @ldap_errno($this->_connection) !== 0)
		{
			$return = true;
		}

		return $return;
	}

	/**
	 * Gets the last Ldap error.
	 *
	 * @return array the last Ldap error in the form array('number' => X, 'message' => Y).
	 */
	public function get_error()
	{
		// @formatter:off
		$return = array(
			'number' => 0,
			'message' => ''
		);
		// @formatter:on

		if ($this->is_connected() && ($return['number'] = ldap_errno($this->_connection)) !== 0)
		{
			$return['message'] = ldap_error($this->_connection);
		}

		return $return;
	}

	/**
	 * Generates an Ldap_Query to be executed in the Ldap server
	 *
	 * @return Ldap_Query object containing the query to be executed.
	 */
	public function query($filter = '')
	{
		$return = Ldap_Query::forge($this);
		$return->set_filter($filter);

		return $return;
	}

	/**
	 * Creates an Auth instance using LdapAuth driver. The Auth package must me present. This function will try
	 * to autoload it if is not already loaded.
	 * 
	 * @return Auth object using LdapAuth driver.
	 */
	public function auth($config = array())
	{
		// Try to load Auth package if is not loaded
		\Package::load('auth');

		// Create the Auth instance for this Ldap
		$return = \Auth::forge(array('driver' => 'Ldap\LdapAuth', 'id' => $this->get_name(), 'ldap' => $this, 'config' => $config));
		
		return $return;
	}

	/**
	 * Parses a username or email and returns the full qualified id. It can also use the domain suffix
	 * for that if a username is given.
	 *
	 * @param string $username_or_email the username or email to parse and work on.
	 * @param string $domain_suffix optional the domain suffix used if only a username was given.
	 * @return string the full qualified id for the given username.
	 */
	public static function full_qualified_id($username_or_email, $domain_suffix = '')
	{
		$return = trim($username_or_email);
		$use_domain_suffix = false;

		// is the username_or_email a string and is not empty?
		// consider as empty also a string with only n whitespaces
		if (is_string($return) && $return != '')
		{
			// Is there an @ in the username_or_email string?
			if (($pos = strpos($return, '@')) !== false)
			{
				// We have a composited id with an arbitrary suffix
				// separate the username_or_email string into id and suffix
				$id = trim(substr($return, 0, $pos));
				$suffix = trim(substr($return, $pos + 1));

				// is the id part empty?
				if ($id != '')
				{
					// set the response to the id part and begin assembling the full qualified id
					$return = $id;

					// is the suffix part empty?
					if ($suffix != '')
					{
						// does the suffix part include another @?
						if (strpos($suffix, '@') === false)
						{
							// we can safely build the rest of the full qualified id
							$return .= '@'.$suffix;
						}
						else
						{
							throw new \FuelException('There can only be one @ in the username_or_email string');
						}
					}
					else
					{
						$use_domain_suffix = true;
					}
				}
				else
				{
					throw new \FuelException('The id part of the username_or_email string (usually the substring that is before the @) cannot be an empty string');
				}
			}
			else
			{
				$use_domain_suffix = true;
			}
		}
		else
		{
			throw new \FuelException('The username_or_email parameter must be a non-empty string');
		}

		// Let's use the domain_suffix part as needed if it's a non-empty string
		if ($use_domain_suffix && is_string($domain_suffix) && $domain_suffix != '')
		{
			// does the domain_suffix part include an @ or not?
			if (($pos = strpos($domain_suffix, '@') === false))
			{
				// the domain_suffix part does not include an @ so it's safely to build the rest
				// of the full qualified id
				$return .= '@'.$domain_suffix;
			}
			else if ($pos == 0)
			{
				// The domain_suffix includes an @ at the beginning, see if it's the only @
				if (strpos($domain_suffix, '@', 1) === false)
				{
					// there's only one @ in the domain_suffix and it's at the beginning so it's safe
					// to build the rest of the full qualified id
					$return .= $domain_suffix;
				}
				else
				{
					// throw exception
					throw new \FuelException('There can only be one @ in the domain_suffix string');
				}
			}
			else
			{
				// throw exception
				throw new \FuelException('The domain_suffix string is incorrect. If there\'s an @ in the string, it can only be at the beginning');
			}
		}

		return $return;
	}

	/**
	 * Returns a randomized domain controller from config.
	 *
	 * @return null|string the randomized domain controller uri or null if array is empty.
	 */
	private function _random_domain_controller()
	{
		$return = null;

		// Get config value or an empty array if not found
		$domain_controllers = \Arr::get($this->_config, 'domain.controllers', array());
		if ( ! empty($domain_controllers))
		{
			$return = $domain_controllers[array_rand($domain_controllers)];
		}

		return $return;
	}

	/**
	 * Tries to find the base dn of a binding.
	 * This is a modified function based on the adLDAP source code (@link
	 * http://adldap.sourceforge.net/).
	 *
	 * @return string the Base DN for the binding.
	 */
	private function _find_base_dn()
	{
		$return = '';

		// Get the naming contexts!
		$namingContexts = $this->_get_root_dse(array('defaultnamingcontext'));
		if ($namingContexts === null || ! isset($namingContexts[0]['defaultnamingcontext'][0]))
		{
			// Let's try that again but with different parameters (Differences between
			// Windows and other Ldap sources)
			$namingContexts = $this->_get_root_dse(array('namingcontexts'));
			if ($namingContexts !== null)
			{
				// That was our last chance so if we don't have a naming context then it's an
				// empty string and that's that!
				if (isset($namingContexts[0]['namingcontexts'][0]))
				{
					$return = $namingContexts[0]['namingcontexts'][0];
				}
				else
				{
					$return = '';
				}
			}
			else
			{
				throw new \FuelException('Cannot find base dn.');
			}
		}
		else
		{
			$return = $namingContexts[0]['defaultnamingcontext'][0];
		}

		return $return;
	}

	/**
	 * Tries to get the root dse (naming context).
	 * This is a modified function based on the adLDAP source code (@link
	 * http://adldap.sourceforge.net/).
	 *
	 * @param array $attributes optional the attributes to look for and find the root dse
	 * @return array of Ldap search entries containing the root dse.
	 */
	private function _get_root_dse($attributes = array("*", "+"))
	{
		$return = null;

		if ($this->is_connected())
		{
			$sr = @ldap_read($this->_connection, null, 'objectClass=*', $attributes);
			if (is_resource($sr) && get_resource_type($sr) == self::RESOURCE_RESULT)
			{
				$return = @ldap_get_entries($this->_connection, $sr);
			}
		}

		return $return;
	}

}
