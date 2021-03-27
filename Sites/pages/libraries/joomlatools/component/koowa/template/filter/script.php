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
class ComKoowaTemplateFilterScript extends KTemplateFilterScript
{
    /**
     * An array of MD5 hashes for loaded script strings
     */
    protected $_loaded = array();

    /**
     * Find any virtual tags and render them
     *
     * This function will pre-pend the tags to the content
     *
     * @param string $text  The text to parse
     */
    public function filter(&$text)
    {
        $scripts = $this->_parseTags($text);

        if($this->getTemplate()->decorator() == 'koowa') {
            $text = str_replace('<ktml:script>', $scripts, $text);
        } else  {
            $text = $scripts.$text;
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
            $link = isset($attribs['src']) ? $attribs['src'] : false;
            $condition = isset($attribs['condition']) ? $attribs['condition'] : false;

            if(!$link)
            {
                $script = trim($content);
                $hash   = md5($script.serialize($attribs));

                if (!isset($this->_loaded[$hash]))
                {
                    if($condition)
                    {
                        $script = parent::_renderTag($attribs, $content);
                        JFactory::getDocument()->addCustomTag($script);
                    }
                    else JFactory::getDocument()->addScriptDeclaration($script);

                    $this->_loaded[$hash] = true;
                }
            }
            else
            {
                if (defined('JOOMLATOOLS_PLATFORM'))
                {
                    if($condition)
                    {
                        $script = parent::_renderTag($attribs, $content);
                        JFactory::getDocument()->addCustomTag($script);
                    }
                    else
                    {
                        $defer = isset($attribs['defer']);
                        $async = isset($attribs['async']);

                        JFactory::getDocument()->addScript($link, 'text/javascript', $defer, $async);
                    }
                }
                else
                {
                    $options = [];

                    if (isset($attribs['defer'])) { $attribs['defer'] = true; }
                    if (isset($attribs['async'])) { $attribs['async'] = true; }

                    unset($attribs['src']);

                    if($condition)
                    {
                        $options['conditional'] = $attribs['condition'];
                        unset($attribs['condition']);
                    }

                    JFactory::getDocument()->addScript($link, $options, $attribs);
                }
            }
        }
        else return parent::_renderTag($attribs, $content);
    }
}