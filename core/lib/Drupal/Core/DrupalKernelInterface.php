<?php

/**
 * @file
 * Definition of Drupal\Core\DrupalKernelInterface.
 */

namespace Drupal\Core;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The interface for DrupalKernel, the core of Drupal.
 *
 * This interface extends Symfony's KernelInterface and adds methods for
 * responding to modules being enabled or disabled during its lifetime.
 */
interface DrupalKernelInterface extends HttpKernelInterface {

  /**
   * Boots the current kernel.
   *
   * @return $this
   */
  public function boot();

  /**
   * Shuts down the kernel.
   */
  public function shutdown();

  /**
   * Discovers available serviceProviders.
   *
   * @return array
   *   The available serviceProviders.
   */
  public function discoverServiceProviders();

  /**
   * Returns all registered service providers.
   *
   * @param string $origin
   *   The origin for which to return service providers; one of 'app' or 'site'.
   *
   * @return array
   *   An associative array of ServiceProvider objects, keyed by name.
   */
  public function getServiceProviders($origin);

  /**
   * Gets the current container.
   *
   * @return ContainerInterface A ContainerInterface instance
   */
  public function getContainer();

  /**
   * Returns the appropriate site directory for a request.
   *
   * Once the kernel has been created DrupalKernelInterface::getSitePath() is
   * preferred since it gets the statically cached result of this method.
   *
   * Site directories contain all site specific code. This includes settings.php
   * for bootstrap level configuration, file configuration stores, public file
   * storage and site specific modules and themes.
   *
   * Finds a matching site directory file by stripping the website's hostname
   * from left to right and pathname from right to left. By default, the
   * directory must contain a 'settings.php' file for it to match. If the
   * parameter $require_settings is set to FALSE, then a directory without a
   * 'settings.php' file will match as well. The first configuration file found
   * will be used and the remaining ones will be ignored. If no configuration
   * file is found, returns a default value 'sites/default'. See
   * default.settings.php for examples on how the URL is converted to a
   * directory.
   *
   * If a file named sites.php is present in the sites directory, it will be
   * loaded prior to scanning for directories. That file can define aliases in
   * an associative array named $sites. The array is written in the format
   * '<port>.<domain>.<path>' => 'directory'. As an example, to create a
   * directory alias for http://www.drupal.org:8080/mysite/test whose
   * configuration file is in sites/example.com, the array should be defined as:
   * @code
   * $sites = array(
   *   '8080.www.drupal.org.mysite.test' => 'example.com',
   * );
   * @endcode
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param bool $require_settings
   *   Only directories with an existing settings.php file will be recognized.
   *   Defaults to TRUE. During initial installation, this is set to FALSE so
   *   that Drupal can detect a matching directory, then create a new
   *   settings.php file in it.
   *
   * @return string
   *   The path of the matching directory.
   *
   * @see \Drupal\Core\DrupalKernelInterface::getSitePath()
   * @see \Drupal\Core\DrupalKernelInterface::setSitePath()
   * @see default.settings.php
   * @see example.sites.php
   */
  public static function findSitePath(Request $request, $require_settings = TRUE);

  /**
   * Set the current site path.
   *
   * @param $path
   *   The current site path.
   */
  public function setSitePath($path);

  /**
   * Get the site path.
   *
   * @return string
   *   The current site path.
   */
  public function getSitePath();

  /**
   * Updates the kernel's list of modules to the new list.
   *
   * The kernel needs to update its bundle list and container to match the new
   * list.
   *
   * @param array $module_list
   *   The new list of modules.
   * @param array $module_filenames
   *   List of module filenames, keyed by module name.
   */
  public function updateModules(array $module_list, array $module_filenames = array());

  /**
   * Attempts to serve a page from the cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return $this
   */
  public function handlePageCache(Request $request);

  /**
   * Prepare the kernel for handling a request without handling the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return $this
   *
   * @deprecated 8.x
   *   Only used by legacy front-controller scripts.
   */
  public function prepareLegacyRequest(Request $request);

}
