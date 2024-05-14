<?php
class PdoDb {
    private $host;
    private $username; 
    private $password; 
    private $db; 
    private $port; 
    private $prefix; 
    private $charset;
    private $pdo;
    private $stmt;
    private $tablePrefix;
    private $lastQuery;
    private $totalCount;
    private $whereClause;
    private $whereParams;
    private $orderByClause;
    private $groupByClause;
    private $joinClause;
    private $options;

    public function __construct($host, $username, $password, $db, $port, $prefix, $charset) {
        $dsn = "mysql:host={$host};dbname={$db};charset={$charset};port={$port}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new PDO($dsn, $username, $password, $options);
        $this->tablePrefix = $prefix;
    }

    public function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset};port={$this->port}";
        $this->pdo = new PDO($dsn, $this->username, $this->password, $this->options);
    }

    public function disconnect() {
        $this->pdo = null;
    }

    public function setPrefix($prefix) {
        $this->tablePrefix = $prefix;
    }

    public function ping() {
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            return false;
        }
        return true;
    }

    public function query($sql, $params = []) {
        $this->stmt = $this->pdo->prepare($sql);
        $this->stmt->execute($params);
        $this->lastQuery = $this->stmt->queryString;
        return $this->stmt;
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(", ", $keys);
        $placeholders = ":" . implode(", :", $keys);
        $sql = "INSERT INTO {$this->tablePrefix}$table ($fields) VALUES ($placeholders)";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $fields = implode(", ", $fields);
        $sql = "UPDATE {$this->tablePrefix}$table SET $fields WHERE $where";
        return $this->query($sql, $data)->rowCount();
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$this->tablePrefix}$table WHERE $where";
        return $this->query($sql, $params)->rowCount();
    }

    public function get($table, $limit = null, $columns = "*") {
        $cols = is_array($columns) ? implode(", ", $columns) : $columns;
        $sql = "SELECT $cols FROM $table";
        if (!empty($this->joinClause)) {
            $sql .= $this->joinClause;
        }
        if (!empty($this->whereClause)) {
            $sql .= " WHERE {$this->whereClause}";
        }
        if (!empty($this->groupByClause)) {
            $sql .= " {$this->groupByClause}";
        }
        if (!empty($this->orderByClause)) {
            $sql .= " {$this->orderByClause}";
        }
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        $stmt = $this->query($sql, $this->whereParams);
        $this->resetClauses(); // Reset clauses after execution
        return $stmt->fetchAll();
    }

    public function getOne($table, $columns = "*") {
        $result = $this->get($table, 1, $columns);
        return $result ? $result[0] : null;
    }

    public function rawQuery($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function rawQueryOne($sql, $params = []) {
        $result = $this->rawQuery($sql, $params);
        return $result ? $result[0] : null;
    }

    public function rawQueryValue($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchColumn();
        return $result;
    }
    public function where($column, $value, $operator = '=') {
        if (is_array($value)) {
            if ($operator === 'BETWEEN' || $operator === 'NOT BETWEEN') {
                $this->whereClause = "$column $operator :{$column}Start AND :{$column}End";
                $this->whereParams = [
                    "{$column}Start" => $value[0],
                    "{$column}End" => $value[1]
                ];
            } elseif ($operator === 'IN' || $operator === 'NOT IN') {
                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $this->whereClause = "$column $operator ($placeholders)";
                $this->whereParams = $value;
            }
        } else {
            if ($operator === 'IS' || $operator === 'IS NOT') {
                $this->whereClause = "$column $operator NULL";
                $this->whereParams = [];
            } elseif ($operator === 'LIKE' || $operator === 'NOT LIKE') {
                $this->whereClause = "$column $operator :$column";
                $this->whereParams = [$column => $value];
            } else {
                $this->whereClause = "$column $operator :$column";
                $this->whereParams = [$column => $value];
            }
        }
        return $this;
    }
    
    public function orWhere($column, $value, $operator = '=') {
        $this->whereClause .= " OR $column $operator :$column";
        $this->whereParams[$column] = $value;
        return $this;
    }

    public function getWithWhere($table, $limit = null, $columns = "*") {
        $cols = is_array($columns) ? implode(", ", $columns) : $columns;
        $sql = "SELECT $cols FROM {$this->tablePrefix}$table WHERE {$this->whereClause}";
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        $stmt = $this->query($sql, $this->whereParams);
        return $stmt->fetchAll();
    }

    public function join($table, $condition, $type = 'INNER') {
        $this->joinClause .= " $type JOIN $table ON $condition";
        return $this;
    }

    public function joinWhere($table, $column, $value) {
        $this->joinClause .= " AND $table.$column = $value";
        return $this;
    }
    
    public function joinOrWhere($table, $column, $value) {
        $this->joinClause .= " OR $table.$column = $value";
        return $this;
    }

    public function orderBy($column, $order = 'ASC', $customValues = []) {
        if (!empty($customValues)) {
            $values = implode("', '", $customValues);
            $this->orderByClause = "ORDER BY FIELD($column, '$values') $order";
        } else {
            $this->orderByClause = "ORDER BY $column $order";
        }
        return $this;
    }

    public function groupBy($column) {
        $this->groupByClause = "GROUP BY $column";
        return $this;
    }

    public function has($table) {
        $sql = "SELECT EXISTS(SELECT 1 FROM {$this->tablePrefix}$table WHERE {$this->whereClause})";
        $stmt = $this->query($sql, $this->whereParams);
        return (bool)$stmt->fetchColumn();
    }

    public function tableExists($table) {
        try {
            $result = $this->pdo->query("SELECT 1 FROM {$this->tablePrefix}$table LIMIT 1");
        } catch (Exception $e) {
            return false;
        }
        return $result !== false;
    }

    public function getLastError() {
        return $this->stmt->errorInfo();
    }

    public function getLastQuery() {
        return $this->lastQuery;
    }

    public function getTotalCount() {
        return $this->totalCount;
    }
    private function resetClauses() {
        $this->whereClause = '';
        $this->whereParams = [];
        $this->orderByClause = '';
        $this->groupByClause = '';
        $this->joinClause = '';
    }
    
}
?>
