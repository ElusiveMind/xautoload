<?php


/**
 * Namespace handlers are for:
 *   - More exotic autoload patterns that are incompatible with PSR-0 or PEAR
 *   - Situations where we don't want to register a ton of namespaces, and using
 *     a handler instead gives us performance benefits.
 */
interface xautoload_NamespaceHandler_Interface {

  /**
   * Find the file for a class that in PSR-0 or PEAR would be in
   * $psr_0_root . '/' . $first_part . $second_part
   *
   * E.g.:
   *   - The class we look for is Some\Namespace\Some\Class
   *   - The file is actually in "exotic/location.php". This is not following
   *     PSR-0 or PEAR standard, so we need a handler.
   *   -> The class finder will transform the class name to
   *     "Some/Namespace/Some/Class.php"
   *   - The handler was registered for the namespace "Some\Namespace". This is
   *     because all those exotic classes all begin with Some\Namespace\
   *   -> The arguments will be:
   *     ($api = the API object, see below)
   *     $first_part = "Some/Namespace/"
   *     $second_part = "Some/Class.php"
   *     $api->getClass() gives the original class name, if we still need it.
   *   -> We are supposed to:
   *     if ($api->suggestFile('exotic/location.php')) {
   *       return TRUE;
   *     }
   *
   * @param xautoload_InjectedAPI_findFile $api
   *   An object with a suggestFile() method.
   *   We are supposed to suggest files until suggestFile() returns TRUE, or we
   *   have no more suggestions.
   * @param string $first_part
   *   The key that this handler was registered with.
   *   With trailing DIRECTORY_SEPARATOR.
   * @param string $second_part
   *   Second part of the canonical path, ending with '.php'.
   */
  function findFile($api, $first_part, $second_part);
}
