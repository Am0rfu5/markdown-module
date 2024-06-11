<?php

namespace Drupal\markdown\Plugin\Markdown\Pecl;

use function CommonMark\Parse;
use function CommonMark\Render\HTML;
use Drupal\Core\Language\LanguageInterface;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\BaseParser;
use Drupal\markdown\Traits\ParserAllowedHtmlTrait;

/**
 * @MarkdownAllowedHtml(
 *   id = "commonmark-pecl",
 * )
 * @MarkdownParser(
 *   id = "commonmark-pecl",
 *   label = @Translation("CommonMark PECL"),
 *   description = @Translation("CommonMark PECL extension using libcmark."),
 *   weight = 10,
 *   libraries = {
 *     @PeclExtension(
 *       id = "ext-cmark",
 *       object = "\CommonMark\Parser",
 *     ),
 *   }
 * )
 */
class Cmark extends BaseParser implements AllowedHtmlInterface {

  use ParserAllowedHtmlTrait;

  /**
   * {@inheritdoc}
   */
  protected function convertToHtml($markdown, LanguageInterface $language = NULL) {
    try {
      if (is_string($markdown)) {
        // NOTE: these are functions, not classes.
        $node = Parse($markdown);
        return HTML($node);
      }
    }
    catch (\Exception $e) {
      // Intentionally left blank.
    }
    return '';
  }

}
