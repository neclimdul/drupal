<?php

/**
 * @file
 * Contains \Drupal\block\Tests\Menu\BlockLocalTasksTest
 */

namespace Drupal\block\Tests\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests existence of block local tasks.
 *
 * @group Drupal
 * @group Block
 */
class BlockLocalTasksTest extends LocalTaskIntegrationTest {

  /**
   * SimpleTest info.
   */
  public static function getInfo() {
    return array(
      'name' => 'Block local tasks test',
      'description' => 'Test block local tasks.',
      'group' => 'Block',
    );
  }

  /**
   * @{inheritdoc}
   */
  public function setUp() {
    $this->moduleList = array('block' => 'core/modules/block/block.info');
    parent::setUp();
  }

  /**
   * Test local task existence.
   */
  public function testBlockAdminLocalTasks() {
    $this->markTestIncomplete(
      'This test has not been implemented yet. list_theme() and Drupal::config() calls fail.'
    );
    //$this->assertLocalTasks('block.admin_edit', array(array('block.admin_edit')));
  }

  /**
   * Check block listing local tasks
   *
   * @dataProvider getBlockListingRoutes
   */
  public function testBlockListLocalTasks($route) {
    $this->markTestIncomplete(
      'This test has not been implemented yet. list_theme() and Drupal::config() calls fail.'
    );
    //
//    $this->assertLocalTasks($route, array(
//      0 => array('aggregator.category_view', 'aggregator.categorize_feed_form', 'aggregator.feed_configure'),
//    ));
  }

  /**
   * Provide a list of routes to test.
   */
  public function getBlockListingRoutes() {
    return array(
      array('placeholder'),
      // theme_list()
    );
  }
}
