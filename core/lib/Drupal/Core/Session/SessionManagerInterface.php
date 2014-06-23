<?php

/**
 * @file
 * Contains \Drupal\Core\Session\SessionManagerInterface.
 */

namespace Drupal\Core\Session;

use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * Defines the session manager interface.
 */
interface SessionManagerInterface extends SessionStorageInterface {

  /**
   * Initializes the session handler, starting a session if needed.
   *
   * @return $this
   */
  public function initialize();

  /**
   * Ends a specific user's session(s).
   *
   * @param int $uid
   *   User ID.
   */
  public function delete($uid);

}
