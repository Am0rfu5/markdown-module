<?php

namespace Drupal\markdown\Plugin\Markdown;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\markdown\PluginManager\ParserManager;
use Drupal\markdown\Render\ParsedMarkdown;
use Drupal\markdown\Traits\EnabledPluginTrait;
use Drupal\markdown\Traits\FilterAwareTrait;
use Drupal\markdown\Traits\SettingsTrait;
use Drupal\markdown\Util\FilterAwareInterface;
use Drupal\markdown\Util\FilterFormatAwareInterface;
use Drupal\markdown\Util\FilterHtml;
use Drupal\markdown\Util\ParserAwareInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Base class form Markdown Parser instances.
 *
 * @property \Drupal\markdown\Annotation\MarkdownParser $pluginDefinition
 * @method \Drupal\markdown\Annotation\MarkdownParser getPluginDefinition()
 */
abstract class BaseParser extends InstallablePluginBase implements FilterAwareInterface, ParserInterface, PluginFormInterface {

  use EnabledPluginTrait;
  use FilterAwareTrait;
  use RefinableCacheableDependencyTrait;
  use SettingsTrait;

  /**
   * {@inheritdoc}
   */
  protected $enabled = TRUE;

  /**
   * Validates parser settings.
   *
   * @param array $settings
   *   The parser settings to validate.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validation execution context.
   */
  public static function validateSettings(array $settings, ExecutionContextInterface $context) {
    try {
      $object = $context->getObject();
      $parent = $object instanceof Mapping ? $object->getParent() : NULL;
      $parserId = $parent instanceof Mapping && ($id = $parent->get('id')) ? $id->getValue() : NULL;
      $parserManager = ParserManager::create();

      if (!$parserId || !$parserManager->hasDefinition($parserId)) {
        throw new \RuntimeException(sprintf('Unknown markdown parser: "%s"', $parserId));
      }

      $parser = $parserManager->createInstance($parserId);

      // Immediately return if parser doesn't have any settings.
      if (!($parser instanceof SettingsInterface)) {
        return;
      }

      $defaultSettings = $parser::defaultSettings($parser->getPluginDefinition());
      $unknownSettings = array_keys(array_diff_key($settings, $defaultSettings));
      if ($unknownSettings) {
        throw new \RuntimeException(sprintf('Unknown parser settings: %s', implode(', ', $unknownSettings)));
      }
    }
    catch (\RuntimeException $exception) {
      $context->addViolation($exception->getMessage());
    }
  }

