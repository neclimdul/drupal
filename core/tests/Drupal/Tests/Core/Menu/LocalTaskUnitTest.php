<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Menu\LocalTaskUnitTest.
 */

namespace Drupal\Tests\Core\Menu;

use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Language\Language;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zend\Stdlib\ArrayObject;

/**
 * Base unit test for testing existence of local tasks.
 */
abstract class LocalTaskUnitTest extends UnitTestCase {

  /**
   * The tested manager.
   *
   * @var \Drupal\Core\Menu\LocalTaskManager
   */
  protected $manager;

  /**
   * The mocked controller resolver.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $controllerResolver;

  /**
   * The test request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The mocked route provider.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $routeProvider;

  /**
   * The mocked plugin discovery.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $pluginDiscovery;

  /**
   * The plugin factory used in the test.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $factory;

  /**
   * The cache backend used in the test.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $cacheBackend;

  /**
   * The mocked access manager.
   *
   * @var \Drupal\Core\Access\AccessManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $accessManager;

  public function setUp() {
    parent::setUp();

    $this->controllerResolver = $this->getMock('Symfony\Component\HttpKernel\Controller\ControllerResolverInterface');
    $this->request = new Request();
    $this->routeProvider = $this->getMock('Drupal\Core\Routing\RouteProviderInterface');
    $this->factory = $this->getMock('Drupal\Component\Plugin\Factory\FactoryInterface');
    $this->cacheBackend = $this->getMock('Drupal\Core\Cache\CacheBackendInterface');
    $this->accessManager = $this->getMockBuilder('Drupal\Core\Access\AccessManager')
      ->disableOriginalConstructor()
      ->getMock();

    $this->setupLocalTaskManager();
  }

  /**
   * Setups the local task manager for the test.
   */
  protected function setupLocalTaskManager(array $modules) {
    $this->manager = $this
      ->getMockBuilder('Drupal\Core\Menu\LocalTaskManager')
      ->disableOriginalConstructor()
      ->setMethods(NULL)
      ->getMock();

//    $this->manager = new LocalTaskManager()

    $module_handler = new ModuleHandler($modules);
    $this->pluginDiscovery = new YamlDiscovery('local_tasks', $module_handler->getModuleDirectories());



    $language_manager = $this->getMockBuilder('Drupal\Core\Language\LanguageManager')
      ->disableOriginalConstructor()
      ->getMock();
    $language_manager->expects($this->any())
      ->method('getLanguage')
      ->will($this->returnValue(new Language(array('id' => 'en'))));

    $this->manager->setCacheBackend($this->cacheBackend, $language_manager, 'local_task');
  }

  /**
   * @param $route_name
   * @param $expected_tasks
   */
  protected function assertLocalTasks($route_name, $expected_tasks) {

    $tasks = $this->manager->getLocalTasksForRoute($route_name);
    var_dump($tasks);
    $this->assertEquals($expected_tasks, $tasks);
    $this->assertFalse(true);
  }
}
