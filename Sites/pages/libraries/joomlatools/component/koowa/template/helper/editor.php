<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Editor Template Helper
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Component\Koowa\Template\Helper
 */
class ComKoowaTemplateHelperEditor extends KTemplateHelperAbstract
{
    /**
     * Generates an HTML editor
     *
     * @param   array   $config An optional array with configuration options
     * @return  string  Html
     */
    public function display($config = array())
    {
        $config = new KObjectConfigJson($config);
        $config->append(array(
            'editor'    => JFactory::getConfig()->get('editor'),
            'name'      => 'description',
            'value'     => '',
            'width'     => '100%',
            'height'    => '500',
            'cols'      => '75',
            'rows'      => '20',
            'buttons'   => true,
            'options'   => array()
        ));

        // Add editor styles and scripts in JDocument to page when rendering
        $this->getIdentifier('com:koowa.view.page.html')->getConfig()->append(['template_filters' => ['document']]);

        $editor  = JEditor::getInstance($config->editor);
        $options = KObjectConfig::unbox($config->options);

        $result = $editor->display($config->name, $config->value, $config->width, $config->height, $config->cols, $config->rows, KObjectConfig::unbox($config->buttons), $config->name, null, null, $options);
        
        // Some editors like CKEditor return inline JS. 
        $result = str_replace('<script', '<script data-inline', $result);

        return $result;
    }
}
