<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Searchable Model Behavior
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Model\Behavior
 */
class KModelBehaviorSearchable extends KModelBehaviorAbstract
{
    /**
     * The column names to search in
     *
     * Default is 'title'.
     *
     * @var array
     */
    protected $_columns;

    /**
     * Constructor.
     *
     * @param   KObjectConfig $config An optional KObjectConfig object with configuration options
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->_columns = (array)KObjectConfig::unbox($config->columns);

        $this->addCommandCallback('before.fetch', '_buildQuery')
            ->addCommandCallback('before.count', '_buildQuery');
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   KObjectConfig $config An optional KObjectConfig object with configuration options
     *
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'columns' => 'title',
        ));

        parent::_initialize($config);
    }

    /**
     * Insert the model states
     *
     * @param KObjectMixable $mixer
     */
    public function onMixin(KObjectMixable $mixer)
    {
        parent::onMixin($mixer);

        $mixer->getState()
            ->insert('search', 'string');
    }

    /**
     * Add search query
     *
     * @param   KModelContextInterface $context A model context object
     *
     * @return    void
     */
    protected function _buildQuery(KModelContextInterface $context)
    {
        $model = $context->getSubject();

        if ($model instanceof KModelDatabase && !$context->state->isUnique())
        {
            $state  = $context->state;
            $search = $state->search;

            if ($search)
            {
                $search_column = null;
                $columns       = array_keys($this->getTable()->getColumns());

                // Parse $state->search for possible column prefix
                if (preg_match('#^([a-z0-9\-_]+)\s*:\s*(.+)\s*$#i', $search, $matches))
                {
                    if (in_array($matches[1], $this->_columns) || $matches[1] === 'id') {
                        $search_column = $matches[1];
                        $search        = $matches[2];
                    }
                }

                // Search in the form of id:NUM
                if ($search_column === 'id')
                {
                    $context->query->where('(tbl.' . $this->getTable()->getIdentityColumn() . ' = :search)')
                        ->bind(array('search' => $search));
                }
                else
                {
                    $conditions = array();

                    foreach ($this->_columns as $column)
                    {
                        if (in_array($column, $columns) && (!$search_column || $column === $search_column)) {
                            $conditions[] = 'tbl.' . $column . ' LIKE :search';
                        }
                    }

                    if ($conditions)
                    {
                        $context->query->where('(' . implode(' OR ', $conditions) . ')')
                            ->bind(array('search' => '%' . $search . '%'));
                    }
                }
            }
        }
    }
}