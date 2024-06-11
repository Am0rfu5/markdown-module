<?php

namespace Drupal\markdown\Plugin\Markdown\AllowedHtml;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Filter module support for "filter_caption" filter.
 *
 * @MarkdownAllowedHtml(
 *   id = "filter_caption",
 *   description = @Translation("Adds support for the <code>data-caption</code> attribute."),
 *   provider = "filter",
 *   requirements = {
 *     @InstallableRequirement(
 *       id = "filter:filter_caption",
 *     ),
 *   },
 * )
 */
class FilterCaption extends PluginBase implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      '*' => [
        'data-caption' => TRUE,
      ],
    ];
  }

}
