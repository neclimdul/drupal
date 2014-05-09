<?php

/**
 * @file
 * Definition of Drupal\Core\DrupalKernel.
 */

namespace Drupal\Core;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Timer;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\DependencyInjection\YamlFileLoader;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Language\Language;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\TerminableInterface;
use Composer\Autoload\ClassLoader;

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
class DrupalKernel implements DrupalKernelInterface, TerminableInterface {

  const CONTAINER_BASE_CLASS = '\Drupal\Core\DependencyInjection\Container';

  /**
   * Holds the container instance.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The environment, e.g. 'testing', 'install'.
   *
   * @var string
   */
  protected $environment;

  /**
   * Whether the kernel has been booted.
   *
   * @var bool
   */
  protected $booted = FALSE;

  /**
   * Holds the list of enabled modules.
   *
   * @var array
   *   An associative array whose keys are module names and whose values are
   *   ignored.
   */
  protected $moduleList;

  /**
   * Holds an updated list of enabled modules.
   *
   * @var array
   *   An associative array whose keys are module names and whose values are
   *   ignored.
   */
  protected $newModuleList;

  /**
   * List of available modules and installation profiles.
   *
   * @var \Drupal\Core\Extension\Extension[]
   */
  protected $moduleData = array();

  /**
   * PHP code storage object to use for the compiled container.
   *
   * @var \Drupal\Component\PhpStorage\PhpStorageInterface
   */
  protected $storage;

  /**
   * The classloader object.
   *
   * @var \Composer\Autoload\ClassLoader
   */
  protected $classLoader;

  /**
   * Config storage object used for reading enabled modules configuration.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The list of the classnames of the service providers in this kernel.
   *
   * @var array
   */
  protected $serviceProviderClasses;

  /**
   * Whether the container can be dumped.
   *
   * @var bool
   */
  protected $allowDumping;

  /**
   * Whether the container needs to be dumped once booting is complete.
   *
   * @var bool
   */
  protected $containerNeedsDumping;

  /**
   * Holds the list of YAML files containing service definitions.
   *
   * @var array
   */
  protected $serviceYamls;

  /**
   * The array of registered service providers.
   *
   * @var array
   */
  protected $serviceProviders;

  /**
   * Whether the request globals have been initialized.
   *
   * @var bool
   */
  protected static $isRequestInitialized = FALSE;

  /**
   * Whether the PHP environment has been initialized.
   *
   * This legacy phase can only be booted once because it sets session INI
   * settings. If a session has already been started, re-generating these
   * settings would break the session.
   *
   * @var bool
   */
  protected static $isEnvironmentInitialized = FALSE;

  /**
   * Whether this instance is for testing purposes only.
   *
   * @var bool
   */
  protected $testOnly = FALSE;

  /**
   * Constructs a DrupalKernel object.
   *
   * @param string $environment
   *   String indicating the environment, e.g. 'prod' or 'dev'.
   * @param \Composer\Autoload\ClassLoader $class_loader
   *   (optional) The classloader is only used if $storage is not given or
   *   the load from storage fails and a container rebuild is required. In
   *   this case, the loaded modules will be registered with this loader in
   *   order to be able to find the module serviceProviders.
   * @param bool $allow_dumping
   *   (optional) FALSE to stop the container from being written to or read
   *   from disk. Defaults to TRUE.
   * @param bool $test_only
   *   (optional) Whether the kernel is built for testing purposes only.
   *   Defaults to FALSE.
   */
  public function __construct($environment, ClassLoader $class_loader, $allow_dumping = TRUE, $test_only = FALSE) {
    $this->environment = $environment;
    $this->allowDumping = $allow_dumping;
    $this->testOnly = $test_only;
    $this->classLoader = $class_loader;
  }

