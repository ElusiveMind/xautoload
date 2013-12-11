<?php

namespace Drupal\xautoload\Tests;

class XAutoloadWebTestCase extends \DrupalWebTestCase {

  /**
   * {@inheritdoc}
   */
  static function getInfo() {
    return array(
      'name' => 'X Autoload web test',
      'description' => 'Test xautoload class loading for an example module.',
      'group' => 'X Autoload',
    );
  }

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();
  }

  /**
   *
   */
  function testNoCache() {
    $this->xautoloadCheckCacheMode('dev');
  }

  /**
   *
   */
  function testApcCache() {
    $this->xautoloadCheckCacheMode('apc');
  }

  /**
   *
   */
  function testApcLazyCache() {
    $this->xautoloadCheckCacheMode('apc_lazy');
  }

  /**
   * @param string $mode
   *   The autoloader mode, like 'apc' or 'apc_lazy'.
   */
  protected function xautoloadCheckCacheMode($mode) {

    variable_set('xautoload_cache_mode', $mode);
    $this->pass("Set cache mode: '$mode'");

    // Enable xautoload.
    module_enable(array('xautoload'), FALSE);

    // At this time the xautoload_cache_mode setting is not in effect yet,
    // so we have to clear old cached values from APC cache.
    xautoload()->cacheManager->renewCachePrefix();

    module_enable(array('xautoload_test_1', 'xautoload_test_2'), FALSE);
    menu_rebuild();

    $this->xautoloadModuleEnabled('xautoload_test_1', array('Drupal\xautoload_test_1\ExampleClass'), FALSE);
    $this->xautoloadModuleCheckJson('xautoload_test_1', $mode, array('Drupal\xautoload_test_1\ExampleClass'));

    $this->xautoloadModuleEnabled('xautoload_test_2', array('xautoload_test_2_ExampleClass'), TRUE);
    $this->xautoloadModuleCheckJson('xautoload_test_2', $mode, array('xautoload_test_2_ExampleClass'));
  }

  /**
   * @param string $module
   * @param string[] $classes
   * @param bool $classes_on_include
   */
  protected function xautoloadModuleEnabled($module, $classes, $classes_on_include) {

    $observation_function = '_' . $module . '_early_boot_observations';
    $observation_function('later');

    $all = $observation_function();

    foreach ($all as $phase => $observations) {
      $when =
        ($phase === 'early') ? 'on drupal_load() during module_enable()' : (
        ($phase === 'later') ? 'after hook_modules_enabled()' : (
        'at an undefined time'
      ));

      // Test the classes of the example module.
      foreach ($classes as $class) {
        // Test that the class was already found in $phase.
        if ($classes_on_include || $phase !== 'early') {
          $this->assertTrue($observations[$class], "Class $class was found $when.");
        }
        else {
          $this->assertFalse($observations[$class], "Class $class cannot be found $when.");
        }
      }
    }
  }

  /**
   * @param string $module
   * @param string $mode
   *   The autoloader mode, like 'apc' or 'apc_lazy'.
   * @param string[] $classes
   */
  protected function xautoloadModuleCheckJson($module, $mode, $classes) {

    $path = "$module.json";
    $json = $this->drupalGet($path);
    $all = json_decode($json, TRUE);

    if (!is_array($all) || empty($all)) {
      $this->fail("$path must return a non-empty json array.");
      return;
    }

    foreach ($all as $phase => $observations) {
      $when =
        ($phase === 'early') ? 'on early bootstrap' : (
        ($phase === 'boot')  ? 'during hook_boot()' : (
        'at an undefined time'
      ));
      $this->xautoloadCheckTestEnvironment($observations, $mode, $when);

      // Test the classes of the example module.
      foreach ($classes as $class) {
        // Test that the class was already found in $phase.
        $this->assertTrue($observations[$class], "Class $class was found $when.");
      }
    }
  }

  /**
   * @param array $observations
   * @param string $mode
   *   The autoloader mode, like 'apc' or 'apc_lazy'.
   * @param $when
   */
  protected function xautoloadCheckTestEnvironment($observations, $mode, $when) {

    // Check early-bootstrap variables.
    $this->assertEqual($observations['xautoload_cache_mode'], $mode,
      "xautoload_cache_mode was '$mode' $when.");

    // Check registered class loaders.
    $this->assertAutoloadStackOrder($observations['spl_autoload_functions'], $mode);
  }

  /**
   * @param string $mode
   *   The autoloader mode, like 'apc' or 'apc_lazy'.
   * @return string[]
   *   Expected order of class loaders on the spl autoload stack for the given
   *   autoloader mode. Each represented by a string.
   */
  protected function expectedAutoloadStackOrder($mode) {

    switch ($mode) {
      case 'apc':
      case 'apc_lazy':
        $loader = 'xautoload_ClassLoader_ApcCache->loadClass()';
        break;
      default:
        $loader = 'xautoload_ClassFinder_NamespaceOrPrefix->loadClass()';
    }

    return array(
      'drupal_autoload_class',
      'drupal_autoload_interface',
      $loader,
      '_simpletest_autoload_psr0',
    );
  }

  /**
   * @param string[] $autoload_stack
   *   Actual order of class loaders on the spl autoload stack, to be compared
   *   with those expected for the given autoloader mode. Each represented by a
   *   string.
   * @param string $mode
   *   The autoloader mode, like 'apc' or 'apc_lazy'.
   */
  protected function assertAutoloadStackOrder($autoload_stack, $mode) {

    $expected = $this->expectedAutoloadStackOrder($mode);

    foreach ($autoload_stack as $index => $str) {
      if (!isset($expected[$index])) {
        break;
      }
      $expected_str = $expected[$index];
      if ($expected_str === $str) {
        $this->pass("Autoload callback at index $index must be $expected_str.");
      }
      else {
        $this->fail("Autoload callback at index $index must be $expected_str instead of $str.");
      }
    }

    if (!isset($index)) {
      return;
    }

    for (++$index; isset($autoload_stack[$index]); ++$index) {
      $str = $autoload_stack[$index];
      $this->fail("Autoload callback at index $index must be empty instead of $str.");
    }

    for (++$index; isset($expected[$index]); ++$index) {
      $expected_str = $expected[$index];
      $this->fail("Autoload callback at index $index must be $expected_str instead being empty.");
    }
  }

  /**
   * Assert that a module is disabled.
   *
   * @param string $module
   */
  protected function assertModuleDisabled($module) {
    $this->assertFalse(module_exists($module), "Module $module is disabled.");
  }

  /**
   * Assert that a module is enabled.
   *
   * @param string $module
   */
  protected function assertModuleEnabled($module) {
    $this->assertTrue(module_exists($module), "Module $module is enabled.");
  }

  /**
   * Assert that a class is defined.
   *
   * @param string $class
   */
  protected function assertClassExists($class) {
    $this->assertTrue(class_exists($class), "Class '$class' must exist.");
  }
}
