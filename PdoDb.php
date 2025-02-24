<?php
/**
 * PdoDb Class
 *
 * @category  Database Access
 * @package   PdoDbDb
 * @author    Osman Cakmak <info@oxcakmak.com>
 * @copyright Copyright (c) 2024-?
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link      https://github.com/oxcakmak/PdoDb-Driver
 * @version   1.0.6
 */
class PDODb
{
    /**
     * Static instance of self
     * @var PDODb
     */
    protected static $_instance;

    /**
     * Table prefix
     * @var string
     */
    public static $prefix = '';

    /**
     * PDO instance
     * @var PDO
     */
    protected $pdo;

    /**
     * The SQL query to be prepared and executed
     * @var string
     */
    protected $_query;

    /**
     * The previously executed SQL query
     * @var string
     */
    protected $_lastQuery;

    /**
     * An array that holds where conditions
     * @var array
     */
    protected $_where = array();

    /**
     * An array that holds where join
     * @var array
     */
    protected $_join = array();

    /**
     * An array that holds having conditions
     * @var array
     */
    protected $_having = array();

    /**
     * Dynamic type list for order by condition value
     * @var array
     */
    protected $_orderBy = array();

    /**
     * Dynamic type list for group by condition value
     * @var array
     */
    protected $_groupBy = array();

    /**
     * Dynamic array that holds a combination of where condition/table data value types and parameter references
     * @var array
     */
    protected $_bindParams = array(''); // Create the empty 0 index

    /**
     * Variable which holds an amount of returned rows during get/getOne/select queries
     * @var int
     */
    public $count = 0;

    /**
     * Variable which holds last statement error
     * @var string
     */
    protected $_error;

    /**
     * Database credentials
     * @var array
     */
    protected $connectionParams = array();

    /**
     * Is Subquery object
     * @var bool
     */
    protected $isSubQuery = false;

    /**
     * Name of the auto increment column
     * @var int
     */
    protected $_lastInsertId = null;

    /**
     * Column names for update when using onDuplicate method
     * @var array
     */
    protected $_updateColumns = null;

    /**
     * Return type: 'array' to return results as array, 'object' as object
     * 'json' as json string
     * @var string
     */
    public $returnType = 'array';

    /**
     * Per page limit for pagination
     * @var int
     */
    public $pageLimit = 20;

    /**
     * Variable that holds total pages count of last paginate() query
     * @var int
     */
    public $totalPages = 0;

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $db
     * @param int    $port
     */
    public function __construct($host = null, $username = null, $password = null, $db = null, $port = null)
    {
        $this->connectionParams = array(
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'db' => $db,
            'port' => $port
        );

        if ($host != null) {
            $this->connect();
        }
    }

