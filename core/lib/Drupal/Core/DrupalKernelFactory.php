<?php

/**
 * @file
 * Definition of Drupal\Core\DrupalKernelFactory.
 *
 * Settings is based on path but only one can ever exist. Kernel is a
 * representation of the current running application and settings is tightly
 * connected to that so if Settings can only have one instance, Kernel seems to
 * be in the same situation.
 *
 * Settings also encapsulates the concept that including multiple settings.php
 * files could be catastrophic but only partially because its enforced by the
 * old bootstrap and not clearly documented.
 *
 * Error handler is tied to Drupal::config(). While this makes sense, it also means
 * if the kernel is in charge of registering this we can only have one.
 *
 * Global Drupal elements get reset to what ever kernel we've booted up replacing
 * any old kernel global data.
 */

namespace Drupal\Core;

use Drupal\Component\Utility\Settings;
use Drupal\Component\Utility\Timer;
use Drupal\Component\Utility\Unicode;
use Symfony\Component\HttpFoundation\Request;

/**
 * The DrupalKernel class is the core of Drupal itself.
 *
 * This class is responsible for building the Dependency Injection Container and
 * also deals with the registration of service providers. It allows registered
 * service providers to add their services to the container. Core provides the
 * CoreServiceProvider, which, in addition to registering any core services that
 * cannot be registered in the core.services.yaml file, adds any compiler passes
 * needed by core, e.g. for processing tagged services. Each module can add its
 * own service provider, i.e. a class implementing
 * Drupal\Core\DependencyInjection\ServiceProvider, to register services to the
 * container, or modify existing services.
 */
class DrupalKernelFactory {

  /**
   * Bootstrap phase: Initialize PHP environment.
   */
  const BOOTSTRAP_ENVIRONMENT = 1;

  /**
   * Bootstrap phase: Initialize site and settings.
   */
  const BOOTSTRAP_CONFIGURATION = 2;

  /**
   * Bootstrap phase: Try to serve a cached page.
   */
  const BOOTSTRAP_PAGE_CACHE = 3;

  /**
   * Bootstrap phase: Load legacy subsystems.
   */
  const BOOTSTRAP_CODE = 4;

  /**
   * Current boot level.
   *
   * @var int
   */
  protected static $bootLevel = 0;

  /**
   * Environment initialization status.
   *
   * @var bool
   */
  protected static $isEnvironmentInitialized = FALSE;

  /**
   * Whether the environment has been initialized for the request.
   *
   * @todo Refactor/remove initializeRequest().
   *
   * @var bool
   */
  protected static $isRequestInitialized = FALSE;

  /**
   * The current request path.
   *
   * @var string
   */
  protected static $requestPath;

  /**
   * The current request path.
   *
   * @var string
   */
  protected static $currentPath = '';

  /**
   * The conf path for the given request.
   *
   * @var string
   */
  protected static $confPath;

  /**
   * Bootstraps code from include and module files.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $environment
   *   (optional) The environment to bootstrap. Defaults to 'prod'.
   * @param bool $allow_dumping
   *   (optional) Allow dumping the container. Defaults to TRUE.
   *
   * @return \Drupal\Core\DrupalKernelInterface
   *   The bootstrapped kernel.
   */
  public static function get(Request $request, $environment = 'prod', $allow_dumping = TRUE) {
    if (!static::$isEnvironmentInitialized) {
      static::boot($request);
    }

    require_once DRUPAL_ROOT . '/core/includes/common.inc';
    require_once DRUPAL_ROOT . '/core/includes/database.inc';

    // @todo DRUPAL_TEST_IN_CHILD_SITE must not be passed here.
    //   DrupalKernelFactory::bootConfiguration() negotiates a test request via
    //   drupal_valid_test_ua() already. This parameter here only exists for the
    //   http[s].php test front-controllers.
    return new DrupalKernel($environment, drupal_classloader(), $allow_dumping, DRUPAL_TEST_IN_CHILD_SITE);
  }

