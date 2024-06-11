<?php

namespace Drupal\markdown\Plugin\Filter;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\markdown\Form\ParserConfigurationForm;
use Drupal\markdown\Form\SubformState;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Drupal\markdown\PluginManager\ParserManagerInterface;
use Drupal\markdown\Traits\FilterFormatAwareTrait;
use Drupal\markdown\Traits\FormTrait;
use Drupal\markdown\Traits\MoreInfoTrait;
use Drupal\markdown\Traits\ParserAwareTrait;
use Drupal\markdown\Util\FilterAwareInterface;
use Drupal\markdown\Util\FilterFormatAwareInterface;
use Drupal\markdown\Util\ParserAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class FilterMarkdown extends FilterBase implements ContainerFactoryPluginInterface, FilterMarkdownInterface, ParserAwareInterface {

  use FilterFormatAwareTrait;
  use FormTrait;
  use MoreInfoTrait;
  use ParserAwareTrait {
    setParser as setParserTrait;
  }

  /**
   * The Element Info Manager service.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfo;

  /**
   * The Markdown Parser Plugin Manager service.
   *
   * @var \Drupal\markdown\PluginManager\ParserManagerInterface
   */
  protected $parserManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ElementInfoManagerInterface $elementInfo, ParserManagerInterface $parserManager) {
    $this->elementInfo = $elementInfo;
    $this->parserManager = $parserManager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.element_info'),
      $container->get('plugin.manager.markdown.parser')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->getParser()->calculateDependencies();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();

    // Ensure any filter format set is added to the configuration. This is
    // needed in the event the filters configuration is cached in the database.
    // @see filter_formats()
    // @see markdown_filter_format_load()
    $filterFormat = $this->getFilterFormat();
    $configuration['filterFormat'] = $filterFormat ? $filterFormat->id() : NULL;

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    // Immediately return the default configuration if filter isn't enabled.
    if (!$this->status) {
      $configuration['id'] = $this->getPluginId();
      $configuration += $this->defaultConfiguration();
      return $configuration;
    }

    $configuration = parent::getConfiguration();

    // Ensure any filter format set is added to the configuration. This is
    // needed in the event the filters configuration is cached in the database.
    // @see filter_formats()
    // @see markdown_filter_format_load()
    $filterFormat = $this->getFilterFormat();
    $configuration['filterFormat'] = $filterFormat ? $filterFormat->id() : NULL;

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getParser() {
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
  public function process($text, $langcode = NULL) {
    // Only use the parser to process the text if it's not empty.
    if (!empty($text)) {
      $language = $langcode ? \Drupal::languageManager()->getLanguage($langcode) : NULL;
      $text = $this->getParser()->parse($text, $language);
    }
    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Normalize any passed filter format. This is needed in the event the
    // filter is being loaded from cached database configuration.
    // @see \Drupal\markdown\Plugin\Filter\Markdown::getConfiguration()
    // @see filter_formats()
    // @see markdown_filter_format_load()
    if (isset($configuration['filterFormat'])) {
      // Filter format is an entity, ensure configuration has an identifier.
      if ($configuration['filterFormat'] instanceof FilterFormat) {
        $this->setFilterFormat($configuration['filterFormat']);
        $configuration['filterFormat'] = $configuration['filterFormat']->id();
      }
      // Filter format is an identifier, ensure that it is properly loaded.
      elseif (is_string($configuration['filterFormat']) && (!$this->filterFormat || $this->filterFormat->id() !== $configuration['filterFormat'])) {
        if ($currentFilterFormat = drupal_static('markdown_filter_format_load')) {
          $filterFormat = $currentFilterFormat;
        }
        else {
          /** @var \Drupal\filter\Entity\FilterFormat $filterFormat */
          $filterFormat = FilterFormat::load($configuration['filterFormat']);
        }
        $this->setFilterFormat($filterFormat);
      }
    }

    // The passed configuration is for the filter plugin.
    $configuration += ['settings' => []];

    // The settings of the filter plugin are the parser configuration.
    $parserConfiguration = $configuration['settings'];

    // Some older 8.x-2.x code used to have just the parser as a string.
    // @todo Remove after 8.x-2.0 release.
    if (isset($parserConfiguration['parser'])) {
      if (\is_string($parserConfiguration['parser'])) {
        $parserConfiguration['id'] = $parserConfiguration['parser'];
      }
      // Some older 8.x-2.x code used to have nested parser config in an array.
      // @todo Remove after 8.x-2.0 release.
      elseif (is_array($parserConfiguration['parser'])) {
        $parserConfiguration += $parserConfiguration['parser'];
      }
      unset($parserConfiguration['parser']);
    }

    $parserId = !empty($parserConfiguration['id']) ? $parserConfiguration['id'] : $this->parserManager->getDefaultParser()->getPluginId();

    // If the "override" setting for the filter isn't flagged, then it should
    // be using the site-wide parser configuration. Replace the configuration
    // so it only passes the render_strategy configuration to override any
    // site-wide configuration as that is still relevant to the filter.
    $override = !empty($parserConfiguration['override']);
    if (!$override) {
      $render_strategy = isset($parserConfiguration['render_strategy']) ? $parserConfiguration['render_strategy'] : [];
      $parserConfiguration = \Drupal::config("markdown.parser.$parserId")->get() ?: [];
      $parserConfiguration['render_strategy'] = $render_strategy;
    }

    // Create a new parser based on the configuration being set.
    $parser = $this->parserManager->createInstance($parserId, array_merge([
      'enabled' => TRUE,
    ], $parserConfiguration));

    $this->setParser($parser);

    // Normalize the configuration settings from the parser itself.
    $parserConfiguration = array_merge(
      ['override' => $override],
      $parser->getConfiguration()
    );

    // Remove settings and extension settings if not overridden.
    if (!($parserConfiguration['override'] = $override)) {
      unset($parserConfiguration['settings']);
      unset($parserConfiguration['extensions']);
    }

    // Remove any weight, not needed here.
    unset($parserConfiguration['weight']);

    // Remove dependencies, this is added above.
    // @see \Drupal\markdown\Plugin\Filter\Markdown::calculateDependencies()
    unset($parserConfiguration['dependencies']);

    // Replace filter settings with normalized parser configuration.
    $configuration['settings'] = $parserConfiguration;

    return parent::setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function setParser(ParserInterface $parser = NULL) {
    if ($parser instanceof FilterAwareInterface) {
      $parser->setFilter($this);
    }

    // Add a cacheable dependency on the filter format, if it exists.
    if ($parser instanceof FilterFormatAwareInterface && ($filterFormat = $this->getFilterFormat())) {
      $parser->setFilterFormat($filterFormat);
      $parser->addCacheableDependency($filterFormat);
    }

    return $this->setParserTrait($parser);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // Filter settings are nested inside "details". Due to the way the Form API
    // works, any time a property is explicitly specified, the default property
    // values are not included. It must be manually retrieved and set here.
    $form['#process'] = $this->elementInfo->getInfoProperty('details', '#process', []);

    // Now, add the process to build the subform.
    $form['#process'][] = [$this, 'processSubform'];

    // If there's no filter format set, attempt to extract it from the form.
    if (!$this->filterFormat && ($formObject = $form_state->getFormObject()) && $formObject instanceof EntityFormInterface && ($entity = $formObject->getEntity()) && $entity instanceof FilterFormat) {
      $this->setFilterFormat($entity);
    }

    return $form;
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
  public function processSubform(array $element, FormStateInterface $form_state, array &$complete_form) {
    // Create a subform state.
    $subform_state = SubformState::createForSubform($element, $complete_form, $form_state);

    // If the triggering element is the parser select element, clear out any
    // parser values other than the identifier. This is necessary since the
    // parser has switched and the previous parser settings may not translate
    // correctly to the new parser.
    if (($trigger = $form_state->getTriggeringElement()) && isset($trigger['#ajax']['callback']) && $trigger['#ajax']['callback'] === '\Drupal\markdown\Plugin\Filter\FilterMarkdown::ajaxChangeParser' && ($parserId = $subform_state->getValue('id'))) {
      $parents = $subform_state->createParents();
      $input = &NestedArray::getValue($form_state->getUserInput(), $parents);
      $values = &NestedArray::getValue($form_state->getValues(), $parents);
      if ($trigger['#type'] === 'select') {
        $input = ['id' => $parserId, 'override' => (int) !empty($input['override'])];
        $values = ['id' => $parserId, 'override' => (int) !empty($values['override'])];
      }
      $configuration = $this->getConfiguration();
      $configuration['settings'] = $values;
      $this->setConfiguration($configuration);
    }

    $parser = $this->getParser();
    $parserId = $parser->getPluginId();

    // Attempt to build the parser form, catch any exceptions.
    try {
      $element = ParserConfigurationForm::create()
        ->setFilter($this)
        ->setParser($parser)
        ->processSubform($element, $form_state, $complete_form);
    }
    catch (\Exception $exception) {
      // Intentionally left blank.
    }
    catch (\Throwable $exception) {
      // Intentionally left blank.
    }

    if (isset($exception)) {
      $element['error'] = static::createInlineMessage([
        'error' => [
          Markup::create($exception->getMessage()),
        ],
      ]);
    }

    $markdownAjaxId = $form_state->get('markdownAjaxId');

    // Add enabled parsers.
    $labels = [];
    foreach ($this->parserManager->enabled() as $name => $enabledParser) {
      $labels[$name] = $enabledParser->getLabel(TRUE);
    }

    // Check if parser exists and, if not, append an option showing it missing.
    if (!$this->parserManager->hasDefinition($parserId)) {
      $labels[$parserId] = new FormattableMarkup('@label (missing)', [
        '@label' => $parserId,
      ]);
    }

    $parserElement = &$element['parser'];
    $parserElement['status'] = $parser->buildStatus();
    $parserElement['status']['#weight'] = -10;
    $parserElement['id'] = static::createElement([
      '#weight' => -9,
      '#type' => 'select',
      '#options' => $labels,
      '#default_value' => $parserId,
      '#description' => $parser->getDescription(),
      '#attributes' => [
        'data' => [
          'markdownSummary' => 'parser',
          'markdownId' => $parserId,
          'markdownInstalled' => $parser->isInstalled(),
        ],
      ],
      '#ajax' => [
        'callback' => '\Drupal\markdown\Plugin\Filter\FilterMarkdown::ajaxChangeParser',
        'event' => 'change',
        'wrapper' => $markdownAjaxId,
      ],
    ]);

    $override = !empty($this->getConfiguration()['settings']['override']);
    $parserElement['override'] = static::createElement([
      '#weight' => -8,
      '#type' => 'radios',
      '#options' => [
        0 => $this->t('Inherit site-wide settings'),
        1 => $this->t('Override site-wide settings'),
      ],
      '#default_value' => (int) $override,
      '#ajax' => [
        'callback' => '\Drupal\markdown\Plugin\Filter\FilterMarkdown::ajaxChangeParser',
        'event' => 'change',
        'wrapper' => $markdownAjaxId,
      ],
    ]);

    if (($markdownParserEditUrl = Url::fromRoute('markdown.parser.edit', ['parser' => $parser])) && $markdownParserEditUrl->access()) {
      $parserElement['override']['#description'] = $this->t('Site-wide markdown settings can be adjusted by visiting the site-wide <a href=":markdown.parser.edit" target="_blank">@label</a> parser.', [
        '@label' => $parser->getLabel(FALSE),
        ':markdown.parser.edit' => $markdownParserEditUrl->toString(),
      ]);
    }
    else {
      $parserElement['override']['#description'] = $this->t('Site-wide markdown settings can only be adjusted by administrators.');
    }

    if (!$override) {
      $parserElement['settings']['#access'] = FALSE;
      $parserElement['extensions']['#access'] = FALSE;
    }

    // Append a "more info" link to the parser in the description.
    if ($url = $parser->getUrl()) {
      $parserElement['id']['#description'] = $this->moreInfo($parserElement['id']['#description'], $url);
    }

    return $element;
  }

  /**
   * The AJAX callback used to return the parser ajax wrapper.
   */
  public static function ajaxChangeParser(array $form, FormStateInterface $form_state) {
    // Immediately return if subform parents aren't known.
    if (!($arrayParents = $form_state->get('markdownSubformArrayParents'))) {
      $arrayParents = array_slice($form_state->getTriggeringElement()['#array_parents'], 0, -2);
    }
    $subform = &NestedArray::getValue($form, $arrayParents);
    return $subform['ajax'];
  }

  /**
   * The process callback for "text_format" elements.
   *
   * @param array $element
   *   The render array element being processed, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form, passed by reference.
   *
   * @return array
   *   The modified $element.
   */
  public static function processTextFormat(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Immediately return if not a valid filter format.
    if (!isset($element['#format']) || !($formats = filter_formats()) || !isset($formats[$element['#format']])) {
      return $element;
    }

    /** @var \Drupal\filter\FilterFormatInterface $format */
    $format = $formats[$element['#format']];
    try {
      if (($markdown = $format->filters('markdown')) && $markdown->status) {
        $element['format']['help']['about'] = [
          '#type' => 'link',
          // Shamelessly copied from GitHub's Octicon icon set.
          // @todo Revisit this?
          // @see https://primer.style/octicons/markdown
          '#title' => Markup::create('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16"><path fill-rule="evenodd" d="M14.85 3H1.15C.52 3 0 3.52 0 4.15v7.69C0 12.48.52 13 1.15 13h13.69c.64 0 1.15-.52 1.15-1.15v-7.7C16 3.52 15.48 3 14.85 3zM9 11H7V8L5.5 9.92 4 8v3H2V5h2l1.5 2L7 5h2v6zm2.99.5L9.5 8H11V5h2v3h1.5l-2.51 3.5z"></path></svg>'),
          '#url' => Url::fromRoute('filter.tips_all')->setOptions([
            'attributes' => [
              'class' => ['markdown'],
              'target' => '_blank',
              'title' => t('Styling with Markdown is supported'),
            ],
          ]),
        ];
      }
    }
    /* @noinspection PhpRedundantCatchClauseInspection */
    catch (PluginNotFoundException $exception) {
      // Intentionally do nothing.
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    // On the "short" tips, don't show anything.
    // @see \Drupal\markdown\Plugin\Filter\FilterMarkdown::processTextFormat
    if (!$long) {
      return NULL;
    }
    return $this->moreInfo($this->t('Parses markdown and converts it to HTML.'), 'https://www.drupal.org/docs/8/modules/markdown');
  }

}
