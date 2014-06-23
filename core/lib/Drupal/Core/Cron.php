<?php

/**
 * @file
 * Contains \Drupal\Core\Cron.
 */

namespace Drupal\Core;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\SessionHelper;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Queue\SuspendQueueException;

/**
 * The Drupal core Cron service.
 */
class Cron implements CronInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The session helper.
   *
   * @var \Drupal\Core\Session\SessionHelper
   */
  protected $sessionHelper;

  /**
   * Constructs a cron object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *    The current user.
   * @param Session\SessionHelper $session_helper
   *    The session helper.
   */
  public function __construct(ModuleHandlerInterface $module_handler, LockBackendInterface $lock, QueueFactory $queue_factory, StateInterface $state, AccountProxyInterface $current_user, SessionHelper $session_helper) {
    $this->moduleHandler = $module_handler;
    $this->lock = $lock;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
    $this->currentUser = $current_user;
    $this->sessionHelper = $session_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    // Allow execution to continue even if the request gets cancelled.
    @ignore_user_abort(TRUE);

    // Prevent session information from being saved while cron is running.
    $original_session_saving = $this->sessionHelper->isEnabled();
    $this->sessionHelper->disable();

    // Force the current user to anonymous to ensure consistent permissions on
    // cron runs.
    $original_user = $this->currentUser->getAccount();
    $this->currentUser->setAccount(new AnonymousUserSession());

    // Try to allocate enough time to run all the hook_cron implementations.
    drupal_set_time_limit(240);

    $return = FALSE;

    // Try to acquire cron lock.
    if (!$this->lock->acquire('cron', 240.0)) {
      // Cron is still running normally.
      watchdog('cron', 'Attempting to re-run cron while it is already running.', array(), WATCHDOG_WARNING);
    }
    else {
      $this->invokeCronHandlers();
      $this->setCronLastTime();

      // Release cron lock.
      $this->lock->release('cron');

      // Return TRUE so other functions can check if it did run successfully
      $return = TRUE;
    }

    // Process cron queues.
    $this->processQueues();

    // Restore the user.
    $this->currentUser->setAccount($original_user);
    if ($original_session_saving) {
      $this->sessionHelper->enable();
    }

    return $return;
  }

  /**
   * Records and logs the request time for this cron invocation.
   */
  protected function setCronLastTime() {
    // Record cron time.
    $this->state->set('system.cron_last', REQUEST_TIME);
    watchdog('cron', 'Cron run completed.', array(), WATCHDOG_NOTICE);
  }

  /**
   * Processes cron queues.
   */
  protected function processQueues() {
    // Grab the defined cron queues.
    $queues = $this->moduleHandler->invokeAll('queue_info');
    $this->moduleHandler->alter('queue_info', $queues);

    foreach ($queues as $queue_name => $info) {
      if (isset($info['cron'])) {
        // Make sure every queue exists. There is no harm in trying to recreate
        // an existing queue.
        $this->queueFactory->get($queue_name)->createQueue();

        $callback = $info['worker callback'];
        $end = time() + (isset($info['cron']['time']) ? $info['cron']['time'] : 15);
        $queue = $this->queueFactory->get($queue_name);
        while (time() < $end && ($item = $queue->claimItem())) {
          try {
            call_user_func_array($callback, array($item->data));
            $queue->deleteItem($item);
          }
          catch (SuspendQueueException $e) {
            // If the worker indicates there is a problem with the whole queue,
            // release the item and skip to the next queue.
            $queue->releaseItem($item);

            watchdog_exception('cron', $e);

            // Skip to the next queue.
            continue 2;
          }
          catch (\Exception $e) {
            // In case of any other kind of exception, log it and leave the item
            // in the queue to be processed again later.
            watchdog_exception('cron', $e);
          }
        }
      }
    }
  }

  /**
   * Invokes any cron handlers implementing hook_cron.
   */
  protected function invokeCronHandlers() {
    // Iterate through the modules calling their cron handlers (if any):
    foreach ($this->moduleHandler->getImplementations('cron') as $module) {
      // Do not let an exception thrown by one module disturb another.
      try {
        $this->moduleHandler->invoke($module, 'cron');
      }
      catch (\Exception $e) {
        watchdog_exception('cron', $e);
      }
    }
  }

}