  /**
   * {@inheritdoc}
   */
  public function boot(Request $request) {
    if ($this->booted) {
      return;
    }

    // Start a page timer:
    Timer::start('page');

    // Include our bootstrap file.
    require_once dirname(dirname(dirname(__DIR__))) . '/includes/bootstrap.inc';

    // Exit if we should be in a test environment but aren't.
    if ($this->testOnly && !drupal_valid_test_ua()) {
      header($request->server->get('SERVER_PROTOCOL') . ' 403 Forbidden');
      exit;
    }

    static::bootEnvironment();

    // Get our most basic settings setup.
    Settings::initialize($request);

    drupal_classloader($this->classLoader);

    // Initialize Request globals.
    static::initializeRequestGlobals($request);

    // Redirect the user to the installation script if Drupal has not been
    // installed yet (i.e., if no $databases array has been defined in the
    // settings.php file) and we are not already installing.
    if (!Database::getConnectionInfo() && !drupal_installation_attempted() && !drupal_is_cli()) {
      include_once DRUPAL_ROOT . '/core/includes/install.inc';
      install_goto('core/install.php');
    }

    $this->booted = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function shutdown() {
    if (FALSE === $this->booted) {
      return;
    }
    $this->booted = FALSE;
    $this->container = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContainer() {
    // Container should probably always be built during boot() but because of
    // the way Settings are loaded we need to delay it to facilitate
    // modification for testing. globals --
    if (!isset($this->container)) {
      $this->initializeContainer();
    }
    if ($this->containerNeedsDumping && !$this->dumpDrupalContainer($this->container, static::CONTAINER_BASE_CLASS)) {
      watchdog('DrupalKernel', 'Container cannot be written to disk');
    }
    return $this->container;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Invoke proper request/response/terminate events.
   */
  public function handlePageCache(Request $request) {
    $this->boot($request);

    // Check for a cache mode force from settings.php.
    if (Settings::get('page_cache_without_database')) {
      $cache_enabled = TRUE;
    }
    else {
      $config = $this->getContainer()->get('config.factory')->get('system.performance');
      $cache_enabled = $config->get('cache.page.use_internal');
    }

    // If there is no session cookie and cache is enabled (or forced), try to
    // serve a cached page.
    if (!$request->cookies->has(session_name()) && $cache_enabled && drupal_page_is_cacheable()) {
      // Get the page from the cache.
      $response = drupal_page_get_cache($request);
      // If there is a cached page, display it.
      if ($response) {
        $response->headers->set('X-Drupal-Cache', 'HIT');

        drupal_serve_page_from_cache($response, $request);

        // We are done.
        $response->prepare($request);
        $response->send();
        exit;
      }
    }
    return $this;
  }

  /**
   * Finishes booting by loading remaining includes and enabled modules.
   *
   * @return $this
   */
  public function bootCode(Request $request) {
    require_once DRUPAL_ROOT . '/core/includes/common.inc';
    require_once DRUPAL_ROOT . '/core/includes/database.inc';
    require_once DRUPAL_ROOT . '/' . Settings::get('path_inc', 'core/includes/path.inc');
    require_once DRUPAL_ROOT . '/core/includes/module.inc';
    require_once DRUPAL_ROOT . '/core/includes/theme.inc';
    require_once DRUPAL_ROOT . '/core/includes/pager.inc';
    require_once DRUPAL_ROOT . '/' . Settings::get('menu_inc', 'core/includes/menu.inc');
    require_once DRUPAL_ROOT . '/core/includes/tablesort.inc';
    require_once DRUPAL_ROOT . '/core/includes/file.inc';
    require_once DRUPAL_ROOT . '/core/includes/unicode.inc';
    require_once DRUPAL_ROOT . '/core/includes/form.inc';
    require_once DRUPAL_ROOT . '/core/includes/mail.inc';
    require_once DRUPAL_ROOT . '/core/includes/ajax.inc';
    require_once DRUPAL_ROOT . '/core/includes/errors.inc';
    require_once DRUPAL_ROOT . '/core/includes/schema.inc';
    require_once DRUPAL_ROOT . '/core/includes/entity.inc';

    // Ensure container is loaded.
    $this->getContainer();

    // Load all enabled modules.
    $this->container->get('module_handler')->loadAll();

    // Ensure container has a request scope so we can load file stream wrappers.
    if (!$this->container->isScopeActive('request')) {
      // Enter the request scope so that current_user service is available for
      // locale/translation sake.
      $this->container->enterScope('request');
      $this->container->set('request', $request);
      $this->container->get('request_stack')->push($request);
    }

    // Make sure all stream wrappers are registered.
    file_get_stream_wrappers();

    // Ensure mt_rand() is reseeded to prevent random values from one page load
    // being exploited to predict random values in subsequent page loads.
    $seed = unpack("L", Crypt::randomBytes(4));
    mt_srand($seed[1]);

    // Set the allowed protocols once we have the config available.
    $allowed_protocols = $this->container->get('config.factory')->get('system.filter')->get('protocols');
    if (!isset($allowed_protocols)) {
      // \Drupal\Component\Utility\UrlHelper::filterBadProtocol() is called by
      // the installer and update.php, in which case the configuration may not
      // exist (yet). Provide a minimal default set of allowed protocols for
      // these cases.
      $allowed_protocols = array('http', 'https');
    }
    UrlHelper::setAllowedProtocols($allowed_protocols);

    // Back out scope required to initialize the file stream wrappers.
    if ($this->container->isScopeActive('request')) {
      $this->container->leaveScope('request');
    }
    return $this;
  }

  /**
   * Returns an array of available bundles.
   *
   * @return array
   *   The available bundles.
   */
  public function discoverServiceProviders() {
    $serviceProviders = array(
      'CoreServiceProvider' => new CoreServiceProvider(),
    );
    $this->serviceYamls = array(
      'core/core.services.yml'
    );
    $this->serviceProviderClasses = array('Drupal\Core\CoreServiceProvider');

    // Ensure we know what modules are enabled and that their namespaces are
    // registered.
    if (!isset($this->moduleList)) {
      $extensions = $this->getConfigStorage()->read('core.extension');
      $this->moduleList = isset($extensions['module']) ? $extensions['module'] : array();
    }
    $module_filenames = $this->getModuleFileNames();
    $this->registerNamespacesPsr4($this->getModuleNamespacesPsr4($module_filenames));

    // Load each module's serviceProvider class.
    foreach ($this->moduleList as $module => $weight) {
      $camelized = ContainerBuilder::camelize($module);
      $name = "{$camelized}ServiceProvider";
      $class = "Drupal\\{$module}\\{$name}";
      if (class_exists($class)) {
        $serviceProviders[$name] = new $class();
        $this->serviceProviderClasses[] = $class;
      }
      $filename = dirname($module_filenames[$module]) . "/$module.services.yml";
      if (file_exists($filename)) {
        $this->serviceYamls[] = $filename;
      }
    }

    // Add site specific or test service providers.
    if (!empty($GLOBALS['conf']['container_service_providers'])) {
      foreach ($GLOBALS['conf']['container_service_providers'] as $name => $class) {
        $serviceProviders[$name] = new $class();
        $this->serviceProviderClasses[] = $class;
      }
    }
    // Add site specific or test YAMLs.
    if (!empty($GLOBALS['conf']['container_yamls'])) {
      $this->serviceYamls = array_merge($this->serviceYamls, $GLOBALS['conf']['container_yamls']);
    }
    return $serviceProviders;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceProviders() {
    return $this->serviceProviders;
  }

  /**
   * {@inheritdoc}
   */
  public function terminate(Request $request, Response $response) {
    if (FALSE === $this->booted) {
      return;
    }

    if ($this->getHttpKernel() instanceof TerminableInterface) {
      $this->getHttpKernel()->terminate($request, $response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $this->boot($request);
    $this->bootCode($request);
    return $this->getHttpKernel()->handle($request, $type, $catch);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareLegacyRequest(Request $request) {
    $this->boot($request);
    $this->bootCode($request);
    // Normally this is handled in the HttpKernel object but this method exists
    // for pages that don't run through a http kernel.
    $this->container->enterScope('request');
    $this->container->set('request', $request);
    $this->container->get('request_stack')->push($request);
    return $this;
  }

  /**
   * Returns module data on the filesystem.
   *
   * @param $module
   *   The name of the module.
   *
   * @return \Drupal\Core\Extension\Extension|bool
   *   Returns an Extension object if the module is found, FALSE otherwise.
   */
  protected function moduleData($module) {
    if (!$this->moduleData) {
      // First, find profiles.
      $listing = new ExtensionDiscovery();
      $listing->setProfileDirectories(array());
      $all_profiles = $listing->scan('profile');
      $profiles = array_intersect_key($all_profiles, $this->moduleList);

      // If a module is within a profile directory but specifies another
      // profile for testing, it needs to be found in the parent profile.
      $settings = $this->getConfigStorage()->read('simpletest.settings');
      $parent_profile = !empty($settings['parent_profile']) ? $settings['parent_profile'] : NULL;
      if ($parent_profile && !isset($profiles[$parent_profile])) {
        // In case both profile directories contain the same extension, the
        // actual profile always has precedence.
        $profiles = array($parent_profile => $all_profiles[$parent_profile]) + $profiles;
      }

      $profile_directories = array_map(function ($profile) {
        return $profile->getPath();
      }, $profiles);
      $listing->setProfileDirectories($profile_directories);

      // Now find modules.
      $this->moduleData = $profiles + $listing->scan('module');
    }
    return isset($this->moduleData[$module]) ? $this->moduleData[$module] : FALSE;
  }

  /**
   * Implements Drupal\Core\DrupalKernelInterface::updateModules().
   *
   * @todo Remove obsolete $module_list parameter. Only $module_filenames is
   *   needed.
   */
  public function updateModules(array $module_list, array $module_filenames = array()) {
    $this->newModuleList = $module_list;
    foreach ($module_filenames as $name => $extension) {
      $this->moduleData[$name] = $extension;
    }

    $this->initializeContainer();
  }

  /**
   * Returns the classname based on environment.
   *
   * @return string
   *   The class name.
   */
  protected function getClassName() {
    $parts = array('service_container', $this->environment);
    return implode('_', $parts);
  }


  /**
   * Returns the kernel parameters.
   *
   * @return array An array of kernel parameters
   */
  protected function getKernelParameters() {
    return array(
      'kernel.environment' => $this->environment,
    );
  }

  /**
   * Initializes the service container.
   */
  protected function initializeContainer() {
    $this->containerNeedsDumping = FALSE;
    $persist = $this->getServicesToPersist();
    // The request service requires custom persisting logic, since it is also
    // potentially scoped.
    $request_scope = FALSE;
    if (isset($this->container)) {
      if ($this->container->isScopeActive('request')) {
        $request_scope = TRUE;
      }
      if ($this->container->initialized('request')) {
        $request = $this->container->get('request');
      }
    }
    $this->container = NULL;
    $class = $this->getClassName();
    $cache_file = $class . '.php';

    if ($this->allowDumping) {
      // First, try to load.
      if (!class_exists($class, FALSE)) {
        $this->storage()->load($cache_file);
      }
      // If the load succeeded or the class already existed, use it.
      if (class_exists($class, FALSE)) {
        $fully_qualified_class_name = '\\' . $class;
        $this->container = new $fully_qualified_class_name;
        $this->persistServices($persist);
      }
    }
    // First check whether the list of modules changed in this request.
    if (isset($this->newModuleList)) {
      if (isset($this->container) && isset($this->moduleList) && array_keys($this->moduleList) !== array_keys($this->newModuleList)) {
        unset($this->container);
      }
      $this->moduleList = $this->newModuleList;
      unset($this->newModuleList);
    }
    // Second, check if some other request -- for example on another web
    // frontend or during the installer -- changed the list of enabled modules.
    if (isset($this->container)) {
      // All namespaces must be registered before we attempt to use any service
      // from the container.
      $container_modules = $this->container->getParameter('container.modules');
      $namespaces_before = $this->classLoader->getPrefixesPsr4();
      $this->registerNamespacesPsr4($this->container->getParameter('container.namespaces'));

      // If 'container.modules' is wrong, the container must be rebuilt.
      if (!isset($this->moduleList)) {
        $this->moduleList = $this->container->get('config.factory')->get('core.extension')->get('module') ?: array();
      }
      if (array_keys($this->moduleList) !== array_keys($container_modules)) {
        $persist = $this->getServicesToPersist();
        unset($this->container);
        // Revert the class loader to its prior state. However,
        // registerNamespaces() performs a merge rather than replace, so to
        // effectively remove erroneous registrations, we must replace them with
        // empty arrays.
        $namespaces_after = $this->classLoader->getPrefixesPsr4();
        $namespaces_before += array_fill_keys(array_diff(array_keys($namespaces_after), array_keys($namespaces_before)), array());
        $this->registerNamespacesPsr4($namespaces_before);
      }
    }

    if (!isset($this->container)) {
      $this->container = $this->buildContainer();
      $this->persistServices($persist);

      // The namespaces are marked as persistent, so objects like the annotated
      // class discovery still has the right object. We may have updated the
      // list of modules, so set it.
      if ($this->container->initialized('container.namespaces')) {
        $this->container->get('container.namespaces')->exchangeArray($this->container->getParameter('container.namespaces'));
      }

      if ($this->allowDumping) {
        $this->containerNeedsDumping = TRUE;
      }
    }

    $this->container->set('kernel', $this);

    // Set the class loader which was registered as a synthetic service.
    $this->container->set('class_loader', $this->classLoader);
    // If we have a request set it back to the new container.
    if ($request_scope) {
      $this->container->enterScope('request');
    }
    if (isset($request)) {
      $this->container->set('request', $request);
    }
    \Drupal::setContainer($this->container);
  }

  /**
   * Setup a consistent PHP environment.
   *
   * This method sets PHP environment options we want to be sure are set
   * correctly for security or just saneness.
   */
  public static function bootEnvironment() {
    if (static::$isEnvironmentInitialized) {
      return;
    }

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

    // Detect string handling method.
    Unicode::check();

    // Indicate that code is operating in a test child site.
    if (!defined('DRUPAL_TEST_IN_CHILD_SITE')) {
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
    }

    // Set the Drupal custom error handler.
    set_error_handler('_drupal_error_handler');
    set_exception_handler('_drupal_exception_handler');

    static::$isEnvironmentInitialized = TRUE;
  }

  /**
   * Bootstraps the legacy global request variables.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @todo D8: Eliminate this entirely in favor of Request object.
   */
  public static function initializeRequestGlobals(Request $request) {
    // If we do this more then once per page request things go weird.
    // $globals--
    if (static::$isRequestInitialized) {
      return;
    }

    // Provided by settings.php.
    global $base_url, $cookie_domain;
    // Set and derived from $base_url by this function.
    global $base_path, $base_root, $script_path;
    global $base_secure_url, $base_insecure_url;

    $is_https = $request->isSecure();

    // @todo Refactor with the Symfony Request object.
    _current_path(request_path());

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
      // in DrupalKernel::bootEnvironment().
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
    static::$isRequestInitialized = TRUE;
  }

  /**
   * Returns service instances to persist from an old container to a new one.
   */
  protected function getServicesToPersist() {
    $persist = array();
    if (isset($this->container)) {
      foreach ($this->container->getParameter('persistIds') as $id) {
        // It's pointless to persist services not yet initialized.
        if ($this->container->initialized($id)) {
          $persist[$id] = $this->container->get($id);
        }
      }
    }
    return $persist;
  }

  /**
   * Moves persistent service instances into a new container.
   */
  protected function persistServices(array $persist) {
    foreach ($persist as $id => $object) {
      // Do not override services already set() on the new container, for
      // example 'service_container'.
      if (!$this->container->initialized($id)) {
        $this->container->set($id, $object);
      }
    }
  }

  /**
   * Builds the service container.
   *
   * @return ContainerBuilder The compiled service container
   */
  protected function buildContainer() {
    $this->initializeServiceProviders();
    $container = $this->getContainerBuilder();
    $container->set('kernel', $this);
    $container->setParameter('container.service_providers', $this->serviceProviderClasses);
    $container->setParameter('container.modules', $this->getModulesParameter());

    // Get a list of namespaces and put it onto the container.
    $namespaces = $this->getModuleNamespacesPsr4($this->getModuleFileNames());
    // Add all components in \Drupal\Core and \Drupal\Component that have a
    // Plugin directory.
    foreach (array('Core', 'Component') as $parent_directory) {
      $path = DRUPAL_ROOT . '/core/lib/Drupal/' . $parent_directory;
      $parent_namespace = 'Drupal\\' . $parent_directory;
      foreach (new \DirectoryIterator($path) as $component) {
        if (!$component->isDot() && $component->isDir() && is_dir($component->getPathname() . '/Plugin')) {
          $namespaces[$parent_namespace . '\\' . $component->getFilename()] = $path . '/' . $component->getFilename();
        }
      }
    }
    $container->setParameter('container.namespaces', $namespaces);

    // Store the default language values on the container. This is so that the
    // default language can be configured using the configuration factory. This
    // avoids the circular dependencies that would created by
    // \Drupal\language\LanguageServiceProvider::alter() and allows the default
    // language to not be English in the installer.
    $default_language_values = Language::$defaultValues;
    if ($system = $this->getConfigStorage()->read('system.site')) {
      if ($default_language_values['id'] != $system['langcode']) {
        $default_language_values = array('id' => $system['langcode'], 'default' => TRUE);
      }
    }
    $container->setParameter('language.default_values', $default_language_values);

    // Register synthetic services.
    $container->register('class_loader')->setSynthetic(TRUE);
    $container->register('kernel', 'Symfony\Component\HttpKernel\KernelInterface')->setSynthetic(TRUE);
    $container->register('service_container', 'Symfony\Component\DependencyInjection\ContainerInterface')->setSynthetic(TRUE);
    $yaml_loader = new YamlFileLoader($container);
    foreach ($this->serviceYamls as $filename) {
      $yaml_loader->load($filename);
    }
    foreach ($this->serviceProviders as $provider) {
      if ($provider instanceof ServiceProviderInterface) {
        $provider->register($container);
      }
    }

    // Identify all services whose instances should be persisted when rebuilding
    // the container during the lifetime of the kernel (e.g., during a kernel
    // reboot). Include synthetic services, because by definition, they cannot
    // be automatically reinstantiated. Also include services tagged to persist.
    $persist_ids = array();
    foreach ($container->getDefinitions() as $id => $definition) {
      if ($definition->isSynthetic() || $definition->getTag('persist')) {
        $persist_ids[] = $id;
      }
    }
    $container->setParameter('persistIds', $persist_ids);

    $container->compile();
    return $container;
  }

  /**
   * Registers all service providers to the kernel.
   *
   * @throws \LogicException
   */
  protected function initializeServiceProviders() {
    $this->serviceProviders = array();

    foreach ($this->discoverServiceProviders() as $name => $provider) {
      if (isset($this->serviceProviders[$name])) {
        throw new \LogicException(sprintf('Trying to register two service providers with the same name "%s"', $name));
      }
      $this->serviceProviders[$name] = $provider;
    }
  }

  /**
   * Gets a new ContainerBuilder instance used to build the service container.
   *
   * @return ContainerBuilder
   */
  protected function getContainerBuilder() {
    return new ContainerBuilder(new ParameterBag($this->getKernelParameters()));
  }

  /**
   * Dumps the service container to PHP code in the config directory.
   *
   * This method is based on the dumpContainer method in the parent class, but
   * that method is reliant on the Config component which we do not use here.
   *
   * @param ContainerBuilder $container
   *   The service container.
   * @param string $baseClass
   *   The name of the container's base class
   *
   * @return bool
   *   TRUE if the container was successfully dumped to disk.
   */
  protected function dumpDrupalContainer(ContainerBuilder $container, $baseClass) {
    if (!$this->storage()->writeable()) {
      return FALSE;
    }
    // Cache the container.
    $dumper = new PhpDumper($container);
    $class = $this->getClassName();
    $content = $dumper->dump(array('class' => $class, 'base_class' => $baseClass));
    return $this->storage()->save($class . '.php', $content);
  }


  /**
   * Gets a http kernel from the container
   *
   * @return \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected function getHttpKernel() {
    return $this->container->get('http_kernel');
  }

  /**
   * Gets the PHP code storage object to use for the compiled container.
   *
   * @return \Drupal\Component\PhpStorage\PhpStorageInterface
   */
  protected function storage() {
    if (!isset($this->storage)) {
      $this->storage = PhpStorageFactory::get('service_container');
    }
    return $this->storage;
  }

  /**
   * Returns the active configuration storage to use during building the container.
   *
   * @return \Drupal\Core\Config\StorageInterface
   */
  protected function getConfigStorage() {
    if (!isset($this->configStorage)) {
      // The active configuration storage may not exist yet; e.g., in the early
      // installer. Catch the exception thrown by config_get_config_directory().
      try {
        $this->configStorage = BootstrapConfigStorageFactory::get();
      }
      catch (\Exception $e) {
        $this->configStorage = new NullStorage();
      }
    }
    return $this->configStorage;
  }

  /**
   * Returns an array of Extension class parameters for all enabled modules.
   *
   * @return array
   */
  protected function getModulesParameter() {
    $extensions = array();
    foreach ($this->moduleList as $name => $weight) {
      if ($data = $this->moduleData($name)) {
        $extensions[$name] = array(
          'type' => $data->getType(),
          'pathname' => $data->getPathname(),
          'filename' => $data->getExtensionFilename(),
        );
      }
    }
    return $extensions;
  }

  /**
   * Gets the file name for each enabled module.
   *
   * @return array
   *   Array where each key is a module name, and each value is a path to the
   *   respective *.module or *.profile file.
   */
  protected function getModuleFileNames() {
    $filenames = array();
    foreach ($this->moduleList as $module => $weight) {
      if ($data = $this->moduleData($module)) {
        $filenames[$module] = $data->getPathname();
      }
    }
    return $filenames;
  }

  /**
   * Gets the PSR-4 base directories for module namespaces.
   *
   * @param array $module_file_names
   *   Array where each key is a module name, and each value is a path to the
   *   respective *.module or *.profile file.
   *
   * @return array
   *   Array where each key is a module namespace like 'Drupal\system', and each
   *   value is an array of PSR-4 base directories associated with the module
   *   namespace.
   */
  protected function getModuleNamespacesPsr4($module_file_names) {
    $namespaces = array();
    foreach ($module_file_names as $module => $filename) {
      // @todo Remove lib/Drupal/$module, once the switch to PSR-4 is complete.
      $namespaces["Drupal\\$module"][] = DRUPAL_ROOT . '/' . dirname($filename) . '/lib/Drupal/' . $module;
      $namespaces["Drupal\\$module"][] = DRUPAL_ROOT . '/' . dirname($filename) . '/src';
    }
    return $namespaces;
  }

  /**
   * Gets the PSR-0 base directories for module namespaces.
   *
   * @param array $module_file_names
   *   Array where each key is a module name, and each value is a path to the
   *   respective *.module or *.profile file.
   *
   * @return array
   *   Array where each key is a module namespace like 'Drupal\system', and each
   *   value is a PSR-0 base directory associated with the module namespace.
   */
  protected function getModuleNamespaces($module_file_names) {
    $namespaces = array();
    foreach ($module_file_names as $module => $filename) {
      $namespaces["Drupal\\$module"] = DRUPAL_ROOT . '/' . dirname($filename) . '/lib';
    }
    return $namespaces;
  }

  /**
   * Registers a list of namespaces with PSR-4 directories for class loading.
   *
   * @param array $namespaces
   *   Array where each key is a namespace like 'Drupal\system', and each value
   *   is either a PSR-4 base directory, or an array of PSR-4 base directories
   *   associated with this namespace.
   */
  protected function registerNamespacesPsr4(array $namespaces = array()) {
    foreach ($namespaces as $prefix => $paths) {
      $this->classLoader->addPsr4($prefix . '\\', $paths);
    }
  }

  /**
   * Registers a list of namespaces with PSR-0 directories for class loading.
   *
   * @param array $namespaces
   *   Array where each key is a namespace like 'Drupal\system', and each value
   *   is either a PSR-0 base directory, or an array of PSR-0 base directories
   *   associated with this namespace.
   */
  protected function registerNamespaces(array $namespaces = array()) {
    foreach ($namespaces as $prefix => $path) {
      $this->classLoader->add($prefix, $path);
    }
  }
}
