<?php

namespace Drupal\markdown\Plugin\Markdown\Parsedown;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Support for Parsedown Extra by Emanuil Rusev.
 *
 * @MarkdownAllowedHtml(
 *   id = "parsedown-extra",
 * )
 * @MarkdownParser(
 *   id = "parsedown-extra",
 *   label = @Translation("Parsedown Extra"),
 *   description = @Translation("Parser for Markdown with extra functionality."),
 *   weight = 20,
 *   libraries = {
 *     @ComposerPackage(
 *       id = "erusev/parsedown-extra",
 *       object = "\ParsedownExtra",
 *       url = "https://github.com/erusev/parsedown-extra",
 *     ),
 *   }
 * )
 * @method \ParsedownExtra getParsedown()
 */
class ParsedownExtra extends Parsedown {

  /**
   * {@inheritdoc}
   */
  protected static $parsedownClass = '\\ParsedownExtra';

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      'a' => [
        'rev' => TRUE,
      ],
      'abbr' => [],
      'dd' => [],
      'dl' => [],
      'dt' => [],
      'sup' => [],
    ];
  }

}