    /**
     * A method to connect to the database
     */
    public function connect()
    {
        if ($this->pdo) {
            return;
        }

        try {
            $dsn = "mysql:host={$this->connectionParams['host']};dbname={$this->connectionParams['db']}";
            if ($this->connectionParams['port']) {
                $dsn .= ";port={$this->connectionParams['port']}";
            }

            $this->pdo = new PDO($dsn, 
                $this->connectionParams['username'],
                $this->connectionParams['password'],
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * A method of returning the static instance to allow access to the
     * instantiated object from within another class.
     * Inheriting this class would require reloading connection info.
     *
     * @uses $db = PDODb::getInstance();
     *
     * @return PDODb Returns the current instance.
     */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Reset states after an execution
     *
     * @return void
     */
    protected function reset()
    {
        $this->_where = array();
        $this->_join = array();
        $this->_having = array();
        $this->_orderBy = array();
        $this->_groupBy = array();
        $this->_bindParams = array(''); // Create the empty 0 index
        $this->_query = null;
        $this->count = 0;
    }

    /**
     * Method attempts to prepare the SQL query
     * and throws an error if there was a problem.
     *
     * @return PDOStatement
     */
    protected function _prepareQuery()
    {
        try {
            $stmt = $this->pdo->prepare($this->_query);
        } catch (PDOException $e) {
            throw new Exception("Problem preparing query ($this->_query) " . $e->getMessage());
        }

        return $stmt;
    }

    /**
     * Execute raw SQL query.
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     *
     * @return array Contains the returned rows from the query.
     */
    public function rawQuery($query, $bindParams = null)
    {
        $this->_query = $query;
        $stmt = $this->_prepareQuery();
        
        if (is_array($bindParams)) {
            $i = 1;
            foreach ($bindParams as $param) {
                $stmt->bindValue($i++, $param);
            }
        }
        
        $stmt->execute();
        $this->count = $stmt->rowCount();
        
        return $this->_fetchAll($stmt);
    }

    /**
     * Helper function to execute simple SELECT, INSERT, UPDATE, DELETE queries
     *
     * @param string $query
     * @param array $bindParams
     * @return bool|array
     */
    public function query($query, $bindParams = null)
    {
        $stmt = $this->pdo->prepare($query);
        
        if (is_array($bindParams)) {
            foreach ($bindParams as $key => $value) {
                $stmt->bindValue(is_numeric($key) ? $key + 1 : $key, $value);
            }
        }
        
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->count = $stmt->rowCount();
        
        return $result;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) WHERE statements for SQL queries.
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param string $operator   Comparison operator. Default is =
     * @param string $cond       Condition of where statement (OR, AND)
     *
     * @return PDODb
     */
    public function where($whereProp, $whereValue = null, $operator = '=', $cond = 'AND')
    {
        if (is_array($whereValue) && ($operator == 'IN' || $operator == 'NOT IN')) {
            $whereIn = $whereValue;
            $whereValue = '(' . implode(',', array_fill(0, count($whereIn), '?')) . ')';
            $this->_bindParams = array_merge($this->_bindParams, $whereIn);
        } else {
            $this->_bindParams[] = $whereValue;
        }

        $this->_where[] = array($cond, $whereProp, $operator, $whereValue);
        return $this;
    }

    /**
     * This method allows you to concatenate joins for the final SQL statement.
     *
     * @param string $joinTable     The name of the table.
     * @param string $joinCondition the condition.
     * @param string $joinType      'LEFT', 'INNER' etc.
     *
     * @return PDODb
     */
    public function join($joinTable, $joinCondition, $joinType = '')
    {
        $this->_join[] = array($joinType, $joinTable, $joinCondition);
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
     *
     * @param string $orderBy   The name of the database field.
     * @param string $orderDir  The direction (ASC, DESC)
     *
     * @return PDODb
     */
    public function orderBy($orderBy, $orderDir = "DESC")
    {
        $this->_orderBy[$orderBy] = $orderDir;
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) GROUP BY statements for SQL queries.
     *
     * @param string $groupBy The name of the database field.
     *
     * @return PDODb
     */
    public function groupBy($groupBy)
    {
        $this->_groupBy[] = $groupBy;
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) HAVING statements for SQL queries.
     *
     * @param string $havingProp  The name of the database field.
     * @param mixed  $havingValue The value of the database field.
     * @param string $operator    Comparison operator. Default is =
     *
     * @return PDODb
     */
    public function having($havingProp, $havingValue = null, $operator = '=')
    {
        $this->_having[] = array("AND", $havingProp, $operator, $havingValue);
        return $this;
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @param int $numRows The number of rows to limit
     *
     * @return PDODb
     */
    public function limit($numRows)
    {
        if ($numRows) {
            $this->_query .= " LIMIT " . (int)$numRows;
        }
        return $this;
    }

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function startTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->pdo->rollBack();
    }

    /**
     * Get the last inserted ID.
     *
     * @return string
     */
    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Get last error
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->_error;
    }

    /**
     * Method to create/update records
     *
     * @param string $tableName The name of the table.
     * @param array $insertData Data containing information for inserting into the DB.
     *
     * @return bool Boolean indicating whether the insert query was completed successfully.
     */
    public function insert($tableName, $insertData)
    {
        $this->_query = "INSERT INTO " . self::$prefix . $tableName;
        $fields = array_keys($insertData);
        $values = array_values($insertData);
        
        $this->_query .= ' (' . implode(',', $fields) . ') VALUES (';
        $this->_query .= implode(',', array_fill(0, count($fields), '?'));
        $this->_query .= ')';

        try {
            $stmt = $this->_prepareQuery();
            foreach ($values as $i => $value) {
                $stmt->bindValue($i + 1, $value);
            }
            $stmt->execute();
            $this->_lastInsertId = $this->pdo->lastInsertId();
            return true;
        } catch (PDOException $e) {
            $this->_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Update query. Be sure to first call the "where" method.
     *
     * @param string $tableName The name of the database table to work with.
     * @param array  $tableData Array of data to update the desired row.
     *
     * @return bool
     */
    public function update($tableName, $tableData)
    {
        $this->_query = "UPDATE " . self::$prefix . $tableName . " SET ";
        
        $fields = array_keys($tableData);
        $values = array_values($tableData);
        
        $fieldSet = array();
        foreach ($fields as $field) {
            $fieldSet[] = "$field = ?";
        }
        
        $this->_query .= implode(',', $fieldSet);
        
        if (!empty($this->_where)) {
            $this->_buildWhere();
        }

        try {
            $stmt = $this->_prepareQuery();
            
            // Bind update data
            foreach ($values as $i => $value) {
                $stmt->bindValue($i + 1, $value);
            }
            
            // Bind where conditions
            if (!empty($this->_bindParams)) {
                foreach ($this->_bindParams as $i => $value) {
                    $stmt->bindValue($i + count($values) + 1, $value);
                }
            }
            
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Delete query. Call the "where" method first.
     *
     * @param string $tableName The name of the database table to work with.
     *
     * @return bool Indicates success. 0 or 1.
     */
    public function delete($tableName)
    {
        $this->_query = "DELETE FROM " . self::$prefix . $tableName;

        if (!empty($this->_where)) {
            $this->_buildWhere();
        }

        try {
            $stmt = $this->_prepareQuery();
            
            if (!empty($this->_bindParams)) {
                foreach ($this->_bindParams as $i => $value) {
                    $stmt->bindValue($i + 1, $value);
                }
            }
            
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            $this->_error = $e->getMessage();
            return false;
        }
    }

    /**
     * This method allows you to specify multiple (method chaining optional) AND WHERE statements for SQL queries.
     *
     * @uses $PDODb->where('id', 7)->where('title', 'MyTitle');
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param string $operator   Comparison operator. Default is =
     *
     * @return PDODb
     */
    public function andWhere($whereProp, $whereValue = null, $operator = '=')
    {
        return $this->where($whereProp, $whereValue, $operator, 'AND');
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR WHERE statements for SQL queries.
     *
     * @uses $PDODb->orWhere('id', 7)->orWhere('title', 'MyTitle');
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param string $operator   Comparison operator. Default is =
     *
     * @return PDODb
     */
    public function orWhere($whereProp, $whereValue = null, $operator = '=')
    {
        return $this->where($whereProp, $whereValue, $operator, 'OR');
    }

    /**
     * Abstraction method that will build the part of the WHERE conditions
     */
    protected function _buildWhere()
    {
        if (empty($this->_where)) {
            return;
        }

        $this->_query .= ' WHERE ';

        $i = 0;
        foreach ($this->_where as $cond) {
            list($concat, $wProp, $wOperator, $wValue) = $cond;

            if ($i > 0) {
                $this->_query .= " $concat ";
            }

            $this->_query .= "$wProp $wOperator ?";
            $i++;
        }
    }

    /**
     * Method returns a single row from the database.
     *
     * @param string $tableName The name of the database table to work with.
     * @param array  $columns   Array of database columns to select
     *
     * @return array
     */
    public function getOne($tableName, $columns = '*')
    {
        $res = $this->get($tableName, 1, $columns);
        return $res ? $res[0] : null;
    }

    /**
     * Method returns rows from the database.
     *
     * @param string $tableName The name of the database table to work with.
     * @param int|array $numRows Array/number of rows to select, or null for all
     * @param array $columns Array of database columns to select
     *
     * @return array
     */
    public function get($tableName, $numRows = null, $columns = '*')
    {
        if (empty($columns)) {
            $columns = '*';
        }

        $column = is_array($columns) ? implode(', ', $columns) : $columns;

        $this->_query = "SELECT $column FROM " . self::$prefix . $tableName;

        // Join
        if (!empty($this->_join)) {
            $this->_buildJoin();
        }

        // Where
        if (!empty($this->_where)) {
            $this->_buildWhere();
        }

        // Group by
        if (!empty($this->_groupBy)) {
            $this->_query .= " GROUP BY " . implode(', ', $this->_groupBy);
        }

        // Having
        if (!empty($this->_having)) {
            $this->_buildHaving();
        }

        // Order by
        if (!empty($this->_orderBy)) {
            $this->_query .= " ORDER BY ";
            $i = 0;
            foreach ($this->_orderBy as $prop => $value) {
                if ($i > 0) {
                    $this->_query .= ', ';
                }
                $this->_query .= "$prop $value";
                $i++;
            }
        }

        // Limit
        if ($numRows !== null) {
            if (is_array($numRows)) {
                $this->_query .= ' LIMIT ' . (int)$numRows[0] . ',' . (int)$numRows[1];
            } else {
                $this->_query .= ' LIMIT ' . (int)$numRows;
            }
        }

        try {
            $stmt = $this->_prepareQuery();
            
            if (!empty($this->_bindParams)) {
                foreach ($this->_bindParams as $i => $value) {
                    $stmt->bindValue($i + 1, $value);
                }
            }
            
            $stmt->execute();
            $this->count = $stmt->rowCount();
            
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $res;
        } catch (PDOException $e) {
            $this->_error = $e->getMessage();
            return false;
        }
    }
}
?>
