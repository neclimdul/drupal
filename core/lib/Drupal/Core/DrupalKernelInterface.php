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
   * @return array
   *   An associative array of ServiceProvider objects, keyed by name.
   */
  public function getServiceProviders();

  /**
   * Gets the current container.
   *
   * @return ContainerInterface A ContainerInterface instance
   */
  public function getContainer();

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
   * Prepare the kernel for handling a request without handling the request.
   *
   * Because Drupal still provides so much outside of the Kernel as global state,
   * there are standalone php files even within core that want to handle the page
   * request entirely on their own but want to have access to this state. To do
   * they can create a kernel and call this method to have the Kernel populate its
   * state which will be mirrored in those global methods.
   *
   * Note: This is provided for backwards compatibility only. Many of those global
   * methods are deprecated and the ones that are not are meant to be shortcuts for
   * procedural methods, not for bypassing the kernel. Future code should extend the
   * DrupalKernel or implement its own kernel and handle the page the request in
   * that class.
   *
   * @param Request $request
   */
  public function preHandle(Request $request);
}
