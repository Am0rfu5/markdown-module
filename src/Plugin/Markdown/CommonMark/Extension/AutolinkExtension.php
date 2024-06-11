<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;

/**
 * Autolink extension.
 *
 * @MarkdownExtension(
 *   id = "commonmark-autolink",
 *   label = @Translation("Autolink"),
 *   description = @Translation("Automatically links URLs and email addresses even when the CommonMark <code>&lt;...&gt;</code> autolink syntax is not used."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\Autolink\AutolinkExtension",
 *       customLabel = "commonmark-autolink",
 *       url = "https://commonmark.thephpleague.com/extensions/autolinks/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *     @ComposerPackage(
 *       id = "league/commonmark-ext-autolink",
 *       deprecated = @Translation("Support for this library was deprecated in markdown:8.x-2.0 and will be removed from markdown:3.0.0."),
 *       object = "\League\CommonMark\Ext\Autolink\AutolinkExtension",
 *       url = "https://github.com/thephpleague/commonmark-ext-autolink",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = ">=0.18.1 <1.0.0 || ^1.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class AutolinkExtension extends BaseExtension {
}
