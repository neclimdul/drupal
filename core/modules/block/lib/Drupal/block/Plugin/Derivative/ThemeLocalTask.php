<?php

/**
 * @file
 * Contains \Drupal\system\Plugin\Derivative\ThemeLocalTask.
 */

namespace Drupal\block\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DerivativeBase;

/**
 * Provides dynamic tabs based on active themes.
 */
class ThemeLocalTask extends DerivativeBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions(array $base_plugin_definition) {
    $default_theme = \Drupal::config('system.theme')->get('default');

    foreach (list_themes() as $theme_name => $theme) {
      if ($theme->status) {
        $this->derivatives[$theme_name] = $base_plugin_definition;
        $this->derivatives[$theme_name]['title'] = $theme->info['name'];
        $this->derivatives[$theme_name]['route_parameters'] = array('theme_name' => $theme_name);
      }
      // Default task!
      if ($default_theme == $theme_name) {
        $this->derivatives[$theme_name]['route_name'] = 'block.admin_display';
        // Emulate default logic because without the base plugin id we can't set the
        // change the tab_root_id.
        $this->derivatives[$theme_name]['weight'] = -10;

        unset($this->derivatives[$theme_name]['route_parameters']);
      }
    }
    return $this->derivatives;
  }

}
