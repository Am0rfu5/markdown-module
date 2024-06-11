<?php

namespace Drupal\markdown\Annotation;

/**
 * Base annotation for "installable" plugins.
 *
 * Note: Doctrine doesn't support multiple types, so if the property accepts
 * more than a single type, "mixed" must be used instead of the desired
 * piped types.
 *
 * @see https://github.com/doctrine/annotations/issues/129
 *
 * @todo Move upstream to https://www.drupal.org/project/installable_plugins.
 */
abstract class InstallablePlugin extends AnnotationObject {

  use InstallablePluginTrait;

  /**
   * An array of available installable libraries this plugin supports.
   *
   * @var \Drupal\markdown\Annotation\InstallableLibrary[]
   */
  public $libraries = [];

  /**
   * Internal marker to ensure required dependencies are enabled.
   */
  public $_requiredBy;

  /**
   * Retrieves the installed library or plugin identifier.
   *
   * @return string
   *   The installed identifier.
   */
  public function getInstalledId() {
    if (($installed = $this->getInstalledLibrary()) && ($id = $installed->getId())) {
      return $id;
    }
    return $this->getId();
  }

  /**
   * Retrieves the installed library.
   *
   * @return \Drupal\markdown\Annotation\InstallableLibrary|void
   *   The installed library.
   */
  public function getInstalledLibrary() {
    return current(array_filter($this->libraries, function ($library) {
      return !$library->requirementViolations;
    })) ?: NULL;
  }

  /**
   * Retrieves the preferred library.
   *
   * @return \Drupal\markdown\Annotation\InstallableLibrary|void
   *   The preferred library.
   */
  public function getPreferredLibrary() {
    return current(array_filter($this->libraries, function ($library) {
      return $library->preferred;
    }));
  }

  /**
   * Retrieves requirements of a certain type.
   *
   * @param string $type
   *   The requirement type to limit by.
   * @param string $id
   *   Optional. A specific identifier to limit by.
   *
   * @return \Drupal\markdown\Annotation\InstallableRequirement[]
   *   An array of requirements matching the type.
   */
  public function getRequirementsByType($type, $id = NULL) {
    $requirements = [];
    foreach (array_merge($this->requirements, $this->runtimeRequirements) as $requirement) {
      if (!$requirement instanceof InstallableRequirement) {
        continue;
      }
      list($t, $i) = $requirement->listTypeId();
      if ($type === $t) {
        if (isset($id) && $id !== $i) {
          continue;
        }
        $requirements[] = $requirement;
      }
    }
    return $requirements;
  }

  /**
   * Retrieves requirements of a certain constraint type.
   *
   * @param string $name
   *   The requirement constraint name to limit by.
   * @param mixed $value
   *   Optional. A specific value to limit by.
   *
   * @return \Drupal\markdown\Annotation\InstallableRequirement[]
   *   An array of requirements matching the type.
   */
  public function getRequirementsByConstraint($name, $value = NULL) {
    $requirements = [];
    foreach (array_merge($this->requirements, $this->runtimeRequirements) as $requirement) {
      if (!$requirement instanceof InstallableRequirement) {
        continue;
      }
      foreach ($requirement->constraints as $k => $v) {
        if ($k === $name) {
          if (isset($value) && $value !== $v) {
            continue;
          }
          $requirements[] = $requirement;
          continue 2;
        }
      }
    }
    return $requirements;
  }

  /**
   * Indicates whether plugin is installed.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isInstalled() {
    return empty($this->requirementViolations);
  }

  /**
   * Indicates whether the preferred library is installed.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function isPreferredLibraryInstalled() {
    return $this->getPreferredLibrary() === $this->getInstalledLibrary();
  }

}
