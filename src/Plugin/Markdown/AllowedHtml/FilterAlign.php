<?php

namespace Drupal\markdown\Plugin\Markdown\AllowedHtml;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Filter module support for "filter_align" filter.
 *
 * @MarkdownAllowedHtml(
 *   id = "filter_align",
 *   description = @Translation("Adds support for the <code>data-align</code> attribute."),
 *   provider = "filter",
 *   requirements = {
 *     @InstallableRequirement(
 *       id = "filter:filter_align",
 *     ),
 *   },
 * )
 */
class FilterAlign extends PluginBase implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      '*' => [
        'data-align' => TRUE,
      ],
    ];
  }

}
