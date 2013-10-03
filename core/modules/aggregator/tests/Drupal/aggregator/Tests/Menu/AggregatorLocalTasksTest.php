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

  /**
   * SimpleTest info.
   */
  public static function getInfo() {
    return array(
      'name' => 'Aggregator local tasks test',
      'description' => 'Test existence of aggregator local tasks.',
      'group' => 'Aggregator',
    );
  }

  /**
   * @{inheritdoc}
   */
  public function setUp() {
    $this->moduleList = array('aggregator' => 'core/modules/aggregator/aggregator.info');
    parent::setUp();
  }

  /**
   * Test local task existence.
   *
   * @dataProvider getAggregatorAdminRoutes
   */
  public function testAggregatorAdminLocalTasks($route) {
    $this->assertLocalTasks($route, array(
      0 => array('aggregator.admin_overview', 'aggregator.admin_settings'),
    ));
  }

  /**
   * Provide a list of routes to test.
   */
  public function getAggregatorAdminRoutes() {
    return array(
      array('aggregator.admin_overview'),
      array('aggregator.admin_settings'),
    );
  }

  /**
   * Check category aggregator tasks.
   *
   * @dataProvider getAggregatorCategoryRoutes
   */
  public function testAggregatorCategoryLocalTasks($route) {
    $this->assertLocalTasks($route, array(
      0 => array('aggregator.category_view', 'aggregator.categorize_category_form', 'aggregator.category_edit'),
    ));
    ;
  }

  /**
   * Provide a list of routes to test.
   */
  public function getAggregatorCategoryRoutes() {
    return array(
      array('aggregator.category_view'),
      array('aggregator.categorize_category_form'),
      array('aggregator.category_edit'),
    );
  }

  //
  /**
   * Check category aggregator tasks.
   *
   * @dataProvider getAggregatorSourceRoutes
   */
  public function testAggregatorSourceLocalTasks($route) {
    $this->assertLocalTasks($route, array(
      0 => array('aggregator.feed_view', 'aggregator.categorize_feed_form', 'aggregator.feed_configure'),
    ));
    ;
  }

  /**
   * Provide a list of routes to test.
   */
  public function getAggregatorSourceRoutes() {
    return array(
      array('aggregator.feed_view'),
      array('aggregator.categorize_feed_form'),
      array('aggregator.feed_configure'),
    );
  }
}
