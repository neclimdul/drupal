<?php

/**
 * @file
 * Contains \Drupal\Core\Site\Settings.
 */

namespace Drupal\Core\Site;

use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;

/**
 * Read only settings that are initialized with the class.
 *
 * @ingroup utility
 */
final class Settings {

  /**
   * Array with the settings.
   *
   * @var array
   */
  private $storage = array();

  /**
   * Singleton instance.
   *
   * @var \Drupal\Core\Site\Settings
   */
  private static $instance;

  /**
   * The site directory from which to read settings.php.
   *
   * @var string
   */
  private static $confPath;

  /**
   * Constructor.
   *
   * @param array $settings
   *   Array with the settings.
   */
  public function __construct(array $settings) {
    $this->storage = $settings;
    self::$instance = $this;
  }

  /**
   * Returns the settings instance.
   *
   * A singleton is used because this class is used before the container is
   * available.
   *
   * @return \Drupal\Core\Site\Settings
   */
  public static function getInstance() {
    return self::$instance;
  }

  /**
   * Returns a setting.
   *
   * Settings can be set in settings.php in the $settings array and requested
   * by this function. Settings should be used over configuration for read-only,
   * possibly low bootstrap configuration that is environment specific.
   *
   * @param string $name
   *   The name of the setting to return.
   * @param mixed $default
   *   (optional) The default value to use if this setting is not set.
   *
   * @return mixed
   *   The value of the setting, the provided default if not set.
   */
  public static function get($name, $default = NULL) {
    return isset(self::$instance->storage[$name]) ? self::$instance->storage[$name] : $default;
  }

  /**
   * Returns all the settings. This is only used for testing purposes.
   *
   * @return array
   *   All the settings.
   */
  public static function getAll() {
    return self::$instance->storage;
  }

  /**
   * Bootstraps settings.php and the Settings singleton.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public static function initialize(Request $request) {
    // Export these settings.php variables to the global namespace.
    global $base_url, $cookie_domain, $config_directories, $config;
    $settings = array();
    $config = array();
    $databases = array();

    // Make conf_path() available as local variable in settings.php.
    $conf_path = static::confPath($request);
    if (is_readable(DRUPAL_ROOT . '/' . $conf_path . '/settings.php')) {
      require DRUPAL_ROOT . '/' . $conf_path . '/settings.php';
    }

    // Initialize Database.
    Database::setMultipleConnectionInfo($databases);

    // Initialize Settings.
    new Settings($settings);
  }

  /**
   * Returns the appropriate configuration directory.
   *
   * Returns the configuration path based on the site's hostname, port, and
   * pathname. Uses find_conf_path() to find the current configuration
   * directory. See default.settings.php for examples on how the URL is
   * converted to a directory.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param bool $require_settings
   *   Only configuration directories with an existing settings.php file
   *   will be recognized. Defaults to TRUE. During initial installation,
   *   this is set to FALSE so that Drupal can detect a matching directory,
   *   then create a new settings.php file in it.
   * @param bool $reset
   *   Force a full search for matching directories even if one had been
   *   found previously. Defaults to FALSE.
   *
   * @return string
   *   The path of the matching directory.
   *
   * @see default.settings.php
   */
  public static function confPath(Request $request, $require_settings = TRUE, $reset = FALSE) {
    if (isset(static::$confPath) && !$reset) {
      return static::$confPath;
    }

    // Check for a simpletest override.
    if ($test_prefix = drupal_valid_test_ua()) {
      static::$confPath = 'sites/simpletest/' . substr($test_prefix, 10);
      return static::$confPath;
    }

    // Otherwise, use the normal $conf_path.
    $script_name = $request->server->get('SCRIPT_NAME');
    if (!$script_name) {
      $script_name = $request->server->get('SCRIPT_FILENAME');
    }
    $http_host = $request->server->get('HTTP_HOST');
    static::$confPath = find_conf_path($http_host, $script_name, $require_settings);
    return static::$confPath;
  }

  /**
   * Gets a salt useful for hardening against SQL injection.
   *
   * @return string
   *   A salt based on information in settings.php, not in the database.
   *
   * @throws \RuntimeException
   */
  public static function getHashSalt() {
    $hash_salt = self::$instance->get('hash_salt');
    // This should never happen, as it breaks user logins and many other
    // services. Therefore, explicitly notify the user (developer) by throwing
    // an exception.
    if (empty($hash_salt)) {
      throw new \RuntimeException('Missing $settings[\'hash_salt\'] in settings.php.');
    }

    return $hash_salt;
  }

}
