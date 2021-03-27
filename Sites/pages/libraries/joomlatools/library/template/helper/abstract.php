<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Template Helper
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Template\Helper
 */
abstract class KTemplateHelperAbstract extends KObject implements KTemplateHelperInterface
{
    /**
     * Template object
     *
     * @var	object
     */
    private $__template;

    /**
     * Constructor
     *
     * @throws UnexpectedValueException    If no 'template' config option was passed
     * @throws InvalidArgumentException    If the model config option does not implement TemplateInterface
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->__template = $config->template;
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config An optional ObjectConfig object with configuration options
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'template' => 'default',
        ));

        parent::_initialize($config);
    }

    /**
     * Gets the template object
     *
     * @return  KTemplateInterface	The template object
     */
    public function getTemplate()
    {
        if(!$this->__template instanceof KTemplateInterface)
        {
            if(empty($this->__template) || (is_string($this->__template) && strpos($this->__template, '.') === false) )
            {
                $identifier         = $this->getIdentifier()->toArray();
                $identifier['path'] = array('template');
                $identifier['name'] = $this->__template;
            }
            else $identifier = $this->getIdentifier($this->__template);

            $this->__template = $this->getObject($identifier);
        }

        return $this->__template;
    }

    /**
     * Build an HTML element
     *
     * @param string $tag HTML tag name
     * @param array  $attributes Key/Value pairs for the attributes
     * @param string|array|callable $children Child elements, not applicable for self-closing tags
     * @return string
     *
     * Example:
     * ```php
     * echo $this->buildElement('a', ['href' => 'https://example.com/'], 'example link');
     * // returns '<a href="https://example.com/">example link</a>
     *
     * echo $this->buildElement('meta', ['name' => 'foo', 'content' => 'bar']);
     * // returns '<meta name="foo" content="bar" />
     *
     * ```
     */
    public function buildElement($tag, $attributes = [], $children = '')
    {
        static $self_closing_tags = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
            'link', 'meta', 'param', 'source', 'track', 'wbr',
        ];

        $attribs = $this->buildAttributes($attributes);
        $attribs = $attribs ? ' '.$attribs : '';
        $tag     = strtolower($tag);

        if (in_array($tag, $self_closing_tags)) {
            return "<$tag$attribs>";
        } else if (strpos($tag, 'ktml:') === 0 && !$children) {
            return "<$tag$attribs />";
        } else {
            if (!is_scalar($children) && is_callable($children)) {
                $children = $children($tag, $attributes);
            }

            if (is_array($children)) {
                $children = implode("\n", $children);
            }

            return "<$tag$attribs>$children</$tag>";
        }
    }

    /**
     * Method to build a string with xml style attributes from  an array of key/value pairs
     *
     * @param   mixed   $array The array of Key/Value pairs for the attributes
     * @return  string  String containing xml style attributes
     */
    public function buildAttributes($array)
    {
        $output = array();

        if($array instanceof KObjectConfig) {
            $array = KObjectConfig::unbox($array);
        }

        if(is_array($array))
        {
            foreach($array as $key => $item)
            {
                if(is_array($item)) {
                    $item = implode(' ', $item);
                }

                if (is_bool($item))
                {
                    if ($item === false) continue;
                    $item = $key;
                }

                $output[] = $key.'="'.str_replace('"', '&quot;', $item).'"';
            }
        }

        return implode(' ', $output);
    }
}
