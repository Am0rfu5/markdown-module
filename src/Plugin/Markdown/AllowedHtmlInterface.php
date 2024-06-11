<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Theme\ActiveTheme;

/**
 * Interface for Markdown Allowed HTML plugins.
 */
interface AllowedHtmlInterface extends PluginInspectionInterface {

  /**
   * Retrieves the allowed HTML tags.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   The parser associated with this plugin.
   * @param \Drupal\Core\Theme\ActiveTheme $activeTheme
   *   Optional. The active them. This is used as an indicator when in
   *   "render mode".
   *
   * @return array
   *   An associative array of allowed HTML tags.
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL);

}
