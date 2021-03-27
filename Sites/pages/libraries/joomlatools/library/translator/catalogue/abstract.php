<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Translator Catalogue
 *
 * @author  Ercan Ozkaya <https://github.com/ercanozkaya>
 * @package Koowa\Library\Translator\Catalogue
 */
abstract class KTranslatorCatalogueAbstract extends KObjectArray implements KTranslatorCatalogueInterface
{
    /**
     * Get a string from the registry
     *
     * @param  string $string
     * @return  string  The translation of the string
     */
    public function get($string)
    {
        return $this->offsetGet($string);
    }

    /**
     * Set a string in the registry
     *
     * @param  string $string
     * @param  string $translation
     * @return KTranslatorCatalogueAbstract
     */
    public function set($string, $translation)
    {
        $this->offsetSet($string, $translation);
        return $this;
    }

    /**
     * Check if a string exists in the registry
     *
     * @param  string $string
     * @return boolean
     */
    public function has($string)
    {
        return $this->offsetExists((string) $string);
    }

    /**
     * Remove a string from the registry
     *
     * @param  string $string
     * @return KTranslatorCatalogueAbstract
     */
    public function remove($string)
    {
        $this->offsetUnset($string);
        return $this;
    }

    /**
     * Add translations to the catalogue.
     *
     * @param array  $translations Associative array containing translations.
     * @param bool   $override     If TRUE override existing translations. Default is FALSE.
     * @return bool True on success, false otherwise.
     */
    public function add(array $translations, $override = false)
    {
        if ($override) {
            $this->_data = array_merge($this->_data, $translations);
        } else {
            $this->_data = array_merge($translations, $this->_data);
        }

        return true;
    }

    /**
     * Clears out all strings from the registry
     *
     * @return  KTranslatorCatalogueAbstract
     */
    public function clear()
    {
        $this->_data = array();
        return $this;
    }
}