<?php

/**
 * @file
 * Contains \Drupal\user\Tests\Menu\UserLocalTasksTest
 */

namespace Drupal\user\Tests\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests existence of user local tasks.
 *
 * @group Drupal
 * @group User
 */
class UserLocalTasksTest extends LocalTaskIntegrationTest {

  /**
   * SimpleTest info.
   */
  public static function getInfo() {
    return array(
      'name' => 'User local tasks test',
      'description' => 'Test user local tasks.',
      'group' => 'User',
    );
  }

  /**
   * @{inheritdoc}
   */
  public function setUp() {
    $this->moduleList = array('user' => 'core/modules/user/user.info');
    parent::setUp();
  }

//  /**
//   * Test local task existence.
//   *
//   * @dataProvider getUserAdminRoutes
//   */
//  public function tesstUserAdminLocalTasks() {
//  }
//
//  public function getUserAdminRoutes() {
//    return array(
//      array('user.page', 'user.register', 'user.pass'),
//    );
//  }
//
  /**
   * Check user listing local tasks
   *
   * @dataProvider getUserLoginRoutes
   */
  public function testUserLoginLocalTasks($route, $subtask = array()) {
    $tasks = array(
      0 => array('user.page', 'user.register', 'user.pass',),
    );
    if ($subtask) $tasks[] = $subtask;
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Provide a list of routes to test.
   */
  public function getUserLoginRoutes() {
    return array(
      array('user.page', array('user.login',)),
      array('user.login', array('user.login',)),
      array('user.register'),
      array('user.pass'),
    );
  }

  /**
   * Check user listing local tasks
   *
   * @dataProvider getUserPageRoutes
   */
  public function testUserPageLocalTasks($route, $subtask = array()) {
    $tasks = array(
      0 => array('user.view', 'user.edit',),
    );
    if ($subtask) $tasks[] = $subtask;
    $this->assertLocalTasks($route, $tasks);
  }

  /**
   * Provide a list of routes to test.
   */
  public function getUserPageRoutes() {
    return array(
      array('user.view'),
      array('user.edit'),
    );
  }

}
