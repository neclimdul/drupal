<?php

/**
 * @file
 * Contains \Drupal\Core\Session\SessionListener.
 */

namespace Drupal\Core\Session;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\EventListener\SessionListener as SymfonySessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * ...
 */
class SessionListener extends SymfonySessionListener {

  /**
   * @var ContainerInterface
   */
  private $container;

  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  public static function getSubscribedEvents() {
    $subscribers = parent::getSubscribedEvents();
    $subscribers[KernelEvents::RESPONSE] = array('onKernelResponse');
    return $subscribers;
  }

  public function onKernelResponse(FilterResponseEvent $event) {
    global $user;

    /** @var \Drupal\Core\Session\Storage\NativeSessionStorage $session_storage */
    $session_storage = $this->container->get('session.storage');

    if ($user->isAnonymous() && $session_storage->isSessionObsolete()) {
      // There is no session data to store, destroy the session if it was
      // previously started.
      if ($session_storage->getSaveHandler()->isActive()) {
        session_destroy();

        // Cleanup and remove extra cookies.
        $response = $event->getResponse();
        $session_name = $this->container->get('session')->getName();
        $session_helper = $this->container->get('session.helper');
        $insecure_name = $session_helper->getInsecureName($session_name);

        // Unset the session cookies.
        $is_https = $event->getRequest()->isSecure();
        $this->clearCookie($response, $session_name, $is_https);
        if ($is_https) {
          $this->clearCookie($response, $insecure_name, FALSE);
        }
        elseif ($session_helper->isMixedMode()) {
          $this->clearCookie($response, 'S' . $session_name, TRUE);
        }
      }
    }
  }

  public function onKernelRequest(GetResponseEvent $event) {
    parent::onKernelRequest($event);

    if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
      return;
    }

    $this->container->get('session.storage')->initialize();
  }

  /**
   * Internal helper method for clearing cookies.
   *
   * ResponseHeaderBag::clearCookie does not support clearing secure cookies.
   * Because of this we implement our own method with some other features.
   */
  protected function clearCookie(Response $response, $name, $secure = FALSE) {
    $params = session_get_cookie_params();
    $response->headers->setCookie(new Cookie($name, null, 1, $params['path'], $params['domain'], $secure, $params['httponly']));
  }

  /**
   * @{inheritdoc}
   *
   * @return \Symfony\Component\HttpFoundation\Session\Session
   */
  protected function getSession() {
    if ($this->container->has('session')) {
      return $this->container->get('session');
    }
    return NULL;
  }

}
