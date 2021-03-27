<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Show Database Query
 *
 * @author  Gergo Erdosi <https://github.com/gergoerdosi>
 * @package Koowa\Library\Database\Query
 */
class KDatabaseQueryShow extends KDatabaseQueryAbstract
{
    /**
     * The show clause.
     *
     * @var string
     */
    public $show;

    /**
     * The from clause.
     *
     * @var string
     */
    public $from;

    /**
     * The like clause.
     *
     * @var string
     */
    public $like;

    /**
     * The where clause.
     *
     * @var array
     */
    public $where = array();

    /**
     * Build the show clause 
     *
     * @param   string $table The name of the table.
     * @return  KDatabaseQueryShow
     */
    public function show($table) 
    {
        $this->show = $table;
        return $this;
    }

    /**
     * Build the from clause 
     *
     * @param   string $from The name of the database or table.
     * @return  $this
     */
    public function from($from)
    {
        $this->from = $from;
        return $this;
    }

    /**
     * Build the like clause 
     *
     * @param   string $pattern The pattern to match.
     * @return  KDatabaseQueryShow
     */
    public function like($pattern)
    {
        $this->like = $pattern;
    
        return $this;
    }

    /**
     * Build the where clause
     *
     * @param   string $condition   The condition.
     * @param   string $combination Combination type, defaults to 'AND'.
     * @return  KDatabaseQueryShow
     */
    public function where($condition, $combination = 'AND')
    {
        $this->where[] = array(
            'condition'   => $condition,
            'combination' => count($this->where) ? $combination : ''
        );
        
        return $this;
    }

    /**
     * Render the query to a string.
     *
     * @return  string  The query string.
     */
    public function toString()
    {
        $adapter = $this->getAdapter();
        $prefix  = $adapter->getTablePrefix();
        $query   = 'SHOW '.$this->show;

        if($this->from)
        {
            $table  = (in_array($this->show, array('FULL COLUMNS', 'COLUMNS', 'INDEX', 'INDEXES', 'KEYS')) ? $prefix : '').$this->from;
            $query .= ' FROM '.$adapter->quoteIdentifier($table);
        }

        if($this->like) {
            $query .= ' LIKE '.$adapter->quoteIdentifier($this->like);
        }

        if($this->where)
        {
            $query .= ' WHERE';
            
            foreach($this->where as $where)
            {
                if(!empty($where['combination'])) {
                    $query .= ' '.$where['combination'];
                }
            
                $query .= ' '.$adapter->quoteIdentifier($where['condition']);
            }
        }

        if($this->_parameters) {
            $query = $this->_replaceParams($query);
        }

        return $query;
    }
    
    /**
     * Callback method for parameter replacement.
     * 
     * @param  array  $matches Matches of preg_replace_callback.
     * @return string The replacement string.
     */
    protected function _replaceParamsCallback($matches)
    {
        $key    = substr($matches[0], 1);
        $prefix = '';
        
        if(in_array($this->show, array('FULL TABLES', 'OPEN TABLES', 'TABLE STATUS', 'TABLES')) &&
            ($this->like && $key == 'like' || $this->where && ($key == 'name' || $key == 'table')))
        {
            $prefix = $this->getAdapter()->getTablePrefix();
        }
        
        $replacement = $this->getAdapter()->quoteValue($prefix.$this->_parameters[$key]);
        
        return is_array($this->_parameters[$key]) ? '('.$replacement.')' : $replacement;
    }
}
