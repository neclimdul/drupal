<?php

/**
 * @file
 * Contains \Drupal\custom_block\Tests\Menu\CustomBlockLocalTasksTest
 */

namespace Drupal\custom_block\Tests\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests existence of custom_block local tasks.
 *
 * @group Drupal
 * @group Block
 */
class CustomBlockLocalTasksTestt extends LocalTaskIntegrationTest {

  /**
   * SimpleTest info.
   */
  public static function getInfo() {
    return array(
      'name' => 'Custom Block local tasks test',
      'description' => 'Test custom_block local tasks.',
      'group' => 'Block',
    );
  }

  /**
   * @{inheritdoc}
   */
  public function setUp() {
    $this->moduleList = array(
      'block' => 'core/modules/block/block.info',
      'custom_block' => 'core/modules/block/custom_block/custom_block.info',
    );
    parent::setUp();
  }

  /**
   * Check custom_block listing local tasks
   *
   * @dataProvider getCustomBlockListingRoutes
   */
  public function testCustomBlockListLocalTasks($route) {
    //
    $this->assertLocalTasks($route, array(
      0 => array(
        'block.admin_display',
        'custom_block.list',
      ),
      1 => array(
        'custom_block.list_sub',
        'custom_block.type_list',
      )
    ));
  }

  /**
   * Provide a list of routes to test.C
   */
  public function getCustomBlockListingRoutes() {
    return array(
      array('custom_block.list', 'custom_block.type_list'),
    );
  }
}
