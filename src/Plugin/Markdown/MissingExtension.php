<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\markdown\Traits\EnabledPluginTrait;
use Drupal\markdown\Traits\ParserAwareTrait;

/**
 * The extension used as a fallback when the requested one doesn't exist.
 *
 * @MarkdownExtension(
 *   id = "_missing_extension",
 *   label = @Translation("Missing Extension"),
 *   requirementViolations = { @Translation("Missing Extension") },
 * )
 *
 * @property \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition
 * @method \Drupal\markdown\Annotation\InstallablePlugin getPluginDefinition()
 */
class MissingExtension extends InstallablePluginBase implements ExtensionInterface {

  use EnabledPluginTrait;
  use ParserAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isBundled() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiredBy() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function requires() {
    return [];
  }

}
