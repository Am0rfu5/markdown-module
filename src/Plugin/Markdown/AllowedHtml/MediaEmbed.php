<?php

namespace Drupal\markdown\Plugin\Markdown\AllowedHtml;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Media module support for "media_embed" filter.
 *
 * @MarkdownAllowedHtml(
 *   id = "media_embed",
 *   description = @Translation("Adds support for the <code>&lt;drupal-media&gt;</code> tag."),
 *   provider = "media",
 *   requirements = {
 *     @InstallableRequirement(
 *       id = "filter:media_embed",
 *     ),
 *   },
 * )
 */
class MediaEmbed extends PluginBase implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      'drupal-media' => [
        'data-align' => TRUE,
        'data-caption' => TRUE,
        'data-entity-type' => TRUE,
        'data-entity-uuid' => TRUE,
      ],
    ];
  }

}
