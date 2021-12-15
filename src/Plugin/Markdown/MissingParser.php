<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Render\ParsedMarkdown;
use Drupal\markdown\Traits\EnabledPluginTrait;
use Drupal\markdown\Traits\ParserAllowedHtmlTrait;
use Drupal\markdown\Traits\SettingsTrait;
use Drupal\markdown\Util\FilterHtml;

/**
 * The parser used as a fallback when the requested one doesn't exist.
 *
 * @MarkdownAllowedHtml(
 *   id = "_missing_parser",
 * )
 * @MarkdownParser(
 *   id = "_missing_parser",
 *   label = @Translation("Missing Parser"),
 *   requirementViolations = { @Translation("Missing Parser") },
 * )
 *
 * @property \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition
 * @method \Drupal\markdown\Annotation\InstallablePlugin getPluginDefinition()
 */
class MissingParser extends InstallablePluginBase implements AllowedHtmlInterface, ParserInterface {

  use EnabledPluginTrait;
  use ParserAllowedHtmlTrait;
  use RefinableCacheableDependencyTrait;
  use SettingsTrait;

  /**
   * {@inheritdoc}
   */
  protected function convertToHtml($markdown, LanguageInterface $language = NULL) {
    return $markdown;
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   Use RenderStrategyInterface::getCustomAllowedHtml instead.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  public function getAllowedHtml() {
    return $this->getCustomAllowedHtml();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedHtmlPlugins(ActiveTheme $activeTheme = NULL) {
    return $this->config()->get('render_strategy.plugins') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    // Only add default values as existing configuration may have already
    // already been passed. This may be due to a plugin rename/config changes.
    $configuration = $this->configuration ?: [];
    $configuration += [
      'id' => $this->getPluginId(),
      'render_strategy' => [],
    ];
    $configuration['render_strategy'] += [
      'type' => $this->getRenderStrategy(),
      'custom_allowed_html' => $this->getCustomAllowedHtml(),
      'plugins' => $this->getAllowedHtmlPlugins(),
    ];
    ksort($configuration['render_strategy']['plugins']);
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomAllowedHtml() {
    return $this->config()->get('render_strategy.custom_allowed_html');
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderStrategy() {
    $type = $this->config()->get('render_strategy.type');
    return isset($type) ? $type : static::FILTER_OUTPUT;
  }

  /**
   * {@inheritdoc}
   */
  public function parse($markdown, LanguageInterface $language = NULL) {
    $html = (string) FilterHtml::fromParser($this)->process($markdown, $language ? $language->getId() : NULL);
    return ParsedMarkdown::create($markdown, $html, $language);
  }

}
