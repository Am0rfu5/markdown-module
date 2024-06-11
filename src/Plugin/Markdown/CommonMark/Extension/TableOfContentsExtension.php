<?php

namespace Drupal\markdown\Plugin\Markdown\CommonMark\Extension;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\markdown\Plugin\Markdown\CommonMark\BaseExtension;
use Drupal\markdown\Plugin\Markdown\SettingsInterface;
use Drupal\markdown\Traits\FeatureDetectionTrait;
use Drupal\markdown\Traits\SettingsTrait;

/**
 * @MarkdownExtension(
 *   id = "commonmark-table-of-contents",
 *   label = @Translation("Table Of Contents"),
 *   description = @Translation("Automatically inserts a table of contents into your document with links to the various headings."),
 *   libraries = {
 *     @ComposerPackage(
 *       id = "league/commonmark",
 *       object = "\League\CommonMark\Extension\TableOfContents\TableOfContentsExtension",
 *       customLabel = "commonmark-table-of-contents",
 *       url = "https://commonmark.thephpleague.com/extensions/table-of-contents/",
 *       requirements = {
 *          @InstallableRequirement(
 *             id = "parser:commonmark",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *          @InstallableRequirement(
 *             id = "extension:commonmark-heading-permalink",
 *             callback = "::getVersion",
 *             constraints = {"Version" = "^1.3 || ^2.0"},
 *          ),
 *       },
 *     ),
 *   },
 * )
 */
class TableOfContentsExtension extends BaseExtension implements PluginFormInterface, SettingsInterface {

  use FeatureDetectionTrait;
  use SettingsTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */

    $settings = [
      'html_class' => 'table-of-contents',
      'max_heading_level' => 6,
      'min_heading_level' => 1,
      'normalize' => 'relative',
      'position' => 'top',
      'style' => 'bullet',
    ];

    // Support placeholder feature if it exists (1.5.0+).
    // @see https://github.com/thephpleague/commonmark/pull/466
    if (static::featureExists('placeholder')) {
      $settings['position'] = 'placeholder';
      $settings['placeholder'] = '[TOC]';
    }

    return $settings;
  }

  /**
   * Feature callback for whether TableOfContents supports a placeholder.
   *
   * @return bool
   *   TRUE or FALSE
   */
  protected static function featurePlaceholder() {
    return defined('\\League\\CommonMark\\Extension\\TableOfContents\\TableOfContentsBuilder::POSITION_PLACEHOLDER');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsKey() {
    return 'table_of_contents';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */

    $element += $this->createSettingElement('html_class', [
      '#type' => 'textfield',
      '#title' => $this->t('HTML Class'),
      '#description' => $this->t("Sets the <code>&lt;ul&gt;</code> or <code>&lt;ol&gt;</code> tag's class attribute."),
    ], $form_state);

    $headings = array_combine(range(1, 6), array_map(function ($value) {
      return "h$value";
    }, range(1, 6)));
    $element += $this->createSettingElement('min_heading_level', [
      '#type' => 'select',
      '#title' => $this->t('Minimum Heading Level'),
      '#description' => $this->t('Headings larger than this will be ignored, e.g. if set to <code>h2</code> then <code>h1</code> headings will be ignored.'),
      '#options' => $headings,
    ], $form_state);

    $element += $this->createSettingElement('max_heading_level', [
      '#type' => 'select',
      '#title' => $this->t('Maximum Heading Level'),
      '#description' => $this->t('Headings smaller than this will be ignored, e.g. if set to <code>h5</code> then <code>h6</code> headings will be ignored.'),
      '#options' => $headings,
    ], $form_state);

    $element += $this->createSettingElement('normalize', [
      '#type' => 'select',
      '#title' => $this->t('Normalize'),
      '#description' => $this->t('Strategy used when generating a (potentially-nested) list of headings.'),
      '#options' => [
        'as-is' => $this->t('As Is'),
        'flat' => $this->t('Flat'),
        'relative' => $this->t('Relative'),
      ],
    ], $form_state);

    $positions = [
      'top' => $this->t('Top'),
      'before-headings' => $this->t('Before Headings'),
    ];
    if (static::featureExists('placeholder')) {
      $positions = ['placeholder' => $this->t('Placeholder')] + $positions;
    }

    $element += $this->createSettingElement('position', [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#description' => $this->t('Where to place table of contents.'),
      '#options' => $positions,
    ], $form_state);

    $element += $this->createSettingElement('placeholder', [
      '#access' => static::featureExists('placeholder'),
      '#title' => $this->t('Placeholder'),
      '#type' => 'textfield',
      '#description' => $this->t('The placeholder value that will be replaced with the Table of Contents. Any lines in your document that match this placeholder value will be replaced by the Table of Contents.'),
    ], $form_state);
    $form_state->addElementState($element['placeholder'], 'visible', 'position', ['value' => 'placeholder']);

    $element += $this->createSettingElement('style', [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#description' => $this->t('HTML list style type to use when rendering the table of contents.'),
      '#options' => [
        'bullet' => $this->t('Unordered (Bulleted)'),
        'ordered' => $this->t('Ordered (Numbered)'),
      ],
    ], $form_state);

    return $element;
  }

}
