<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;

/**
 * Attributes extension.
 *
 * @MarkdownExtension(
 *   id = "commonmark-attributes",
 *   label = @Translation("Attributes"),
 *   description = @Translation("Adds a syntax to define attributes on the various HTML elements in markdown’s output."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\Attributes\AttributesExtension",
 *       customLabel = "commonmark-attributes",
 *       url = "https://commonmark.thephpleague.com/extensions/attributes/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.5 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "webuni/commonmark-attributes-extension",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\Webuni\CommonMark\AttributesExtension\AttributesExtension",
 *       url = "https://github.com/webuni/commonmark-attributes-extension",
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
class AttributesExtension extends BaseExtension {
}
