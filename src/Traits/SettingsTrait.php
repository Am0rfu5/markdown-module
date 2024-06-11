<?php

namespace Drupal\markdown\Traits;

use Drupal\Component\Utility\DiffArray;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\markdown\Util\FormHelper;
use Drupal\markdown\Util\SortArray;

/**
 * Trait for installable plugins that implement settings.
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
trait SettingsTrait {

  /**
   * Creates a setting element.
   *
   * @param string $name
   *   The setting name.
   * @param array $element
   *   The array element to construct. Note: this will be filled in with
   *   defaults if they're not provided.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param callable $valueTransformer
   *   Optional. Callback used to transform the setting value.
   *
   * @return array
   *   A render array with a child matching the name of the setting.
   *   This is primarily so that it can union with the parent element, e.g.
   *   `$form += $this->createSettingsElement(...)`.
   */
  protected function createSettingElement($name, array $element, FormStateInterface $form_state, callable $valueTransformer = NULL) {
    /** @var \Drupal\markdown\Form\SubformStateInterface $form_state */
    $settingName = $name;
    $parts = explode('.', $name);
    $name = array_pop($parts);

    // Prevent render if setting doesn't exist.
    if (!isset($element['#access']) && !$this->settingExists($settingName)) {
      $element['#access'] = FALSE;
    }

    // Create placeholder title so it can be replaced with a proper translation.
    if (!isset($element['#title'])) {
      @trigger_error('Deprecated in markdown:8.x-2.0 and is removed from markdown:3.0.0. No replacement for automatically populating. The #title property must be explicitly specified for locale tools to extract the correct value. See https://www.drupal.org/project/markdown/issues/3142418.', E_USER_DEPRECATED);
      $element['#title'] = $this->t(ucwords(str_replace(['-', '_'], ' ', $name))); // phpcs:ignore
    }

    // Handle initial setting value (Drupal names this #default_value).
    if (!isset($element['#default_value'])) {
      $value = $form_state->getValue($name, $this->getSetting($settingName));
      if ($valueTransformer) {
        $return = call_user_func($valueTransformer, $value);
        if (isset($return)) {
          $value = $return;
        }
      }
      $element['#default_value'] = $value;
    }

    // Handle real default setting value.
    $defaultValue = $this->getDefaultSetting($settingName);
    if (isset($defaultValue)) {
      if ($valueTransformer) {
        $return = call_user_func($valueTransformer, $defaultValue);
        if (isset($return)) {
          $defaultValue = $return;
        }
      }
      FormHelper::resetToDefault($element, $name, $defaultValue, $form_state);
    }

    return [$name => FormHelper::createElement($element)];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings($pluginDefinition) {
    /** @var \Drupal\markdown\Annotation\InstallablePlugin $pluginDefinition */
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSetting($name) {
    $defaultSettings = static::defaultSettings($this->getPluginDefinition());
    $parts = explode('.', $name);
    if (count($parts) == 1) {
      return isset($defaultSettings[$name]) ? $defaultSettings[$name] : NULL;
    }
    $value = NestedArray::getValue($defaultSettings, $parts, $key_exists);
    return $key_exists ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($name, $default = NULL) {
    $value = $this->config()->get("settings.$name");
    return isset($value) ? $value : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings($runtime = FALSE, $sorted = TRUE) {
    $settings = $this->config()->get('settings') ?: [];

    // Sort settings (in case configuration was provided by form values).
    if ($sorted && $settings) {
      SortArray::recursiveKeySort($settings);
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingOverrides($runtime = FALSE, $sorted = TRUE, array $settings = NULL) {
    if (!isset($settings)) {
      $settings = $this->getSettings($runtime, FALSE);
    }

    $overridden = DiffArray::diffAssocRecursive($settings, static::defaultSettings($this->getPluginDefinition()));

    if ($sorted && $overridden) {
      SortArray::recursiveKeySort($overridden);
    }

    return $overridden;
  }

  /**
   * {@inheritdoc}
   */
  public function settingExists($name) {
    return array_key_exists($name, static::defaultSettings($this->pluginDefinition));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsKey() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Intentionally do nothing. This is just required to be implemented.
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Intentionally do nothing. This is just required to be implemented.
  }

}
