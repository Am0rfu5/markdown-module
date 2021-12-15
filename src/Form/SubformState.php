<?php

namespace Drupal\markdown\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\markdown\BcSupport\SubformState as CoreSubformState;
use Drupal\markdown\Traits\FormTrait;

/**
 * Markdown subform state.
 */
class SubformState extends CoreSubformState implements SubformStateInterface {

  use FormTrait {
    addElementState as traitAddElementState;
  }

  /**
   * {@inheritdoc}
   */
  protected function __construct(array &$subform, array &$parent_form, FormStateInterface $parent_form_state) {
    $this->decoratedFormState = $parent_form_state;
    $this->parentForm = &$parent_form;
    $this->subform = &$subform;
  }

  /**
   * {@inheritdoc}
   */
  public function conditionalElement(array $element, $state, $name, array $conditions) {
    $element += ['#type' => 'html_tag'];
    if ($element['#type'] === 'container') {
      $element += [
        '#theme_wrappers' => ['container__markdown_conditional_element__' . $name],
      ];
    }
    if ($element['#type'] === 'html_tag') {
      $element += ['#tag' => 'span'];
    }
    $element['#attributes']['class'][] = 'js-form-item';
    $this->addElementState($element, $state, $name, $conditions);

    // Older versions of Drupal core do not have the necessary helper method:
    // \Drupal\Core\Form\FormHelper::processStates. Instead of completely
    // back-porting that entire class, we'll just do what it does here.
    // @todo Replace with FormHelper::processStates() in D9.
    $element['#attached']['library'][] = 'core/drupal.states';
    $element['#attributes']['data-drupal-states'] = Json::encode($element['#states']);

    return \Drupal::service('renderer')->render($element);
  }

  /**
   * {@inheritdoc}
   */
  public static function createForSubform(array &$subform, array &$parent_form, FormStateInterface $parent_form_state) {
    // Attempt to construct #parents array based on passed values.
    if (!isset($subform['#parents']) && $parent_form_state instanceof SubformStateInterface && ($name = array_search($subform, $parent_form, TRUE))) {
      $subform['#parents'] = array_merge($parent_form_state->getAllParents(), [$name]);
    }
    return parent::createForSubform($subform, $parent_form, $parent_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function addElementState(array &$element, $state, $name, array $conditions, array $parents = NULL) {
    if (!isset($parents)) {
      $parents = $this->getAllParents();
    }
    static::traitAddElementState($element, $state, $name, $conditions, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function createParents($name = NULL, $property = '#parents') {
    $parents = $this->getAllParents($property);
    if ($name) {
      $parents = array_merge($parents, (array) $name);
    }
    return $parents;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllParents($property = '#parents') {
    if (!isset($this->parentForm[$property])) {
      throw new \RuntimeException(sprintf('The subform and parent form must contain the %s property, which must be an array. Try calling this method from a #process callback instead.', $property));
    }

    // Merge the parent form and subform's relative parents.
    return array_merge($this->parentForm[$property], $this->getParents($property));
  }

  /**
   * {@inheritdoc}
   */
  public function &getParentForm() {
    return $this->parentForm;
  }

}
