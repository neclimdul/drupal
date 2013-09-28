<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Menu\LocalTaskUnitTest.
 */

namespace Drupal\Tests\Core\Menu;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\Language;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Tests\UnitTestCase;

/**
 * Base unit test for testing existence of local tasks.
 *
 * @todo help do access checking.
 * @todo help with url building, title and other data checks.
 */
abstract class LocalTaskIntegrationTest extends UnitTestCase {

  /**
   * A list of modules used for yaml searching..
   *
   * @var array
   */
  protected $moduleList;

  /**
   * Setups the local task manager for the test.
   */
  protected function getLocalTaskManager($modules, $route_name, $route_params) {
    $manager = $this
      ->getMockBuilder('Drupal\Core\Menu\LocalTaskManager')
      ->disableOriginalConstructor()
      ->setMethods(NULL)
      ->getMock();

    $controllerResolver = $this->getMock('Symfony\Component\HttpKernel\Controller\ControllerResolverInterface');
    $property = new \ReflectionProperty('Drupal\Core\Menu\LocalTaskManager', 'controllerResolver');
    $property->setAccessible(TRUE);
    $property->setValue($manager, $controllerResolver);

    // todo mock a request with a route.
    $request = $this->getMock('Symfony\Component\HttpFoundation\Request');
    $property = new \ReflectionProperty('Drupal\Core\Menu\LocalTaskManager', 'request');
    $property->setAccessible(TRUE);
    $property->setValue($manager, $request);

    $accessManager = $this->getMockBuilder('Drupal\Core\Access\AccessManager')
      ->disableOriginalConstructor()
      ->getMock();    $property = new \ReflectionProperty('Drupal\Core\Menu\LocalTaskManager', 'accessManager');
    $property->setAccessible(TRUE);
    $property->setValue($manager, $accessManager);

    $module_handler = new ModuleHandler($modules);
    $pluginDiscovery = new YamlDiscovery('local_tasks', $module_handler->getModuleDirectories());
    $property = new \ReflectionProperty('Drupal\Core\Menu\LocalTaskManager', 'discovery');
    $property->setAccessible(TRUE);
    $property->setValue($manager, $pluginDiscovery);

    $factory = $this->getMock('Drupal\Component\Plugin\Factory\FactoryInterface');
    $property = new \ReflectionProperty('Drupal\Core\Menu\LocalTaskManager', 'factory');
    $property->setAccessible(TRUE);
    $property->setValue($manager, $factory);

    $language_manager = $this->getMockBuilder('Drupal\Core\Language\LanguageManager')
      ->disableOriginalConstructor()
      ->getMock();
    $language_manager->expects($this->any())
      ->method('getLanguage')
      ->will($this->returnValue(new Language(array('id' => 'en'))));

    $cache_backend = $this->getMock('Drupal\Core\Cache\CacheBackendInterface');
    $manager->setCacheBackend($cache_backend, $language_manager, 'local_task');

    return $manager;
  }

  /**
   * Integration test for local tasks.
   *
   * @param $route_name
   *   Route name to base task building on.
   * @param $expected_tasks
   *   A list of tasks groups by level expected at the given route
   * @param array $route_params
   *   (optional) a list of route parameters used to resolve tasks.
   */
  protected function assertLocalTasks($route_name, $expected_tasks, $route_params = array()) {

    $manager = $this->getLocalTaskManager($this->moduleList, $route_name, $route_params);

    $tmp_tasks = $manager->getLocalTasksForRoute($route_name);

    // At this point we're just testing existence so pull out keys and then compare.
    $tasks = array();
    foreach ($tmp_tasks as $level => $level_tasks) {
      $tasks[$level] = array_keys($level_tasks);
    }
    $this->assertEquals($expected_tasks, $tasks);
  }
}
