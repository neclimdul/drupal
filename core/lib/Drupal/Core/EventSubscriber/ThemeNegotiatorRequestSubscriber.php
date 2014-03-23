<?php

/**
 * @file
 * Contains \Drupal\Core\EventSubscriber\ThemeNegotiatorRequestSubscriber.
 */

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the $request property on the theme negotiation service.
 */
class ThemeNegotiatorRequestSubscriber implements EventSubscriberInterface {

  /**
   * The theme negotiator service.
   *
   * @var \Drupal\Core\Theme\ThemeNegotiatorInterface
   */
  protected $themeNegotiator;

  /**
   * Constructs a ThemeNegotiatorRequestSubscriber object.
   *
   * @param \Drupal\Core\Theme\ThemeNegotiatorInterface $theme_negotiator
   *   The theme negotiator service.
   */
  public function __construct(ThemeNegotiatorInterface $theme_negotiator) {
    $this->themeNegotiator = $theme_negotiator;
  }

  /**
   * Sets the request on the language manager.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function onKernelRequestThemeNegotiator(GetResponseEvent $event) {
    if ($event->getRequestType() == HttpKernelInterface::MASTER_REQUEST) {
      $this->themeNegotiator->setRequest($event->getRequest());
      // Let all modules take action before the menu system handles the request.
      // We do not want this while running update.php.
      if (!defined('MAINTENANCE_MODE') || MAINTENANCE_MODE != 'update') {
        // @todo Refactor drupal_theme_initialize() into a request subscriber.
        drupal_theme_initialize($event->getRequest());
      }
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('onKernelRequestThemeNegotiator', 100);

    return $events;
  }

}
