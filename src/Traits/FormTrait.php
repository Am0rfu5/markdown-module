<?php

namespace Drupal\markdown\Traits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\markdown\Util\FormHelper;

/**
 * Trait providing helpful methods when dealing with forms.
 */
trait FormTrait {

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
   * @param array $parents
   *   An array of parents.
   */
  public static function addElementState(array &$element, $state, $name, array $conditions, array $parents = NULL) {
    FormHelper::addElementState($element, $state, $name, $conditions, $parents);
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
  public static function addDataAttribute(array &$element, $name, $value) {
    FormHelper::addDataAttribute($element, $name, $value);
  }

  /**
   * Adds multiple data attributes to an element.
   *
   * @param array $element
   *   An element, passed by reference.
   * @param array $data
   *   The data attributes to add.
   */
  public static function addDataAttributes(array &$element, array $data) {
    FormHelper::addDataAttributes($element, $data);
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
  public static function createElement(array $element) {
    return FormHelper::createElement($element);
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
  public static function createInlineMessage(array $messages, $weight = -10) {
    return FormHelper::createInlineMessage($messages, $weight);
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
  public static function getElementSelector($name, array $parents) {
    return FormHelper::getElementSelector($name, $parents);
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
  public static function resetToDefault(array &$element, $name, $defaultValue, FormStateInterface $form_state) {
    FormHelper::resetToDefault($element, $name, $defaultValue, $form_state);
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
  public static function createTokenBrowser(array $tokenTypes = [], $globalTypes = TRUE, $dialog = TRUE) {
    return FormHelper::createTokenBrowser($tokenTypes, $globalTypes, $dialog);
  }

}
