<?php

namespace metalinspired\NestedSet;

use metalinspired\NestedSet\Exception;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Driver\ResultInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;

abstract class AbstractNestedSet
{
    /**
     * Database adapter instance
     *
     * @var AdapterInterface
     */
    protected $adapter = null;

    /**
     * Name of a table used to execute queries
     * Can be a string or one element array
     * where key represents alias and value actual table name
     *
     * @var string|array
     */
    protected $table = null;

    /**
     * Column name for identifiers of nodes
     *
     * @var string
     */
    protected $idColumn = 'id';

    /**
     * Column name for left values of nodes
     *
     * @var string
     */
    protected $leftColumn = 'lft';

    /**
     * Column name for right values of nodes
     *
     * @var string
     */
    protected $rightColumn = 'rgt';

    /**
     * Identifier of root node
     * This is used to omit root node from results
     * If set to null, default behavior, root node id will be automatically determined
     *
     * @var int|string|null
     */
    protected $rootNodeId;

    /**
     * Cached statements
     *
     * @var array
     */
    protected $statements = [];

    /**
     * @var Sql
     */
    protected $sql = null;

    public function __construct(Config $config = null)
    {
        if ($config) {
            $this->loadConfig($config);
        }
    }

    /**
     * Returns currently set database adapter instance
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Sets database adapter instance
     *
     * @param AdapterInterface $adapter
     * @return $this Provides a fluent interface
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->statements = [];
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Returns table name used for executing queries
     *
     * @return string|null
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Sets table name used for executing queries
     *
     * @param string|array $table Table name
     * @return $this Provides a fluent interface
     * @throws Exception\InvalidArgumentException
     */
    public function setTable($table)
    {
        if (!is_string($table) && !is_array($table)) {
            throw new Exception\InvalidArgumentException();
        }

        $this->statements = [];
        $this->table = $table;
        return $this;
    }

    /**
     * Returns name of column used for identifiers of nodes
     *
     * @return string
     */
    public function getIdColumn()
    {
        return $this->idColumn;
    }

    /**
     * Sets name of column used for identifiers of nodes
     *
     * @param string $name Id column name
     * @return $this Provides a fluent interface
     */
    public function setIdColumn($name)
    {
        $this->idColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }

    /**
     * Returns name of column used for left values of nodes
     *
     * @return string
     */
    public function getLeftColumn()
    {
        return $this->leftColumn;
    }

    /**
     * Sets name of column used for left values of nodes
     *
     * @param string $name
     * @return $this Provides a fluent interface
     */
    public function setLeftColumn($name)
    {
        $this->leftColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }

    /**
     * Returns name of column used for right values of nodes
     *
     * @return string
     */
    public function getRightColumn()
    {
        return $this->rightColumn;
    }

    /**
     * Sets name of column used for right values of nodes
     *
     * @param string $name
     * @return $this Provides a fluent interface
     */
    public function setRightColumn($name)
    {
        $this->rightColumn = $this->checkColumnName($name);
        $this->statements = [];

        return $this;
    }

    /**
     * Returns identifier of root node
     *
     * @return int|string
     */
    public function getRootNodeId()
    {
        if (null === $this->rootNodeId) {
            $this->detectRootNodeId();
        }
        return $this->rootNodeId;
    }

    /**
     * Sets identifier of root node
     *
     * @param int|string|null $id Root node identifier or null to detect the identifier
     * @return $this Provides a fluent interface
     */
    public function setRootNodeId($id)
    {
        if (!is_null($id) && !is_int($id) && (!is_string($id) || empty($id))) {
            throw new Exception\InvalidRootNodeIdentifierException($id);
        }

        $this->statements = [];
        $this->rootNodeId = $id;

        return $this;
    }

    protected function detectRootNodeId()
    {
        $selectLft = new Select($this->table);
        $selectLft
            ->columns([
                'lft' => new Expression('MIN(' . $this->leftColumn . ')')
            ], false);

        $selectRgt = new Select($this->table);
        $selectRgt
            ->columns([
                'rgt' => new Expression('MAX(' . $this->rightColumn . ')')
            ], false);

        $select = new Select(['root' => $this->table]);
        $select
            ->columns([$this->idColumn])
            ->join(
                ['lft' => $selectLft],
                new Expression('1=1'),
                []
            )
            ->join(
                ['rgt' => $selectRgt],
                new Expression('1=1'),
                []
            )
            ->where
            ->equalTo(
                "root.{$this->leftColumn}",
                new Expression(
                    '?',
                    [
                        ["lft.lft" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            )
            ->equalTo(
                "root.{$this->rightColumn}",
                new Expression(
                    '?',
                    [
                        ["rgt.rgt" => Expression::TYPE_IDENTIFIER]
                    ]
                )
            );

        $result = $this->sql->prepareStatementForSqlObject($select)->execute();

        if (!$result instanceof ResultInterface || !$result->isQueryResult()) {
            throw new Exception\UnknownDbException();
        }

        if (1 !== $result->getAffectedRows()) {
            throw new Exception\RootNodeNotDetectedException();
        }

        $this->rootNodeId = (int)$result->current()[$this->idColumn];
    }

    /**
     * Checks if column name is valid
     *
     * @param string $name
     * @return string
     * @throws Exception\InvalidColumnNameException
     */
    protected function checkColumnName($name)
    {
        if (!is_string($name) || empty($name)) {
            throw new Exception\InvalidColumnNameException();
        }

        return $name;
    }

    /**
     * Loads configuration
     *
     * @param Config $config
     * @return $this Provides a fluent interface
     */
    public function loadConfig(Config $config)
    {
        $this->statements = [];

        foreach (get_object_vars($config) as $key => $value) {
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}($value);
            }
        }

        $this->sql = new Sql($this->adapter);

        return $this;
    }
}
