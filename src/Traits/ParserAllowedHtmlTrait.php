<?php

namespace Drupal\markdown\Traits;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Trait intended to be used by parsers for default allowed HTML.
 */
trait ParserAllowedHtmlTrait {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Global_attributes
      '*' => [
        'aria*' => TRUE,
        'class' => TRUE,
        'id' => TRUE,
        'lang' => TRUE,
        'name' => TRUE,
        'tabindex' => TRUE,
        'title' => TRUE,
      ],
      'a' => [
        'href' => TRUE,
        'hreflang' => TRUE,
      ],
      'abbr' => [],
      'blockquote' => [
        'cite' => TRUE,
      ],
      'b' => [],
      'br' => [],
      'code' => [],
      'div' => [],
      'em' => [],
      'h2' => [],
      'h3' => [],
      'h4' => [],
      'h5' => [],
      'h6' => [],
      'hr' => [],
      'i' => [],
      'img' => [
        'alt' => TRUE,
        'height' => TRUE,
        'src' => TRUE,
        'width' => TRUE,
      ],
      'li' => [],
      'ol' => [
        'start' => TRUE,
        'type' => [
          '1' => TRUE,
          'A' => TRUE,
          'I' => TRUE,
        ],
      ],
      'p' => [],
      'pre' => [],
      'span' => [],
      'strong' => [],
      'ul' => [
        'type' => TRUE,
      ],
    ];
  }

}
