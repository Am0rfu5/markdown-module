<?php

namespace Drupal\markdown\Util;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class FormHelper {

  /**
   * Flag indicating whether the token module exists.
   *
   * @var bool
   */
  protected static $tokenModuleExists;

  /**
   * Adds a #states selector to an element.
   *
   * @param array $element
   *   An element to add the state to, passed by reference.
   * @param string $state
   *   The state that will be triggered.
   * @param string $name
   *   The name of the element used for conditions.
   * @param array $conditions
   *   The conditions of $name that trigger $state.
   * @param array|null $parents
   *   An array of parents.
   */
  public static function addElementState(array &$element, string $state, string $name, array $conditions, array $parents = NULL): void {
    // If parents weren't provided, attempt to extract it from the element.
    if (!isset($parents)) {
      $parents = isset($element['#parents']) ? $element['#parents'] : [];
    }

    // Add the state.
    if ($selector = self::getElementSelector($name, $parents)) {
      if (!isset($element['#states'][$state][$selector])) {
        $element['#states'][$state][$selector] = [];
      }
      $element['#states'][$state][$selector] = NestedArray::mergeDeep($element['#states'][$state][$selector], $conditions);
    }
  }

  /**
   * Creates an element, adding data attributes to it if necessary.
   *
   * @param array $element
   *   An element.
   *
   * @return array
   *   The modified $element.
   */
  public static function createElement(array $element): array {
    if (isset($element['#attributes']['data']) && is_array($element['#attributes']['data'])) {
      self::addDataAttributes($element, $element['#attributes']['data']);
      unset($element['#attributes']['data']);
    }
    return $element;
  }

  /**
   * Creates a Token browser element for use when dealing with tokens.
   *
   * @param array $tokenTypes
   *   An array of token types.
   * @param bool $globalTypes
   *   Flag indicating whether to display global tokens.
   * @param bool $dialog
   *   Flag indicating whether to show the browser in a dialog.
   *
   * @return array
   *   A new render array element.
   */
  public static function createTokenBrowser(array $tokenTypes = [], bool $globalTypes = TRUE, bool $dialog = TRUE): array {
    if (!isset(static::$tokenModuleExists)) {
      static::$tokenModuleExists = \Drupal::moduleHandler()->moduleExists('token');
    }

    if (static::$tokenModuleExists) {
      return [
        '#type' => 'item',
        '#input' => FALSE,
        '#theme' => 'token_tree_link',
        '#token_types' => $tokenTypes,
        '#global_types' => $globalTypes,
        '#dialog' => $dialog,
      ];
    }

    return [
      '#type' => 'item',
      '#input' => FALSE,
      '#markup' => t('To browse available tokens, install the @token module.', [
        '@token' => Link::fromTextAndUrl('Token', Url::fromUri('https://www.drupal.org/project/token', ['attributes' => ['target' => '_blank']]))->toString(),
      ]),
    ];
  }

  /**
   * Allows a form element to be reset to its default value.
   *
   * @param array $element
   *   The render array element to modify, passed by reference.
   * @param string $name
   *   The name.
   * @param mixed $defaultValue
   *   The default value.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function resetToDefault(array &$element, string $name, $defaultValue, FormStateInterface $form_state): void {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $selector = self::getElementSelector($name, $form_state->createParents());

    $reset = self::createElement([
      '#type' => 'link',
      '#title' => '↩️',
      '#url' => Url::fromUserInput('#reset-default-value', ['external' => TRUE]),
      '#attributes' => [
        'data' => [
          'markdownElement' => 'reset',
          'markdownId' => 'reset_' . $name,
          'markdownTarget' => $selector,
          'markdownDefaultValue' => $defaultValue,
        ],
        'title' => t('Reset to default value'),
        'style' => 'display: none; text-decoration: none;',
      ],
    ]);

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    $element['#attached']['library'][] = 'markdown/reset';
    $element['#title'] = new FormattableMarkup('@title @reset', [
      '@title' => $element['#title'],
      '@reset' => $renderer->renderPlain($reset),
    ]);
  }

  /**
   * Adds a data attribute to an element.
   *
   * @param array $element
   *   An element, passed by reference.
   * @param string $name
   *   The name of the data attribute.
   * @param mixed $value
   *   The value of the data attribute. Note: do not JSON encode this value.
   */
  public static function addDataAttribute(array &$element, string $name, $value): void {
    self::addDataAttributes($element, [$name => $value]);
  }

  /**
   * Retrieves the selector for an element.
   *
   * @param string $name
   *   The name of the element.
   * @param array $parents
   *   An array of parents.
   *
   * @return string
   *   The selector for an element.
   */
  public static function getElementSelector(string $name, array $parents): string {
    // Immediately return if name is already an input selector.
    if (strpos($name, ':input[name="') === 0) {
      return $name;
    }

    // Add the name of the element that will be used for the condition.
    $parents[] = $name;

    // Remove the first parent as the base selector.
    $selector = array_shift($parents);

    // Join remaining parents with [].
    if ($parents) {
      $selector .= '[' . implode('][', $parents) . ']';
    }

    return $selector ? ':input[name="' . $selector . '"]' : '';
  }

  /**
   * Creates an inline status message to be used in a render array.
   *
   * @param array $messages
   *   An array of messages, grouped by message type (i.e.
   *   ['status' => ['message']]).
   * @param int $weight
   *   The weight of the message.
   *
   * @return array
   *   The messages converted into a render array to be used inline.
   */
  public static function createInlineMessage(array $messages, $weight = -10): array {
    static $headings;
    if (!$headings) {
      $headings = [
        'error' => t('Error message'),
        'info' => t('Info message'),
        'status' => t('Status message'),
        'warning' => t('Warning message'),
      ];
    }
    return [
      '#type' => 'item',
      '#weight' => $weight,
      '#theme' => 'status_messages',
      '#message_list' => $messages,
      '#status_headings' => $headings,
    ];
  }

  /**
   * Adds multiple data attributes to an element.
   *
   * @param array $element
   *   An element, passed by reference.
   * @param array $data
   *   The data attributes to add.
   */
  public static function addDataAttributes(array &$element, array $data): void {
    $converter = new CamelCaseToSnakeCaseNameConverter();
    foreach ($data as $name => $value) {
      $name = str_replace('_', '-', $converter->normalize(preg_replace('/^data-?/', '', $name)));
      $element['#attributes']['data-' . $name] = is_string($value) || $value instanceof MarkupInterface ? (string) $value : Json::encode($value);
    }
  }

}
