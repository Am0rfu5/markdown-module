<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Table extension.
 *
 * @MarkdownAllowedHtml(
 *   id = "commonmark-table",
 * )
 * @MarkdownExtension(
 *   id = "commonmark-table",
 *   label = @Translation("Table"),
 *   description = @Translation("Adds the ability to create tables in CommonMark documents."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\Table\TableExtension",
 *       customLabel = "commonmark-table",
 *       url = "https://commonmark.thephpleague.com/extensions/tables/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "league/commonmark-ext-table",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\League\CommonMark\Ext\Table\TableExtension",
 *       url = "https://github.com/thephpleague/commonmark-ext-table",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.19.3 <1.0.0 || ^1.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "webuni/commonmark-table-extension",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\Webuni\CommonMark\TableExtension\TableExtension",
 *       url = "https://github.com/webuni/commonmark-table-extension",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.9 <1.0.0 || ^1.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class TableExtension extends BaseExtension implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      'caption' => [],
      'col' => [
        'span' => TRUE,
      ],
      'colgroup' => [
        'span' => TRUE,
      ],
      'table' => [],
      'tbody' => [],
      'td' => [
        'colspan' => TRUE,
        'headers' => TRUE,
        'rowspan' => TRUE,
      ],
      'tfoot' => [],
      'th' => [
        'abbr' => TRUE,
        'colspan' => TRUE,
        'headers' => TRUE,
        'rowspan' => TRUE,
        'scope' => TRUE,
      ],
      'thead' => [],
      'tr' => [],
    ];
  }

}
