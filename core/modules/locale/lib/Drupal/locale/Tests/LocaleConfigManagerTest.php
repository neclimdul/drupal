<?php

/**
 * @file
 * Contains \Drupal\locale\Tests\LocaleConfigManagerTest.
 */

namespace Drupal\locale\Tests;

use Drupal\locale\LocaleConfigManager;
use Drupal\Core\Language\Language;
use Drupal\Core\Config\StorageException;
use Drupal\simpletest\DrupalUnitTestBase;

/**
 * Provides tests for \Drupal\locale\LocaleConfigManager
 */
class LocaleConfigManagerTest extends DrupalUnitTestBase {

  /**
   * A list of modules to install for this test.
   *
   * @var array
   */
  public static $modules = array('language', 'locale', 'locale_test');

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Locale config manager',
      'description' => 'Tests that the locale config manager operates correctly.',
      'group' => 'Locale',
    );
  }

  /**
   * Tests hasTranslation().
   */
  public function testHasTranslation() {
    $this->installConfig(array('locale_test'));
    $locale_config_manager = \Drupal::service('locale.config.typed');

    $language = language_save(new Language(array('id' => 'de')));
    $result = $locale_config_manager->hasTranslation('locale_test.no_translation', $language);
    $this->assertFalse($result, 'There is no translation for locale_test.no_translation configuration.');

    $result = $locale_config_manager->hasTranslation('locale_test.translation', $language);
    $this->assertTrue($result, 'There is a translation for locale_test.translation configuration.');
  }
}
