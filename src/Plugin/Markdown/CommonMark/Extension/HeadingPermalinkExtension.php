<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkRenderer;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\markdown\Plugin\Markdown\AllowedHtmlInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\ExtensibleParserInterface;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\SettingsTrait;
use Drupal\markdown\Util\FilterHtml;

/**
 * @MarkdownAllowedHtml(
 *   id = "commonmark-heading-permalink",
 * )
 * @MarkdownExtension(
 *   id = "commonmark-heading-permalink",
 *   label = @Translation("Heading Permalink"),
 *   description = @Translation("Makes all heading elements (&lt;h1&gt;, &lt;h2&gt;, etc) linkable so users can quickly grab a link to that specific part of the document."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension",
 *       customLabel = "commonmark-heading-permalink",
 *       url = "https://commonmark.thephpleague.com/extensions/heading-permalinks/",
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
class HeadingPermalinkExtension extends BaseExtension implements AllowedHtmlInterface, PluginFormInterface, SettingsInterface {

  use SettingsTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */

    $innerContents = '';
    if (defined('\\League\\CommonMark\\Extension\\HeadingPermalink\\HeadingPermalinkRenderer::DEFAULT_INNER_CONTENTS')) {
      /* @noinspection PhpFullyQualifiedNameUsageInspection */
      $innerContents = HeadingPermalinkRenderer::DEFAULT_INNER_CONTENTS; // phpcs:ignore
    }

    return [
      'html_class' => 'heading-permalink',
      'id_prefix' => 'user-content',
      'inner_contents' => $innerContents,
      'insert' => 'before',
      'title' => 'Permalink',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function allowedHtmlTags(ParserInterface $parser, ActiveTheme $activeTheme = NULL) {
    $tags = [];
    if ($parser instanceof ExtensibleParserInterface && ($extension = $parser->extension($this->getPluginId())) && $extension instanceof SettingsInterface) {
      $tags = FilterHtml::tagsFromHtml($extension->getSetting('inner_contents'));
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */

    $element += $this->createSettingElement('html_class', [
      '#type' => 'textfield',
      '#title' => $this->t('HTML Class'),
      '#description' => $this->t("The value of this nested configuration option should be a <code>string</code> that you want set as the <code>&lt;a&gt;</code> tag's class attribute."),
    ], $form_state);

    $element += $this->createSettingElement('id_prefix', [
      '#type' => 'textfield',
      '#title' => $this->t('ID Prefix'),
      '#description' => $this->t("This should be a <code>string</code> you want prepended to HTML IDs. This prevents generating HTML ID attributes which might conflict with others in your stylesheet. A dash separator (-) will be added between the prefix and the ID. You can instead set this to an empty string ('') if you don’t want a prefix."),
    ], $form_state);

    $element += $this->createSettingElement('inner_contents', [
      '#type' => 'textarea',
      '#title' => $this->t('Inner Contents'),
      '#description' => $this->t("This controls the HTML you want to appear inside of the generated <code>&lt;a&gt;</code> tag. Usually this would be something you'd style as some kind of link icon. By default, an embedded <a href=\":octicon-link\" target=\"_blank\">Octicon link SVG,</a> is provided, but you can replace this with any custom HTML you wish.<br>NOTE: The HTML tags and attributes saved here will be dynamically allowed using the corresponding Allowed HTML Plugin in \"Render Strategy\". This means that whatever is added here has the potential to open up security vulnerabilities.<br>If unsure or you wish for maximum security, use a non-HTML based placeholder (e.g. <code>{{ commonmark_heading_permalink_inner_contents }}</code>) value that you can replace post parsing in <code>hook_markdown_html_alter()</code>.", [
        ':octicon-link' => 'https://primer.style/octicons/link',
      ]),
    ], $form_state);

    $element += $this->createSettingElement('insert', [
      '#type' => 'select',
      '#title' => $this->t('Insert'),
      '#description' => $this->t("This controls whether the anchor is added to the beginning of the <code>&lt;h1&gt;</code>, <code>&lt;h2&gt;</code> etc. tag or to the end."),
      '#options' => [
        'after' => $this->t('After'),
        'before' => $this->t('Before'),
      ],
    ], $form_state);

    $element += $this->createSettingElement('title', [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t("This option sets the title attribute on the <code>&lt;a&gt;</code> tag."),
    ], $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsKey() {
    return 'heading_permalink';
  }

}
