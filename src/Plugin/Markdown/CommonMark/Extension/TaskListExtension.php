<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\ParserInterface;

/**
 * Task List extension.
 *
 * @MarkdownAllowedHtml(
 *   id = "commonmark-task-list",
 * )
 * @MarkdownExtension(
 *   id = "commonmark-task-list",
 *   label = @Translation("Task List"),
 *   description = @Translation("Adds support for GFM-style task lists."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\TaskList\TaskListExtension",
 *       customLabel = "commonmark-task-list",
 *       url = "https://commonmark.thephpleague.com/extensions/task-lists/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "league/commonmark-ext-task-list",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\League\CommonMark\Ext\TaskList\TaskListExtension",
 *       url = "https://github.com/thephpleague/commonmark-ext-task-list",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.19 <1.0.0 || ^1.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class TaskListExtension extends BaseExtension implements AllowedHtmlInterface {

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    return [
      'input' => [
        'checked' => TRUE,
        'disabled' => TRUE,
        'type' => 'checkbox',
      ],
    ];
  }

}
