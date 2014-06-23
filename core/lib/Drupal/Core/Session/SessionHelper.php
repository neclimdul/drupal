<?php
/**
 * @file
 * @todo fill in file doc.
 */

namespace Drupal\Core\Session;


use Drupal\Core\Site\Settings;

class SessionHelper {

  /**
   * Whether session management is enabled or temporarily disabled.
   *
   * PHP session ID, session, and cookie handling happens in the global scope.
   * This value has to persist, since a potentially wrong or disallowed session
   * would be written otherwise.
   *
   * @var bool
   */
  protected $enabled = TRUE;

  /**
   * Whether or not the session manager is operating in mixed mode SSL.
   *
   * @var bool
   */
  protected $mixedMode;

  public function __construct(Settings $settings) {
    $this->mixedMode = $settings->get('mixed_mode_sessions', FALSE);
  }

  /**
   * Determines whether to save session data of the current request.
   *
   * @return bool
   *   FALSE if writing session data has been disabled. TRUE otherwise.
   */
  public function isEnabled() {
    return $this->enabled;
  }

  /**
   * Temporarily disables saving of session data.
   *
   * This function allows the caller to temporarily disable writing of
   * session data, should the request end while performing potentially
   * dangerous operations, such as manipulating the global $user object.
   *
   * @see https://drupal.org/node/218104
   *
   * @return $this
   */
  public function disable() {
    $this->enabled = FALSE;
    return $this;
  }

  /**
   * Re-enables saving of session data.
   *
   * @return $this
   */
  public function enable() {
    $this->enabled = TRUE;
    return $this;
  }

  /**
   * Returns whether mixed mode SSL sessions are enabled in the session manager.
   *
   * @return bool
   *   Value of the mixed mode SSL sessions flag.
   */
  public function isMixedMode() {
    return $this->mixedMode;
  }

  /**
   * Returns the name of the insecure session when operating in mixed mode SSL.
   *
   * @param string $name
   *   Current session name.
   * @return string
   *   The name of the insecure session.
   */
  public function getInsecureName($name) {
    if (strpos($name, 'SS') === 0) {
      $name = substr($name, 1);
    }
    return $name;
  }

}