  /**
   * Converts Markdown into HTML.
   *
   * Note: this method is not guaranteed to be safe from XSS attacks. This
   * returns the raw output from the parser itself.
   *
   * If you need to render this output you should use the
   * \Drupal\markdown\Plugin\Markdown\MarkdownParserInterface::parse()
   * method instead.
   *
   * @param string $markdown
   *   The markdown string to convert.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Optional. The language of the text that is being converted.
   *
   * @return string
   *   The raw parsed HTML returned from the parser.
   *
   * @see \Drupal\markdown\Render\ParsedMarkdownInterface
   * @see \Drupal\markdown\Plugin\Markdown\ParserInterface::parse()
   *
   * @internal
   */
  abstract protected function convertToHtml($markdown, LanguageInterface $language = NULL);

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0.
   *   Use RenderStrategyInterface::getCustomAllowedHtml instead.
   * @see https://www.drupal.org/project/markdown/issues/3142418
   */
  public function getAllowedHtml() {
    return $this->getCustomAllowedHtml();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedHtmlPlugins() {
    return $this->config()->get('render_strategy.plugins') ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();

    $configuration['render_strategy'] = [
      'type' => $this->getRenderStrategy(),
      'custom_allowed_html' => $this->getCustomAllowedHtml(),
      'plugins' => $this->getAllowedHtmlPlugins(),
    ];
    ksort($configuration['render_strategy']['plugins']);

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationSortOrder() {
    return [
      'render_strategy' => -11,
    ] + parent::getConfigurationSortOrder();
  }

  /**
   * Builds context around a markdown parser's hierarchy filter format chain.
   *
   * @param array $context
   *   Additional context to pass.
   *
   * @return array
   *   The context, including references to various parser and filter instances.
   */
  protected function getContext(array $context = []) {
    $parser = NULL;
    if ($this instanceof ParserAwareInterface) {
      $parser = $this->getParser();
    }
    elseif ($this instanceof ParserInterface) {
      $parser = $this;
    }

    $filter = NULL;
    if ($this instanceof FilterAwareInterface) {
      $filter = $this->getFilter();
    }
    elseif ($parser instanceof FilterAwareInterface) {
      $filter = $parser->getFilter();
    }
    elseif ($this instanceof FilterInterface) {
      $filter = $this;
    }

    $format = NULL;
    if ($this instanceof FilterFormatAwareInterface) {
      $format = $this->getFilterFormat();
    }
    elseif ($parser instanceof FilterFormatAwareInterface) {
      $format = $parser->getFilterFormat();
    }
    elseif ($filter instanceof FilterFormatAwareInterface) {
      $format = $filter->getFilterFormat();
    }
    elseif ($this instanceof FilterFormat) {
      $format = $this;
    }

    return [
      'parser' => $parser,
      'filter' => $filter,
      'format' => $format,
    ] + $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomAllowedHtml() {
    return $this->config()->get('render_strategy.custom_allowed_html');
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderStrategy() {
    return $this->config()->get('render_strategy.type') ?: static::FILTER_OUTPUT;
  }

  /**
   * {@inheritdoc}
   */
  public function parse($markdown, LanguageInterface $language = NULL) {
    $moduleHandler = \Drupal::moduleHandler();

    $renderStrategy = $this->getRenderStrategy();
    if ($renderStrategy === static::ESCAPE_INPUT) {
      $markdown = Html::escape($markdown);
    }
    elseif ($renderStrategy === static::STRIP_INPUT) {
      $markdown = strip_tags($markdown);
    }

    // Invoke hook_markdown_alter().
    $context = $this->getContext(['language' => $language]);
    $moduleHandler->alter('markdown', $markdown, $context);

    // Convert markdown to HTML.
    $html = $this->convertToHtml($markdown, $language);

    // Invoke hook_markdown_html_alter().
    $context['markdown'] = $markdown;
    $moduleHandler->alter('markdown_html', $html, $context);

    // Filter all HTML output.
    if ($renderStrategy === static::FILTER_OUTPUT) {
      $html = (string) FilterHtml::fromParser($this)->process($html, $language ? $language->getId() : NULL);
    }

    return ParsedMarkdown::create($markdown, $html, $language)->addCacheableDependency($this);
  }

  /**
   * A description explaining why a setting is disabled due to render strategy.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered description.
   */
  protected function renderStrategyDisabledSetting(FormStateInterface $form_state) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $markdownParents = $form_state->get('markdownSubformParents');
    $parents = array_merge($markdownParents, ['render_strategy', 'type']);
    $selector = ':input[name="' . array_shift($parents) . '[' . implode('][', $parents) . ']"]';

    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    return new FormattableMarkup('@disabled@warning', [
      '@disabled' => $form_state->conditionalElement([
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'form-item--description',
            'is-disabled',
          ],
        ],
        [
          '#markup' => $this->moreInfo($this->t('<strong>NOTE:</strong> This setting is disabled when a render strategy is being used.'), RenderStrategyInterface::DOCUMENTATION_URL),
        ],
      ], 'visible', $selector, ['!value' => static::NONE]),
      '@warning' => $form_state->conditionalElement([
        '#type' => 'container',
        '#theme_wrappers' => ['container__markdown_disabled_setting__render_strategy__warning'],
        '#attributes' => [
          'class' => [
            'form-item__error-message',
            'form-item--error-message',
          ],
        ],
        [
          '#markup' => $this->moreInfo($this->t('<strong>WARNING:</strong> This setting does not guarantee protection against malicious JavaScript from being injected. It is recommended to use the "Filter Output" render strategy.'), RenderStrategyInterface::DOCUMENTATION_URL),
        ],
      ], 'visible', $selector, ['value' => static::NONE]),
    ]);
  }

  /**
   * Adds a conditional state for a setting element based on render strategy.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $element
   *   The element to modify, passed by reference.
   * @param string|string[] $state
   *   Optional. Additional states to trigger when setting is disabled, e.g.
   *   unchecked, etc.
   * @param array $conditions
   *   The conditions for which to trigger the state(s).
   */
  protected function renderStrategyDisabledSettingState(FormStateInterface $form_state, array &$element, $state = 'disabled', array $conditions = ['!value' => self::NONE]) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $markdownParents = $form_state->get('markdownSubformParents');
    $parents = array_merge($markdownParents, ['render_strategy', 'type']);
    $selector = ':input[name="' . array_shift($parents) . '[' . implode('][', $parents) . ']"]';

    $states = (array) $state;
    foreach ($states as $state) {
      $form_state->addElementState($element, $state, $selector, $conditions);
    }

    // Add a conditional description explaining why the setting is disabled.
    if (!isset($element['#description'])) {
      $element['#description'] = $this->renderStrategyDisabledSetting($form_state);
    }
    else {
      $element['#description'] = new FormattableMarkup('@description @renderStrategyDisabledSetting', [
        '@description' => $element['#description'],
        '@renderStrategyDisabledSetting' => $this->renderStrategyDisabledSetting($form_state),
      ]);
    }
  }

}
