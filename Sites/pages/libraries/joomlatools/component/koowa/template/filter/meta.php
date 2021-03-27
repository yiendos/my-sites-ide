<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Script Template Filter
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Component\Koowa\Template\Filter
 */
class ComKoowaTemplateFilterMeta extends KTemplateFilterMeta
{
    /**
     * Find any virtual tags and render them
     *
     * This function will pre-pend the tags to the content
     *
     * @param string $text  The text to parse
     */
    public function filter(&$text)
    {
        $meta    = $this->_parseTags($text);

        if($this->getTemplate()->decorator() == 'koowa') {
            $text = str_replace('<ktml:meta>', $meta, $text);
        } else  {
            $text = $meta.$text;
        }
    }

    /**
     * Render the tag
     *
     * @param   array   $attribs Associative array of attributes
     * @param   string  $content The tag content
     * @return string
     */
    protected function _renderTag($attribs = array(), $content = null)
    {
        if($this->getTemplate()->decorator() == 'joomla')
        {
            if(isset($attribs['name'])) {
                JFactory::getDocument()->setMetaData($attribs['name'], $attribs['content'], 'name');
            }
            elseif(isset($attribs['property'])) {
                JFactory::getDocument()->setMetaData($attribs['property'], $attribs['content'], 'property');
            }
            else
            {
                $meta = parent::_renderTag($attribs, $content);
                JFactory::getDocument()->addCustomTag($meta);
            }
        }
        else return parent::_renderTag($attribs, $content);
    }
}
