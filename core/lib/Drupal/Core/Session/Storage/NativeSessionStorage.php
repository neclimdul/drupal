<?php

/**
 * @file
 * Contains \Drupal\Core\Session\Storage\NativeSessionStorage.
 */

namespace Drupal\Core\Session\Storage;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\SessionHelper;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag as SymfonyMetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage as SymfonyNativeSessionStorage;

/**
 * Manages user sessions.
 *
 * This class implements the custom session management code inherited from
 * Drupal 7 on top of the corresponding Symfony component. Regrettably the name
 * NativeSessionStorage is not quite accurate. In fact the responsibility for
 * storing and retrieving session data has been extracted from it in Symfony 2.1
 * but the class name was not changed.
 *
 * @todo
 *   In fact the NativeSessionStorage class already implements all of the
 *   functionality required by a typical Symfony application. Normally it is not
 *   necessary to subclass it at all. In order to reach the point where Drupal
 *   can use the Symfony session management unmodified, the code implemented
 *   here needs to be extracted either into a dedicated session handler proxy
 *   (e.g. mixed mode SSL, sid-hashing) or relocated to the authentication
 *   subsystem.
 */
class NativeSessionStorage extends SymfonyNativeSessionStorage implements SessionManagerInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Whether a lazy session has been started.
   *
   * @var bool
   */
  protected $lazySession;

  /**
   * Constructs a new session manager instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\Session\Storage\MetadataBag $metadata_bag
   *   The session metadata bag.
   * @param \Drupal\Core\Session\SessionHelper $session_helper
   *   The session helper.
   * @param \SessionHandlerInterface $session_handler
   *   The session handler.
   */
  public function __construct(RequestStack $request_stack, Connection $connection, SymfonyMetadataBag $metadata_bag, SessionHelper $session_helper, \SessionHandlerInterface $session_handler) {
    parent::__construct();
    $this->requestStack = $request_stack;
    $this->connection = $connection;
    $metadata_bag->getLastUsed();
    $this->setMetadataBag($metadata_bag);

    $this->sessionHelper = $session_helper;

    // @todo When not using the Symfony Session object, the list of bags in the
    //   NativeSessionStorage will remain uninitialized. This will lead to
    //   errors in NativeSessionStorage::loadSession. Remove this after
    //   https://drupal.org/node/2229145, when we will be using the Symfony
    //   session object (which registers an attribute bag with the
    //   manager upon instantiation).
    $this->bags = array();

    // Register the default session handler.
    $this->setSaveHandler($session_handler);
  }

  /**
   * {@inheritdoc}
   */
  public function initialize() {
    global $user;

    $is_https = $this->requestStack->getCurrentRequest()->isSecure();
    $cookies = $this->requestStack->getCurrentRequest()->cookies;
    $insecure_session_name = $this->sessionHelper->getInsecureName($this->getName());
    if (($cookies->has($this->getName()) && ($session_name = $cookies->get($this->getName()))) || ($is_https && $this->sessionHelper->isMixedMode() && ($cookies->has($insecure_session_name) && ($session_name = $cookies->get($insecure_session_name))))) {
      // If a session cookie exists, initialize the session. Otherwise the
      // session is only started on demand in save(), making
      // anonymous users not use a session cookie unless something is stored in
      // $_SESSION. This allows HTTP proxies to cache anonymous pageviews.
      $this->start();
      if ($user->isAuthenticated() || !$this->isSessionObsolete()) {
        drupal_page_is_cacheable(FALSE);
      }
    }
    else {
      // Set a session identifier for this request. This is necessary because
      // we lazily start sessions at the end of this request, and some
      // processes (like drupal_get_token()) needs to know the future
      // session ID in advance.
      $this->lazySession = TRUE;
      $user = new AnonymousUserSession();
      $this->setId(Crypt::randomBytesBase64());
      if ($is_https && $this->sessionHelper->isMixedMode()) {
        $session_id = Crypt::randomBytesBase64();
        $cookies->set($insecure_session_name, $session_id);
      }
    }
    date_default_timezone_set(drupal_get_user_timezone());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function start() {
    if (!$this->sessionHelper->isEnabled() || $this->isCli()) {
      return;
    }
    // Save current session data before starting it, as PHP will destroy it.
    $session_data = isset($_SESSION) ? $_SESSION : NULL;

    $result = parent::start();

    // Restore session data.
    if (!empty($session_data)) {
      $_SESSION += $session_data;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    global $user;

    if (!$this->sessionHelper->isEnabled()) {
      // We don't have anything to do if we are not allowed to save the session.
      return;
    }

    if ($user->isAnonymous() && $this->isSessionObsolete()) {
      // There is no session data to store, destroy the session if it was
      // previously started.
      if ($this->getSaveHandler()->isActive()) {
        session_destroy();
      }
    }
    else {
      // There is session data to store. Start the session if it is not already
      // started.
      if (!$this->isStarted()) {
        $this->start();
        if ($this->requestStack->getCurrentRequest()->isSecure() && $this->sessionHelper->isMixedMode()) {
          $insecure_session_name = $this->sessionHelper->getInsecureName($this->getName());
          $params = session_get_cookie_params();
          $expire = $params['lifetime'] ? REQUEST_TIME + $params['lifetime'] : 0;
          $cookie_params = $this->requestStack->getCurrentRequest()->cookies;
          setcookie($insecure_session_name, $cookie_params->get($insecure_session_name), $expire, $params['path'], $params['domain'], FALSE, $params['httponly']);
        }
      }
      // Write the session data.
      parent::save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function regenerate($destroy = FALSE, $lifetime = NULL) {
    global $user;

    // Nothing to do if we are not allowed to change the session.
    if (!$this->sessionHelper->isEnabled()) {
      return;
    }

    // We do not support the optional $destroy and $lifetime parameters as long
    // as #2238561 remains open.
    if ($destroy || isset($lifetime)) {
      throw new \InvalidArgumentException('The optional parameters $destroy and $lifetime of NativeSessionStorage::regenerate() are not supported currently');
    }

    $is_https = $this->requestStack->getCurrentRequest()->isSecure();
    $cookies = $this->requestStack->getCurrentRequest()->cookies;

    if ($is_https && $this->sessionHelper->isMixedMode()) {
      $insecure_session_name = $this->sessionHelper->getInsecureName($this->getName());;
      if (!isset($this->lazySession) && $cookies->has($insecure_session_name)) {
        $old_insecure_session_id = $cookies->get($insecure_session_name);
      }
      $params = session_get_cookie_params();
      $session_id = Crypt::randomBytesBase64();
      // If a session cookie lifetime is set, the session will expire
      // $params['lifetime'] seconds from the current request. If it is not set,
      // it will expire when the browser is closed.
      $expire = $params['lifetime'] ? REQUEST_TIME + $params['lifetime'] : 0;
      setcookie($insecure_session_name, $session_id, $expire, $params['path'], $params['domain'], FALSE, $params['httponly']);
      $cookies->set($insecure_session_name, $session_id);
    }

    if ($this->isStarted()) {
      $old_session_id = $this->getId();
    }
    session_id(Crypt::randomBytesBase64());

    // @todo The token seed can be moved onto \Drupal\Core\Session\MetadataBag.
    //   The session manager then needs to notify the metadata bag when the
    //   token should be regenerated. https://drupal.org/node/2256257
    if (!empty($_SESSION)) {
      unset($_SESSION['csrf_token_seed']);
    }

    if (isset($old_session_id)) {
      $params = session_get_cookie_params();
      $expire = $params['lifetime'] ? REQUEST_TIME + $params['lifetime'] : 0;
      setcookie($this->getName(), $this->getId(), $expire, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
      $fields = array('sid' => Crypt::hashBase64($this->getId()));
      if ($is_https) {
        $fields['ssid'] = Crypt::hashBase64($this->getId());
        // If the "secure pages" setting is enabled, use the newly-created
        // insecure session identifier as the regenerated sid.
        if ($this->sessionHelper->isMixedMode()) {
          $fields['sid'] = Crypt::hashBase64($session_id);
        }
      }
      $this->connection->update('sessions')
        ->fields($fields)
        ->condition($is_https ? 'ssid' : 'sid', Crypt::hashBase64($old_session_id))
        ->execute();
    }
    elseif (isset($old_insecure_session_id)) {
      // If logging in to the secure site, and there was no active session on
      // the secure site but a session was active on the insecure site, update
      // the insecure session with the new session identifiers.
      $this->connection->update('sessions')
        ->fields(array('sid' => Crypt::hashBase64($session_id), 'ssid' => Crypt::hashBase64($this->getId())))
        ->condition('sid', Crypt::hashBase64($old_insecure_session_id))
        ->execute();
    }
    else {
      // Start the session when it doesn't exist yet.
      // Preserve the logged in user, as it will be reset to anonymous
      // by \Drupal\Core\Session\Storage\Handler\SessionHandler::read().
      $account = $user;
      $this->start();
      $user = $account;
    }
    date_default_timezone_set(drupal_get_user_timezone());
  }

  /**
   * {@inheritdoc}
   */
  public function delete($uid) {
    // Nothing to do if we are not allowed to change the session.
    if (!$this->sessionHelper->isEnabled()) {
      return;
    }
    $this->connection->delete('sessions')
      ->condition('uid', $uid)
      ->execute();
  }

  /**
   * Returns whether the current PHP process runs on CLI.
   *
   * Command line clients do not support cookies nor sessions.
   *
   * @return bool
   */
  protected function isCli() {
    return PHP_SAPI === 'cli';
  }

  /**
   * Determines whether the session contains user data.
   *
   * @return bool
   *   TRUE when the session does not contain any values and therefore can be
   *   destroyed.
   */
  protected function isSessionObsolete() {
    $used_session_keys = array_filter($this->getSessionDataMask());
    return empty($used_session_keys);
  }

  /**
   * Returns a map specifying which session key is containing user data.
   *
   * @return array
   *   An array where keys correspond to the session keys and the values are
   *   booleans specifying whether the corresponding session key contains any
   *   user data.
   */
  protected function getSessionDataMask() {
    if (empty($_SESSION)) {
      return array();
    }

    // Start out with a completely filled mask.
    $mask = array_fill_keys(array_keys($_SESSION), TRUE);

    // Ignore the metadata bag, it does not contain any user data.
    $mask[$this->metadataBag->getStorageKey()] = FALSE;

    // Ignore the CSRF token seed.
    //
    // @todo Anonymous users should not get a CSRF token at any time, or if they
    //   do, then the originating code is responsible for cleaning up the
    //   session once obsolete. Since that is not guaranteed to be the case,
    //   this check force-ignores the CSRF token, so as to avoid performance
    //   regressions.
    //   The token seed can be moved onto \Drupal\Core\Session\MetadataBag. This
    //   will result in the CSRF token being ignored automatically.
    //   https://drupal.org/node/2256257
    $mask['csrf_token_seed'] = FALSE;

    // Ignore attribute bags when they do not contain any data.
    foreach ($this->bags as $bag) {
      $key = $bag->getStorageKey();
      $mask[$key] = empty($_SESSION[$key]);
    }

    return array_intersect_key($mask, $_SESSION);
  }

}
