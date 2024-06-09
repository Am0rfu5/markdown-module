<?php

namespace Drupal\markdown\Form;

use Drupal\Core\Form\SubformStateInterface as CoreSubformStateInterface;

/**
 * Interface for markdown plugin subforms.
 */
interface SubformStateInterface extends CoreSubformStateInterface {

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
   *   Optional. A specific array of parents. If not provided, the parents
   *   are determined automatically by the subform state.
   */
  public function addElementState(array &$element, $state, $name, array $conditions, array $parents = NULL);

  /**
   * Creates conditional markup.
   *
   * This is primarily intended to add supplemental "markup" or "message"
   * through out the UI to help inform users why certain things are disabled
   * or otherwise unavailable.
   *
   * @param array $element
   *   A render array element. By default, the element is an inline span and
   *   all that is needed is the #value (i.e. translatable string). However,
   *   the element can be completely custom.
   * @param string $state
   *   The state that will be applied to $element when conditions are met.
   * @param string $name
   *   The input element name.
   * @param array $conditions
   *   The conditions for the $state.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered $element.
   */
  public function conditionalElement(array $element, $state, $name, array $conditions);

  /**
   * Creates a new parents array for a given element.
   *
   * @param string $name
   *   The element name.
   * @param string $property
   *   The property name (#parents or #array_parents).
   *
   * @return array
   *   A new array of parents.
   */
  public function createParents($name = NULL, $property = '#parents');

  /**
   * Retrieves all parents for the form state up to this point.
   *
   * @param string $property
   *   The property name (#parents or #array_parents).
   *
   * @return array
   *   An indexed array of parents.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the requested property does not exist.
   * @throws \UnexpectedValueException
   *   Thrown when the subform is not contained by the given parent form.
   */
  public function getAllParents($property = '#parents');

  /**
   * Retrieves the parent form/element.
   *
   * @return array
   *   The parent form/element.
   */
  public function &getParentForm();

}
