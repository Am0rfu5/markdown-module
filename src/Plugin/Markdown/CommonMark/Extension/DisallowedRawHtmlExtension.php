<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;

/**
 * Disallowed Raw HTML extension.
 *
 * @MarkdownExtension(
 *   id = "commonmark-disallowed-raw-html",
 *   label = @Translation("Disallowed Raw HTML"),
 *   description = @Translation("Automatically filters certain HTML tags when rendering output."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension",
 *       customLabel = "commonmark-disallowed-raw-html",
 *       url = "https://commonmark.thephpleague.com/extensions/disallowed-raw-html/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class DisallowedRawHtmlExtension extends BaseExtension {
}
