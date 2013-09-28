<?php

/**
 * @file
 * Contains \Drupal\aggregator\Tests\Menu\AggregatorLocalTasksTest
 */

namespace Drupal\aggregator\Tests\Menu;

use Drupal\Tests\Core\Menu\LocalTaskIntegrationTest;

/**
 * Tests existence of aggregator local tasks.
 *
 * @group Drupal
 * @group Aggregator
 */
class AggregatorLocalTasksTest extends LocalTaskIntegrationTest {

  public static function getInfo() {
    return array(
      'name' => 'Aggregator local tasks test',
      'description' => 'Test existence of aggregator local tasks.',
      'group' => 'Aggregator',
    );
  }

  public function setUp() {
    $this->moduleList = array('aggregator' => 'core/modules/aggregator/aggregator.info');
    parent::setUp();
  }

  /**
   * Test local task existence.
   */
  public function testAggregatorLocalTasks() {
    $this->assertLocalTasks('aggregator.admin_overview', array(
      0 => array('aggregator.admin_overview', 'aggregator.admin_settings'),
    ));
  }
}