  /**
   * Bootstrap things.
   *
   * Public access to this is provided for backwards support only.
   *
   * @param Request $request
   * @param null $phase
   */
  public static function boot(Request $request, $phase = NULL) {

    $phase = isset($phase) ? $phase : static::BOOTSTRAP_CONFIGURATION;
    $request = Request::createFromGlobals();

    /** @var DrupalKernel $kernel */
    $kernel = null;

    for ($current_phase = static::$bootLevel + 1; $current_phase <= $phase; $current_phase++) {

      switch ($current_phase) {
        case static::BOOTSTRAP_ENVIRONMENT:
          static::bootEnvironment($request);
          break;

        case static::BOOTSTRAP_CONFIGURATION:
          static::bootConfiguration($request);
          break;

        case static::BOOTSTRAP_PAGE_CACHE:
          require_once DRUPAL_ROOT . '/core/includes/common.inc';
          require_once DRUPAL_ROOT . '/core/includes/database.inc';

          $kernel = new DrupalKernel('prod', drupal_classloader(), TRUE, DRUPAL_TEST_IN_CHILD_SITE);
          $kernel->handlePageCache($request);
          break;

        case static::BOOTSTRAP_CODE:
          $kernel->preHandle($request);
          break;
      }
    }
    static::$bootLevel = $phase;
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
   *   (optional) The current request. If not passed, defaults to request as
   *   stored in the container at \Drupal::request().
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
  public static function confPath(Request $request = NULL, $require_settings = TRUE, $reset = FALSE) {
    if (isset(static::$confPath) && !$reset) {
      return static::$confPath;
    }

    if (!isset($request)) {
      // @todo This case cannot be possible. Remove?
      if (\Drupal::hasRequest()) {
        $request = \Drupal::request();
      }
      // @todo Remove once external CLI scripts (Drush) are updated.
      else {
        $request = Request::createFromGlobals();
      }
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
   * Bootstraps settings.php and the Settings singleton.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public static function initializeSettings(Request $request) {
    // Export these settings.php variables to the global namespace.
    global $base_url, $databases, $cookie_domain, $config_directories, $config;
    $settings = array();
    $config = array();

    // Make conf_path() available as local variable in settings.php.
    $conf_path = static::confPath($request);
    if (is_readable(DRUPAL_ROOT . '/' . $conf_path . '/settings.php')) {
      require DRUPAL_ROOT . '/' . $conf_path . '/settings.php';
    }
    // Initialize Settings.
    new Settings($settings);
  }

  /**
   * Bootstraps the legacy global request variables.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @todo D8: Eliminate this entirely in favor of Request object.
   */
  public static function initializeRequest(Request $request) {
    // Provided by settings.php.
    global $base_url, $cookie_domain;
    // Set and derived from $base_url by this function.
    global $base_path, $base_root, $script_path;
    global $base_secure_url, $base_insecure_url;

    $is_https = $request->isSecure();

    if (isset($base_url)) {
      // Parse fixed base URL from settings.php.
      $parts = parse_url($base_url);
      if (!isset($parts['path'])) {
        $parts['path'] = '';
      }
      $base_path = $parts['path'] . '/';
      // Build $base_root (everything until first slash after "scheme://").
      $base_root = substr($base_url, 0, strlen($base_url) - strlen($parts['path']));
    }
    else {
      // Create base URL.
      $http_protocol = $is_https ? 'https' : 'http';
      $base_root = $http_protocol . '://' . $request->server->get('HTTP_HOST');

      $base_url = $base_root;

      // For a request URI of '/index.php/foo', $_SERVER['SCRIPT_NAME'] is
      // '/index.php', whereas $_SERVER['PHP_SELF'] is '/index.php/foo'.
      if ($dir = rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/')) {
        // Remove "core" directory if present, allowing install.php, update.php,
        // and others to auto-detect a base path.
        $core_position = strrpos($dir, '/core');
        if ($core_position !== FALSE && strlen($dir) - 5 == $core_position) {
          $base_path = substr($dir, 0, $core_position);
        }
        else {
          $base_path = $dir;
        }
        $base_url .= $base_path;
        $base_path .= '/';
      }
      else {
        $base_path = '/';
      }
    }
    $base_secure_url = str_replace('http://', 'https://', $base_url);
    $base_insecure_url = str_replace('https://', 'http://', $base_url);

    // Determine the path of the script relative to the base path, and add a
    // trailing slash. This is needed for creating URLs to Drupal pages.
    if (!isset($script_path)) {
      $script_path = '';
      // We don't expect scripts outside of the base path, but sanity check
      // anyway.
      if (strpos($request->server->get('SCRIPT_NAME'), $base_path) === 0) {
        $script_path = substr($request->server->get('SCRIPT_NAME'), strlen($base_path)) . '/';
        // If the request URI does not contain the script name, then clean URLs
        // are in effect and the script path can be similarly dropped from URL
        // generation. For servers that don't provide $_SERVER['REQUEST_URI'],
        // we do not know the actual URI requested by the client, and
        // request_uri() returns a URI with the script name, resulting in
        // non-clean URLs unless
        // there's other code that intervenes.
        if (strpos(request_uri(TRUE) . '/', $base_path . $script_path) !== 0) {
          $script_path = '';
        }
        // @todo Temporary BC for install.php, update.php, and other scripts.
        //   - http://drupal.org/node/1547184
        //   - http://drupal.org/node/1546082
        if ($script_path !== 'index.php/') {
          $script_path = '';
        }
      }
    }

    if ($cookie_domain) {
      // If the user specifies the cookie domain, also use it for session name.
      $session_name = $cookie_domain;
    }
    else {
      // Otherwise use $base_url as session name, without the protocol
      // to use the same session identifiers across HTTP and HTTPS.
      list(, $session_name) = explode('://', $base_url, 2);
      // HTTP_HOST can be modified by a visitor, but has been sanitized already
      // in DrupalKernelFactory::bootEnvironment().
      if ($cookie_domain = $request->server->get('HTTP_HOST')) {
        // Strip leading periods, www., and port numbers from cookie domain.
        $cookie_domain = ltrim($cookie_domain, '.');
        if (strpos($cookie_domain, 'www.') === 0) {
          $cookie_domain = substr($cookie_domain, 4);
        }
        $cookie_domain = explode(':', $cookie_domain);
        $cookie_domain = '.' . $cookie_domain[0];
      }
    }
    // Per RFC 2109, cookie domains must contain at least one dot other than the
    // first. For hosts such as 'localhost' or IP Addresses we don't set a
    // cookie domain.
    if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
      ini_set('session.cookie_domain', $cookie_domain);
    }
    // To prevent session cookies from being hijacked, a user can configure the
    // SSL version of their website to only transfer session cookies via SSL by
    // using PHP's session.cookie_secure setting. The browser will then use two
    // separate session cookies for the HTTPS and HTTP versions of the site. So
    // we must use different session identifiers for HTTPS and HTTP to prevent a
    // cookie collision.
    if ($is_https) {
      ini_set('session.cookie_secure', TRUE);
    }
    $prefix = ini_get('session.cookie_secure') ? 'SSESS' : 'SESS';
    session_name($prefix . substr(hash('sha256', $session_name), 0, 32));
  }

  /**
   * Returns the current boot level of the kernel.
   *
   * @return int
   *   The current boot level.
   *
   * @see \Drupal\Core\DrupalKernelFactory::$bootLevel
   */
  public static function getBootLevel() {
    return static::$bootLevel;
  }

  /**
   * Sets the current boot level of the kernel.
   *
   * Internal use only.
   *
   * @param int $level
   *   The boot level to set.
   *
   * @internal
   */
  public static function setBootLevel($level) {
    static::$bootLevel = $level;
  }

  /**
   * Returns whether a given boot level has been reached.
   *
   * @param int $boot_level
   *   The boot level to check.
   *
   * @return bool
   *   Whether the given $boot_level has been reached.
   */
  public static function isBootLevelReached($boot_level) {
    return static::$bootLevel >= $boot_level;
  }

  /**
   *
   * @param Request $request
   */
  protected static function bootEnvironment(Request $request) {
    // @todo Remove this legacy/BC construct.
    static::currentPath(static::requestPath($request));

    // Enforce E_STRICT, but allow users to set levels not part of E_STRICT.
    error_reporting(E_STRICT | E_ALL);

    // Override PHP settings required for Drupal to work properly.
    // sites/default/default.settings.php contains more runtime settings.
    // The .htaccess file contains settings that cannot be changed at runtime.

    // Use session cookies, not transparent sessions that puts the session id in
    // the query string.
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    // Don't send HTTP headers using PHP's session handler.
    // Send an empty string to disable the cache limiter.
    ini_set('session.cache_limiter', '');
    // Use httponly session cookies.
    ini_set('session.cookie_httponly', '1');

    // Set sane locale settings, to ensure consistent string, dates, times and
    // numbers handling.
    setlocale(LC_ALL, 'C');

    // Indicate that code is operating in a test child site.
    if ($test_prefix = drupal_valid_test_ua()) {
      // Only code that interfaces directly with tests should rely on this
      // constant; e.g., the error/exception handler conditionally adds further
      // error information into HTTP response headers that are consumed by
      // Simpletest's internal browser.
      define('DRUPAL_TEST_IN_CHILD_SITE', TRUE);

      // Log fatal errors to the test site directory.
      ini_set('log_errors', 1);
      ini_set('error_log', DRUPAL_ROOT . '/sites/simpletest/' . substr($test_prefix, 10) . '/error.log');
    }
    else {
      // Ensure that no other code defines this.
      define('DRUPAL_TEST_IN_CHILD_SITE', FALSE);
    }

    // Detect string handling method.
    Unicode::check();
  }

  /**
   * Bootstraps configuration.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  protected static function bootConfiguration(Request $request) {
    if (static::$bootLevel >= self::BOOTSTRAP_CONFIGURATION) {
      return;
    }

    // Initialize the configuration, including variables from settings.php.
    static::initializeSettings($request);

    // @todo Refactor initializeRequest() to remove this condition. Note:
    //   All of the globals are legacy and obsolete, but the cookie domain and
    //   session name setup depends on settings.php and can only be run once.
    if (!static::$isRequestInitialized) {
      static::$isRequestInitialized = TRUE;
      static::initializeRequest($request);

      // Start a page timer:
      Timer::start('page');

      // Set the Drupal custom error handler.
      // @todo Move into bootKernel() or remove entirely.
      set_error_handler('_drupal_error_handler');
      set_exception_handler('_drupal_exception_handler');
    }

    // Redirect the user to the installation script if Drupal has not been
    // installed yet (i.e., if no $databases array has been defined in the
    // settings.php file) and we are not already installing.
    if (empty($GLOBALS['databases']) && !drupal_installation_attempted() && !drupal_is_cli()) {
      include_once DRUPAL_ROOT . '/core/includes/install.inc';
      install_goto('core/install.php');
    }

    static::$bootLevel = self::BOOTSTRAP_CONFIGURATION;
  }

  /**
   * Returns the requested URL path of the page being viewed.
   *
   * @param \Symfony\Component\HttpFoundation\Request|NULL $request
   *   (Optional) The request to derive the request path from. Defaults to NULL
   *
   * Examples:
   * - http://example.com/node/306 returns "node/306".
   * - http://example.com/drupalfolder/node/306 returns "node/306" while
   *   base_path() returns "/drupalfolder/".
   * - http://example.com/path/alias (which is a path alias for node/306)
   *   returns "path/alias" as opposed to the internal path.
   * - http://example.com/index.php returns an empty string (meaning: front
   *   page).
   * - http://example.com/index.php?page=1 returns an empty string.
   *
   * @return string
   *   The requested Drupal URL path.
   */
  public static function requestPath(Request $request = NULL) {
    if (isset(static::$requestPath)) {
      return static::$requestPath;
    }
    if (!$request) {
      // @todo Do we even need this?
      $request = \Drupal::request();
    }

    // Get the part of the URI between the base path of the Drupal installation
    // and the query string, and unescape it.
    $request_path = request_uri(TRUE);
    $base_path_len = strlen(rtrim(dirname($request->server->get('SCRIPT_NAME')), '\/'));
    static::$requestPath = substr(urldecode($request_path), $base_path_len + 1);

    // Depending on server configuration, the URI might or might not include the
    // script name. For example, the front page might be accessed as
    // http://example.com or as http://example.com/index.php, and the "user"
    // page might be accessed as http://example.com/user or as
    // http://example.com/index.php/user. Strip the script name from $path.
    $script = basename($request->server->get('SCRIPT_NAME'));
    if (static::$requestPath == $script) {
      static::$requestPath = '';
    }
    elseif (strpos(static::$requestPath, $script . '/') === 0) {
      static::$requestPath = substr(static::$requestPath, strlen($script) + 1);
    }

    // Extra slashes can appear in URLs or under some conditions, added by
    // Apache so normalize.
    static::$requestPath = trim(static::$requestPath, '/');

    return static::$requestPath;
  }

  /**
   * Sets or returns the current request path.
   *
   * @param string $path
   *   (optional) Path to set as the current path. If NULL, returns the current
   *   path. Defaults to NULL.
   *
   * @return string
   *   The current path.
   */
  public static function currentPath($path = NULL) {
    if (isset($path)) {
      self::$currentPath = $path;
    }
    return self::$currentPath;
  }

}
