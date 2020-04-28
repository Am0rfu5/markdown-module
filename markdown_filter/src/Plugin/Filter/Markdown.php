<?php

namespace Drupal\markdown_filter\Plugin\Filter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Url;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\markdown\Form\MarkdownSettingsForm;
use Drupal\markdown\MarkdownSettings;

/**
 * Provides a filter for Markdown.
 *
 * @Filter(
 *   id = "markdown",
 *   title = @Translation("Markdown"),
 *   description = @Translation("Allows content to be submitted using Markdown, a simple plain-text syntax that is filtered into valid HTML."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   weight = -15,
 * )
 */
class Markdown extends FilterBase implements MarkdownFilterInterface {

  /**
   * The Markdown Settings for this filter.
   *
   * @var \Drupal\markdown\MarkdownSettingsInterface
   */
  protected $markdownSettings;

  /**
   * The Markdown parser as set by the filter.
   *
   * @var \Drupal\markdown\Plugin\Markdown\MarkdownParserInterface
   */
  protected $parser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->markdownSettings = MarkdownSettings::load("filter_settings.{$this->pluginId}", $this->settings);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($name, $default = NULL) {
    return $this->markdownSettings->getParserSetting($name, $default);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->markdownSettings->getParserSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getParser() {
    if (!isset($this->parser)) {
      // Filter is using a specific parser/configuration.
      if ($parserId = $this->markdownSettings->getParserId(FALSE)) {
        $this->parser = $this->markdownSettings->getParser();
      }
      // Filter is using global parser.
      else {
        $this->parser = \Drupal\markdown\Markdown::create()->getParser();
      }
    }
    return $this->parser;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return !!$this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Sanitize parser values.
    if (!empty($configuration['settings']['parser'])) {
      $configuration['settings'] = array_merge($configuration['settings'], MarkdownSettingsForm::normalizeConfigValues($configuration['settings']));
    }
    return parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $element, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Render\ElementInfoManagerInterface $elementInfo */
    $elementInfo = \Drupal::service('plugin.manager.element_info');
    $process = $elementInfo->getInfoProperty('details', '#process', []);
    $process[] = [$this, 'processSettingsForm'];
    $element['#process'] = $process;
    return $element;
  }

  /**
   * Process callback for constructing markdown settings for this filter.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form, passed by reference.
   *
   * @return array
   *   The processed element.
   */
  public function processSettingsForm(array $element, FormStateInterface $form_state, array &$complete_form) {
    // Disable form cache as we're using sub-forms and AJAX with the
    // markdown settings form and attempting to cache it causes a fatal
    // due to the database getting serialized somewhere.
    // @todo figure out what's going on here?
    $form_state->disableCache();

    // Create a subform state.
    $subform_state = SubformState::createForSubform($element, $complete_form, $form_state);
    $settingsForm = MarkdownSettingsForm::create();

    // Determine the parser identifier.
    $parserId = $subform_state->getValue(['parser', 'id'], $this->markdownSettings->getParserId());
    $settingsForm->setParserId($parserId);

    // If there's no default parser, then it's using the site-wide parser.
    // Extract the configuration for that from the settings form.
    if (!$parserId) {
      $configuration = $settingsForm->getParserConfiguration();
    }
    // Otherwise, use the settings for selected parser in this filter.
    else {
      $configuration = $this->markdownSettings->getParserConfiguration();
    }

    // Set the parser configuration.
    $settingsForm->setParserConfiguration($configuration);

    // Build the settings form.
    return $settingsForm->buildSettings($element, $subform_state);

  }

  public static function processTextFormat(&$element, FormStateInterface $form_state, &$complete_form) {
    $formats = filter_formats();
    /** @var \Drupal\filter\FilterFormatInterface $format */
    $format = isset($formats[$element['#format']]) ? $formats[$element['#format']] : FALSE;
    if ($format && ($markdown = $format->filters('markdown')) && $markdown instanceof MarkdownFilterInterface && $markdown->isEnabled()) {
      $element['format']['help']['about'] = [
        '#type' => 'link',
        '#title' => t('@iconStyling with Markdown is supported', [
          // Shamelessly copied from GitHub's Octicon icon set.
          // @todo Revisit this?
          // @see https://github.com/primer/octicons/blob/master/lib/svg/markdown.svg
          '@icon' => new FormattableMarkup('<svg class="octicon octicon-markdown v-align-bottom" viewBox="0 0 16 16" version="1.1" width="16" height="16" aria-hidden="true" style="fill: currentColor;margin-right: 5px;vertical-align: text-bottom;"><path fill-rule="evenodd" d="M14.85 3H1.15C.52 3 0 3.52 0 4.15v7.69C0 12.48.52 13 1.15 13h13.69c.64 0 1.15-.52 1.15-1.15v-7.7C16 3.52 15.48 3 14.85 3zM9 11H7V8L5.5 9.92 4 8v3H2V5h2l1.5 2L7 5h2v6zm2.99.5L9.5 8H11V5h2v3h1.5l-2.51 3.5z"></path></svg>', []),
        ]),
        '#url' => Url::fromRoute('filter.tips_all')->setOptions([
          'attributes' => [
            'class' => ['markdown'],
            'target' => '_blank',
        ]]),
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Only use the parser to process the text if it's not empty.
    if (!empty($text)) {
      $language = \Drupal::languageManager()->getLanguage($langcode);
      $markdown = $this->getParser()->parse($text, $language);

      // Enable all tags, let other filters (i.e. filter_html) handle that.
      $text = $markdown->setAllowedTags(TRUE)->getHtml();
    }
    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    $parser = $this->getParser();

    // On the "short" tips, just show and render the summary, if any.
    if (!$long) {
      $summary = $parser->getSummary();
      if (!$summary) {
        return NULL;
      }
      return (string) \Drupal::service('renderer')->render($summary);
    }


    // On the long tips, the render array must be retrieved as a "form" due to
    // the fact that vertical tabs require form processing to work properly.
    $formBuilder = \Drupal::formBuilder();
    $formState = (new FormState())->addBuildInfo('args', [$long, $parser]);
    $form = $formBuilder->buildForm('\Drupal\markdown_filter\Form\MarkdownFilterTipsForm', $formState);

    // Since this is essentially "hacking" the FAPI and not an actual "form",
    // just extract the relevant child elements from the "form" and render it.
    $tips = [];
    foreach (['help', 'tips', 'guides', 'allowed_tags'] as $child) {
      if (isset($form[$child])) {
        $tips[] = $form[$child];
      }
    }

    return \Drupal::service('renderer')->render($tips[1]);
  }

}
