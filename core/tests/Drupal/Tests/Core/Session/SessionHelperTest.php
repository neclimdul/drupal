<?php
/**
 * @file
 * Contains Drupal\Test\Core\Session
 */

namespace Drupal\Tests\Core\Session;
use Drupal\Core\Session\SessionHelper;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;


/**
 * Tests the SessionHelper class.
 *
 * @group Drupal
 *
 * @coversDefaultClass \Drupal\Core\Session\SessionHelper
 */
class SessionHelperTest extends UnitTestCase {
  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Tests the SessionHelper class.',
      'description' => 'Tests the SessionHelper class.',
      'group' => 'Session',
    );
  }

  /**
   * @var \Drupal\Core\Session\SessionHelper
   */
  protected $sessionHelper;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $settings = new Settings(array());
    $this->sessionHelper = new SessionHelper($settings);
  }

  /**
   * Tests mixed mode setting.
   *
   * @covers ::__construct()
   * @covers ::isMixedMode()
   */
  public function testIsMixedMode() {
    $this->assertFalse($this->sessionHelper->isMixedMode(), 'Mixed mode defaults false.');

    $settings = new Settings(array('mixed_mode_sessions' => TRUE));
    $this->sessionHelper = new SessionHelper($settings);
    $this->assertTrue($this->sessionHelper->isMixedMode(), 'Mixed mode set to true.');

    $settings = new Settings(array('mixed_mode_sessions' => FALSE));
    $this->sessionHelper = new SessionHelper($settings);
    $this->assertFalse($this->sessionHelper->isMixedMode(), 'Mixed mode set to false.');
  }

  /**
   * Tests mixed mode is enabled default.
   *
   * @covers ::isEnabled()
   * @covers ::disable()
   * @covers ::enable()
   */
  public function testIsEnabled() {
    $this->assertTrue($this->sessionHelper->isEnabled(), '::isEnabled() initially returns TRUE.');
    $this->assertFalse($this->sessionHelper->disable()->isEnabled(), '::isEnabled() returns FALSE after disabling.');
    $this->assertTrue($this->sessionHelper->enable()->isEnabled(), '::isEnabled() returns TRUE after enabling.');
  }

  /**
   * Tests converting session name to insecure name.
   *
   * @covers ::getInsecureName()
   */
  public function testGetInsecureName() {
    $this->assertEquals('SFooBar', $this->sessionHelper->getInsecureName('SSFooBar'));
    $this->assertEquals('SFooBar', $this->sessionHelper->getInsecureName('SFooBar'));
  }

}
