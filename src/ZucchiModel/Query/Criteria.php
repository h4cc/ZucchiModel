<?php
/**
 * ZucchiModel (http://zucchi.co.uk)
 *
 * @link      http://github.com/zucchi/ZucchiModel for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zucchi Limited. (http://zucchi.co.uk)
 * @license   http://zucchi.co.uk/legals/bsd-license New BSD License
 */
namespace ZucchiModel\Query;

use Zend\Stdlib\AbstractOptions;
use Zend\Db\Sql\Where;

/**
 * Query Criteria
 *
 * @author Rick Nicol <rick@zucchi.co.uk>
 * @package ZucchiModel
 * @subpackage ModelManager
 * @category
 */
class Criteria extends AbstractOptions
{
    /**
     * Collection of Model names
     * @var array|string
     */
    protected $models;

    /**
     * Where object
     *
     * @var \Zend\Db\Sql\Where|null
     */
    protected $where = null;

    /**
     * Integer offset for the query
     *
     * @var int|null
     */
    protected $offset = null;

    /**
     * Integer limit for the query
     *
     * @var int|null
     */
    protected $limit = null;

    /**
     * Order By
     *
     * @var array|null
     */
    protected $orderBy = null;

    /**
     * Get Models - either a collection of
     * models or a single model.
     *
     * @return string|array|null
     */
    public function getModels()
    {
        return $this->models;
    }

    /**
     * Get \Zend\Db\Sql\Where
     *
     * @return \Zend\Db\Sql\Where|null
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * Get integer offset if set.
     *
     * @return int|null
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Get integer limit if set.
     *
     * @return int|null
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Get Order by if set.
     *
     * @return array|null
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * Set Models with given string or array.
     *
     * @param $models
     * @throws \RuntimeException
     */
    public function setModels($models)
    {
        if ((is_string($models) || is_array($models)) && !empty($models)) {
            $this->models = $models;
        } else {
            $type = gettype($models);
            $type = ($type != 'object')?: get_class($models);
            throw new \RuntimeException(sprintf('Models must be a string or an array and can not be empty. %s given.', $type));
        }
    }

    /**
     * Set Where with given \Zend\Db\Sql\Where.
     *
     * @param $where
     * @throws \RuntimeException
     */
    public function setWhere($where)
    {
        if ($where instanceof Where || is_null($where)) {
            $this->where = $where;
        } else {
            $type = gettype($where);
            $type = ($type != 'object')?: get_class($where);
            throw new \RuntimeException(sprintf('Where must be an instance of Zend\Db\Sql\Where or null. %s given', $type));
        }
    }

    /**
     * Set offest with given integer.
     *
     * @param $offset
     * @throws \RuntimeException
     */
    public function setOffset($offset)
    {
        if ((is_integer($offset) && $offset >= 0) || is_null($offset)) {
            $this->offset = $offset;
        } else {
            $type = gettype($offset);
            $type = ($type != 'object')?: get_class($offset);
            throw new \RuntimeException(sprintf('Offset must be a positive integer or null. %s given', $type));
        }
    }

    /**
     * Set limit with given integer.
     *
     * @param $limit
     * @throws \RuntimeException
     */
    public function setLimit($limit)
    {
        if ((is_integer($limit) > 0) || is_null($limit)) {
            $this->limit = $limit;
        } else {
            $type = gettype($limit);
            $type = ($type != 'object')?: get_class($limit);
            throw new \RuntimeException(sprintf('Limit must be a positive integer or null. %s given', $type));
        }
    }

    /**
     * Set order by with given array.
     *
     * @param $orderBy
     * @throws \RuntimeException
     */
    public function setOrderBy($orderBy)
    {
        if (is_array($orderBy) || is_null($orderBy)) {
            $this->orderBy = $orderBy;
        } else {
            $type = gettype($orderBy);
            $type = ($type != 'object')?: get_class($orderBy);
            throw new \RuntimeException(sprintf('Order By must be an array or null. %s given', $type));
        }
    }
}