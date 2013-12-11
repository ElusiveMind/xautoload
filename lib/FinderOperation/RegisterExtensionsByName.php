<?php

class xautoload_FinderOperation_RegisterExtensionsByName implements xautoload_FinderOperation_Interface {

  /**
   * @var string[]
   */
  protected $extensionNames;

  /**
   * @param string[] $extension_names
   *   Array of module names, with numeric keys.
   */
  function __construct($extension_names) {
    $this->extensionNames = $extension_names;
  }

  /**
   * {@inheritdoc}
   */
  function operateOnFinder($finder, $helper) {

    // Register the namespaces / prefixes for those modules.
    $helper->registerExtensionsByName($this->extensionNames);
  }
}