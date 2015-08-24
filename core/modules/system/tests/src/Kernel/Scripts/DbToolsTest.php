<?php

/**
 * @file
 * Contains \Drupal\Tests\system\Kernel\Scripts\DbToolsTest.
 */

namespace Drupal\Tests\system\Kernel\Scripts;

use Drupal\Core\Command\DbToolsApplication;

use Drupal\KernelTests\KernelTestBase;

class DbToolsTest extends KernelTestBase {

  /**
   * Test that the dump command is correctly registered.
   */
  public function testDumpCommandRegistration() {
    $application = new DbToolsApplication();
    $command = $application->find('dump');
    $this->assertInstanceOf('\Drupal\Core\Command\DbDumpCommand', $command);
  }

}
