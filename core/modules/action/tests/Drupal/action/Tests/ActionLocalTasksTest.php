<?php

namespace Drupal\action\Tests;

use Drupal\Tests\Core\Menu\LocalTaskUnitTest;

class ActionLocalTasksTest extends LocalTaskUnitTest {

  public static function getInfo() {
    return array(
      'name' => 'Action local tasks test',
      'description' => 'Test action local tasks.',
      'group' => 'Action',
    );
  }

  public function testActionLocalTasks() {
//    $this->assertFalse(true, 'fail');
    $this->assertLocalTasks('test', array('test'));
  }
}
