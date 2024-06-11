<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark;

/**
 * Support for CommonMark GFM by The League of Extraordinary Packages.
 *
 * @MarkdownParser(
 *   id = "commonmark-gfm",
 *   label = @Translation("CommonMark GFM"),
 *   description = @Translation("A robust, highly-extensible Markdown parser for PHP based on the Github-Flavored Markdown specification."),
 *   extensionInterfaces = {
 *     "\Drupal\markdown\Plugin\Markdown\CommonMark\ExtensionInterface",
 *   },
 *   bundledExtensions = {
 *     "commonmark-autolink",
 *     "commonmark-disallowed-raw-html",
 *     "commonmark-strikethrough",
 *     "commonmark-table",
 *     "commonmark-task-list",
 *   },
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\GithubFlavoredMarkdownConverter",
 *       customLabel = "commonmark-gfm",
 *       url = "https://commonmark.thephpleague.com/extensions/github-flavored-markdown/",
 *       requirements = {
 *         @InstallableRequirement(
 *           constraints = {"Version" = "^1.3 || ^2.0"}
 *         ),
 *       },
 *     ),
 *   },
 * )
 */
class CommonMarkGfm extends CommonMark {

  /**
   * {@inheritdoc}
   */
  public static function converterClass() {
    if (!isset(static::$converterClass)) {
      static::$converterClass = '\\League\\CommonMark\\GithubFlavoredMarkdownConverter';
    }
    return static::$converterClass;
  }

  /**
   * {@inheritdoc}
   */
  protected function createEnvironment() {
    /** @var \League\CommonMark\Environment|\League\CommonMark\Environment\Environment $environment */
    $environment = static::environmentClass();
    return $environment::createGFMEnvironment();
  }

}
