<?php

use Drupal\markdown\Plugin\Markdown\PhpMarkdown\PhpMarkdownExtra;
use Drupal\Component\Utility\Crypt;
/**
 * @file
 * Hooks and alters provided by the Markdown module.
 */

/**
 * Ignore inspections.
 *
 * @noinspection PhpUnused
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
 * @noinspection PhpUndefinedFunctionInspection
 * @noinspection PhpUnusedParameterInspection
 */

/**
 * Allows modules to alter markdown prior to it being parsed.
 *
 * Note: this may introduce or cause performance issues when attempting to parse
 * a lot of markdown content at the same time. If a parser is extensible, it's
 * highly recommended that you create an extension for that specific parser
 * rather than relying on this Drupal hook. This hook is primarily to assist
 * in dealing with parsers that are not extensible and require manual
 * manipulation of the parser itself.
 *
 * @param string $markdown
 *   The markdown to alter.
 * @param array $context
 *   An associative array of context, containing:
 *   - parser: \Drupal\markdown\Plugin\Markdown\MarkdownParserInterface
 *     The parser that these HTML restrictions belong to.
 *   - filter: \Drupal\filter\Plugin\FilterInterface
 *     Optional. A filter that is associated with the parser, may not be set.
 *   - format: \Drupal\filter\Entity\FilterFormat
 *     Optional. A filter format entity that is associated with the filter,
 *     may not be set.
 *   - language: (LanguageInterface) The language of the markdown, if known.
 */
function hook_markdown_alter(&$markdown, array $context) {
  // Append a company name with an inline stock price widget. It's highly
  // recommended to place your replacements in a callback in case they are
  // computationally expensive. This ensures it's only executed when needed.
  $markdown = preg_replace_callback('/Company Name/', function ($matches) {
    return $matches[0] . ' ' . my_module_inline_stock_widget();
  }, $markdown);
}

/**
 * Allows modules to alter the list of incompatible filters.
 *
 * @param array $compatibleFilters
 *   An associative array of compatible filters, where the key is the filter
 *   identifier and the value is a boolean: TRUE if compatible, FALSE otherwise.
 */
function hook_markdown_compatible_filters_alter(array &$compatibleFilters) {
  // Indicate that a module's filter isn't compatible with the markdown filter.
  $compatibleFilters['my_module_filter'] = FALSE;
}

/**
 * Allows modules to alter the generated HTML after it has been parsed.
 *
 * Note: this may introduce or cause performance issues when attempting to parse
 * a lot of markdown content at the same time. If a parser is extensible, it's
 * highly recommended that you create an extension for that specific parser
 * rather than relying on this Drupal hook. This hook is primarily to assist
 * in dealing with parsers that are not extensible and require manual
 * manipulation of the parser itself.
 *
 * @param string $html
 *   The HTML generated from the parser.
 * @param array $context
 *   An associative array of context, containing:
 *   - parser: \Drupal\markdown\Plugin\Markdown\MarkdownParserInterface
 *     The parser that these HTML restrictions belong to.
 *   - filter: \Drupal\filter\Plugin\FilterInterface
 *     Optional. A filter that is associated with the parser, may not be set.
 *   - format: \Drupal\filter\Entity\FilterFormat
 *     Optional. A filter format entity that is associated with the filter,
 *     may not be set.
 *   - markdown: (string) The markdown that was used to generate the HTML.
 *   - language: (LanguageInterface) The language of the markdown, if known.
 */
function hook_markdown_html_alter(&$html, array $context) {
  // Ignore non PHP Markdown Extra parsers.
  $parser = $context['parser'];
  if (!($parser instanceof PhpMarkdownExtra)) {
    return;
  }

  // Handle omitted footnotes by storing them somewhere. They can then be used
  // elsewhere, like in a custom block that appears in a sidebar region.
  $phpMarkdown = $parser->getPhpMarkdown();
  if ($phpMarkdown->omit_footnotes && $phpMarkdown->footnotes_assembled) {
    // Create a hash based on the contents of the HTML output.
    // This can be used as the lookup identifier to load the footnotes later.
    $hash = Crypt::hashBase64($html);
    \Drupal::keyValue('my_module.markdown.footnotes')->set($hash, $phpMarkdown->footnotes_assembled);
  }
}

/**
 * Performs alterations on markdown parser plugin definitions.
 *
 * @param array $info
 *   An array of markdown parser plugin definitions, as collected by the
 *   plugin annotation discovery mechanism.
 *
 * @see \Drupal\markdown\Annotation\MarkdownParser
 * @see \Drupal\markdown\Plugin\Markdown\BaseParser
 * @see \Drupal\markdown\Plugin\Markdown\ParserInterface
 * @see \Drupal\markdown\PluginManager\ParserManager
 */
function hook_markdown_parser_info_alter(array &$info) {
  // Limit maximum nesting value in CommonMark.
  $info['commonmark']['settings']['max_nesting_level'] = 1000;

  // If a parser provides a setting that requires a callback, something you
  // cannot configure from the UI, you can supply it here. Note: this
  // callback must reference a publicly callable function or static method
  // since it will be cached in the database.
  $info['php-markdown']['settings']['header_id_func'] = '\\Drupal\\my_module\\Markdown::buildHeader';
  $info['php-markdown-extra']['settings']['header_id_func'] = '\\Drupal\\my_module\\Markdown::buildHeader';
}

/**
 * Performs alterations on markdown extension plugin definitions.
 *
 * @param array $info
 *   An array of markdown extension plugin definitions, as collected by the
 *   plugin annotation discovery mechanism.
 *
 * @see \Drupal\markdown\Annotation\MarkdownExtension
 * @see \Drupal\markdown\Plugin\Markdown\BaseExtension
 * @see \Drupal\markdown\Plugin\Markdown\ExtensionInterface
 * @see \Drupal\markdown\PluginManager\ExtensionManager
 */
function hook_markdown_extension_info_alter(array &$info) {
  // Use a custom class for TOC.
  $info['commonmark-table-of-contents']['settings']['html_class'] = 'my-module-toc-class';
}

/**
 * Performs alterations on markdown allowed HTML plugin definitions.
 *
 * @param array $info
 *   An array of markdown allowed HTML plugin definitions, as collected by the
 *   plugin annotation discovery mechanism.
 *
 * @see \Drupal\markdown\Annotation\MarkdownAllowedHtml
 * @see \Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface
 * @see \Drupal\markdown\PluginManager\AllowedHtmlManager
 */
function hook_markdown_allowed_html_info_alter(array &$info) {
  // Remove media embed support, regardless if it's available.
  unset($info['media_embed']);
}
