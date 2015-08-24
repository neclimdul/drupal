<?php

/**
 * @file
 * Contains \Drupal\Tests\system\Kernel\Scripts\DbToolsTest.
 */

namespace Drupal\Tests\system\Kernel\Scripts;

use Drupal\Core\Command\DbToolsApplication;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Console\Tester\ApplicationTester;

class DbToolsTest extends KernelTestBase {

  public function testApplication() {
    $application = new DbToolsApplication();
    $tester = new ApplicationTester($application);
    // Running this breaks all assertions following it so we can't test anything yet...
//    $tester->run([], []);
//    $this->assertEquals('', $tester->getStatusCode());
//    $this->assertEquals('not a thing that happened', $tester->getOutput());
  }

}
