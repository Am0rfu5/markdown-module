<?php

namespace Drupal\markdown\Traits;

use Drupal\markdown\Plugin\Markdown\EnabledPluginInterface;

/**
 * Trait for plugin managers that are "enable" aware.
 */
trait EnableAwarePluginManagerTrait {

  /**
   * {@inheritdoc}
   */
  public function enabled(array $configuration = []) {
    return array_filter($this->installed($configuration), function (EnabledPluginInterface $plugin) {
      return $plugin->isEnabled();
    });
  }

}
