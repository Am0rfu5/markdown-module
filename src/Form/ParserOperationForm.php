<?php

namespace Drupal\markdown\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\markdown\Plugin\Markdown\ParserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Provides a confirmation form to disable a parser.
 *
 * @internal
 */
class ParserOperationForm extends ConfirmFormBase {

  protected $operationConfigNames = [
    'default' => 'markdown.settings',
    'disable' => 'markdown.parser.%s',
    'enable' => 'markdown.parser.%s',
  ];

  /**
   * The operation to perform.
   *
   * @var string
   */
  protected $operation;

  /**
   * The operation method to invoke.
   *
   * @var callable
   */
  protected $operationMethod;

  /**
   * The markdown parser.
   *
   * @var \Drupal\markdown\Plugin\Markdown\ParserInterface
   */
  protected $parser;

  /**
   * An array of variables to use in translations.
   *
   * @var string[]
   */
  protected $variables;

  /**
   * Creates a URL with the appropriate CSRF token for a parser operation.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   The parser to perform an operation on.
   * @param string $operation
   *   The operation to perform.
   *
   * @return \Drupal\Core\Url
   *   A parser operation Url object.
   */
  public static function createOperationUrl(ParserInterface $parser, $operation) {
    // Because this is redirecting to a CSRF access controlled route, this
    // needs the correct token added to the route's query option. Core
    // usually does this automatically, but its based on this current form
    // not the redirected route. So it must be manually set here.
    $url = Url::fromRoute('markdown.parser.operation', [
      'parser' => $parser,
      'operation' => $operation,
    ]);
    $token = \Drupal::csrfToken()->get($url->getInternalPath());
    $options = $url->getOptions();
    $options['query']['token'] = $token;
    $url->setOptions($options);
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'markdown_parser_operation';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    switch ($this->operation) {
      case 'default':
        return $this->t('Are you sure you want to set %parser as the default markdown parser?', [
          '@operation' => $this->operation,
          '%parser' => $this->parser->getLabel(FALSE),
        ]);
    }
    return $this->t('Are you sure you want to @operation the %parser markdown parser?', [
      '@operation' => $this->operation,
      '%parser' => $this->parser->getLabel(FALSE),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Confirm Operation');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    switch ($this->operation) {
      case 'default':
        return $this->t('Set as default');
    }
    return $this->t(ucfirst($this->operation));
  }

  /**
   * Retrieves the success message to show after the operation has finished.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The success message.
   */
  public function getSuccessMessage() {
    $variables = [
      '@action' => substr($this->operation, -1, 1) === 'e' ? $this->t($this->operation . 'd') : $this->operation . 'ed',
      '@operation' => $this->operation,
      '%parser' => $this->parser->getLabel(FALSE),
      '@parser_id' => $this->parser->getPluginId(),
    ];
    switch ($this->operation) {
      case 'default':
        return $this->t('%parser was set as the default markdown parser.', $variables);
    }
    return $this->t('The markdown parser %parser was @action.', $variables);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('markdown.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ParserInterface $parser = NULL, $operation = NULL) {
    $this->initializeOperation($parser, $operation);
    $form = parent::buildForm($form, $form_state);

    // Allow the cancel button to simply close the dialog.
    if (!empty(\Drupal::request()->get('_drupal_ajax'))) {
      $form['actions']['cancel']['#attributes']['class'][] = 'dialog-cancel';
    }

    return $form;
  }

  /**
   * Initializes the operation.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   The parser being operated on.
   * @param string $operation
   *   The operation to perform.
   */
  protected function initializeOperation(ParserInterface $parser, $operation) {
    $this->operation = $operation;
    $this->parser = $parser;
    $this->variables = [
      '@action' => substr($this->operation, -1, 1) === 'e' ? $this->t($this->operation . 'd') : $this->operation . 'ed',
      '@operation' => $this->operation,
      '%parser' => $this->parser->getLabel(FALSE),
      '@parser_id' => $this->parser->getPluginId(),
    ];

    $converter = new CamelCaseToSnakeCaseNameConverter();
    $method = $converter->denormalize("operation_$operation");
    if (!method_exists($this, $method)) {
      throw new NotFoundHttpException();
    }
    $this->operationMethod = [$this, $method];
  }

  /**
   * Controller for the "markdown.parser.operation" route.
   *
   * @param \Drupal\markdown\Plugin\Markdown\ParserInterface $parser
   *   The parser being operated on.
   * @param string $operation
   *   The operation to perform.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response|void
   *   A render array or response object.
   */
  public function executeOperation(ParserInterface $parser, $operation) {
    $this->initializeOperation($parser, $operation);

    // Retrieve the Config object for the parser.
    $configName = sprintf(isset($this->operationConfigNames[$this->operation]) ? $this->operationConfigNames[$this->operation] : 'markdown.parser.%s', $this->parser->getPluginId());
    $config = $this->configFactory()->getEditable($configName);

    // Execute the operation, passing the config as its only parameter.
    $callable = $this->operationMethod;
    $response = $callable($config);

    $this->logger('markdown')->notice('Performed operation (@operation) on parser %parser (@parser_id).', [
      '@operation' => $this->operation,
      '%parser' => $this->parser->getLabel(FALSE),
      '@parser_id' => $this->parser->getPluginId(),
    ]);

    if ($message = $this->getSuccessMessage()) {
      $this->messenger()->addMessage($message);
    }

    // Allow the operation to override the response.
    if ($response) {
      return $response;
    }

    // Otherwise, redirect to the overview page.
    return $this->redirect('markdown.overview');
  }

  /**
   * Retrieves an editable Config object for the parser.
   *
   * @return \Drupal\Core\Config\Config
   *   The Parser Config object.
   */
  protected function getParserConfig() {
    return $this->configFactory()->getEditable('markdown.parser.' . $this->parser->getPluginId());
  }

  /**
   * Magic method for the "default" operation.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The editable parser Config object.
   *
   * @see \Drupal\markdown\Form\ParserOperationForm::initializeOperation
   */
  protected function operationDefault(Config $config) {
    $config->set('default_parser', $this->parser->getPluginId())->save();
  }

  /**
   * Magic method for the "disable" operation.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The editable parser Config object.
   *
   * @see \Drupal\markdown\Form\ParserOperationForm::initializeOperation
   */
  protected function operationDisable(Config $config) {
    $config->set('enabled', FALSE)->save();
  }

  /**
   * Magic method for the "enable" operation.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The editable parser Config object.
   *
   * @see \Drupal\markdown\Form\ParserOperationForm::initializeOperation
   */
  public function operationEnable(Config $config) {
    $config->set('enabled', TRUE)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->operation) {
      $url = static::createOperationUrl($this->parser, $this->operation);
    }
    else {
      $url = $this->getCancelUrl();
    }
    $form_state->setRedirectUrl($url);
  }

}
