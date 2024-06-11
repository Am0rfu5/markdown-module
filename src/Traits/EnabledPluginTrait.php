<?php

namespace Drupal\markdown\Traits;

/**
 * Trait for adding an "enabled" state to plugins.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
trait EnabledPluginTrait {

  /**
   * {@inheritdoc}
   */
  public function enabledByDefault() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    $enabled = $this->config->get('enabled');
    return isset($enabled) ? !!$enabled : $this->enabledByDefault();
  }

}
