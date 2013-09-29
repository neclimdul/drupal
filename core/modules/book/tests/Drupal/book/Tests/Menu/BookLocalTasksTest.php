<?php

/**
 * @file
 * Contains \Drupal\book\Tests\Menu\BookLocalTasksTest
 */

namespace Drupal\book\Tests\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests existence of book local tasks.
 *
 * @group Drupal
 * @group Book
 */
class BookLocalTasksTest extends LocalTaskIntegrationTest {

  /**
   * SimpleTest info.
   */
  public static function getInfo() {
    return array(
      'name' => 'Book local tasks test',
      'description' => 'Test existence of book local tasks.',
      'group' => 'Book',
    );
  }

  /**
   * @{inheritdoc}
   */
  public function setUp() {
    $this->moduleList = array('book' => 'core/modules/book/book.info');
    parent::setUp();
  }

  /**
   * Test local task existence.
   *
   * @dataProvider getBookAdminRoutes
   */
  public function testBookAdminLocalTasks($route) {

    $this->assertLocalTasks($route, array(
      0 => array('book.admin', 'book.settings'),
    ));
  }

  /**
   * Provide a list of routes to test.
   */
  public function getBookAdminRoutes() {
    return array(
      array('book.admin'),
      array('book.settings'),
    );
  }
}
