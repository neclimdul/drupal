<?php

/**
 * @file
 * Contains \Drupal\node\Plugin\Menu\NodeViewLocalTask.
 */

namespace Drupal\node\Plugin\Menu;

use Drupal\Core\Menu\LocalTaskDefault;

class NodeViewLocalTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  function getTitle() {
    // @todo support node labels through request or something.
    return parent::getTitle();
    return $node->label();
  }
}
