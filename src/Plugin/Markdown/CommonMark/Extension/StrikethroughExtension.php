<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use CommonMarkExt\Strikethrough\StrikethroughRenderer;
use CommonMarkExt\Strikethrough\StrikethroughParser;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Strikethrough extension.
 *
 * @MarkdownAllowedHtml(
 *   id = "commonmark-strikethrough",
 * )
 * @MarkdownExtension(
 *   id = "commonmark-strikethrough",
 *   label = @Translation("Strikethrough"),
 *   description = @Translation("Adds support for GFM-style strikethrough syntax. It allows users to use <code>~~</code> in order to indicate text that should be rendered within <code>&lt;del&gt;</code> tags."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\Strikethrough\StrikethroughExtension",
 *       customLabel = "commonmark-strikethrough",
 *       url = "https://commonmark.thephpleague.com/extensions/strikethrough/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "league/commonmark-ext-strikethrough",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\League\CommonMark\Ext\Strikethrough\StrikethroughExtension",
 *       url = "https://github.com/thephpleague/commonmark-ext-strikethrough",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.19 <1.0.0 || ^1.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "uafrica/commonmark-ext",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\CommonMarkExt\Strikethrough\StrikethroughParser",
 *       url = "https://github.com/uafrica/commonmark-ext",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.10 <0.19.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class StrikethroughExtension extends BaseExtension implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      'del' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function register($environment) {
    // Support manual uafrica/commonmark-ext implementation.
    if (class_exists('\\CommonMarkExt\\Strikethrough\\StrikethroughParser')) {
      $environment->addInlineParser(new StrikethroughParser());
      if (class_exists('\\CommonMarkExt\\Strikethrough\\StrikethroughRenderer')) {
        $environment->addInlineRenderer(new StrikethroughRenderer());
      }
      return;
    }
    parent::register($environment);
  }

}
