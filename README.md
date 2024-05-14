PdoDb -- Wrapper for a PHP PDO class, which utilizes PDO and prepared statements.

Disclaimer: This database class is completely inspired by the mysqli class project, the responsibility belongs to the user.

Inspired Project:
[PHP-MySQLi-Database-Class](https://github.com/ThingEngineer/PHP-MySQLi-Database-Class/)
<hr>

### Table of Contents

**[Initialization](#initialization)**  
**[Insert Query](#insert-query)**  
**[Update Query](#update-query)**  
**[Select Query](#select-query)**  
**[Delete Query](#delete-query)**  
**[Insert Data](#insert-data)**
**[Running raw SQL queries](#running-raw-sql-queries)**  
**[Query Keywords](#query-keywords)**  
**[Where Conditions](#where--having-methods)**  
**[Order Conditions](#ordering-method)**  
**[Group Conditions](#grouping-method)**  
**[Joining Tables](#join-method)**  
**[Has method](#has-method)**  
**[Helper Methods](#helper-methods)**  
**[Error Helpers](#error-helpers)**  

## Support Me

This software is developed during my free time and I will be glad if somebody will support me.

Everyone's time should be valuable, so please consider donating.

[BuyMeCoffe](buymeacoffee.com/oxcakmak)

### Installation
To utilize this class, first import PdoDb.php into your project, and require it.

```php
require_once ('PdoDb.php');
```

### Initialization
Simple initialization with utf8 charset set by default:
```php
// It is recommended to leave the prefix field blank
$db = new PdoDb (host, username, password, db, port, prefix);
```

table prefix, port and database charset params are optional. If no charset should be set charset, set it to null

If no table prefix were set during object creation its possible to set it later with a separate call:
```php
$db->setPrefix ('my_');
```

If you need to get already created mysqliDb object from another class or function use
```php
    function init () {
        // db staying private here
        $db = new PdoDb ('host', 'username', 'password', 'databaseName');
    }
    ...
    function myfunc () {
        // obtain db object created in init  ()
        $db = MysqliDb::getInstance();
        ...
    }
```

### Insert Query
Simple example
```php
$data = Array ("login" => "admin",
               "firstName" => "John",
               "lastName" => 'Doe'
);
$id = $db->insert ('users', $data);
if($id)
    echo 'user was created. Id=' . $id;
```

### Update Query
```php
$data = Array (
	'firstName' => 'Bobby',
	'lastName' => 'Tables',
);
$db->where ('id', 1);
if ($db->update ('users', $data))
    echo $db->count . ' records were updated';
else
    echo 'update failed: ' . $db->getLastError();
```

### Select Query
After any select/get function calls amount or returned rows is stored in $count variable
```php
$users = $db->get('users'); //contains an Array of all users 
$users = $db->get('users', 10); //contains an Array 10 users
```

or select with custom columns set. Functions also could be used
```php
$cols = Array ("id", "name", "email");
$users = $db->get ("users", null, $cols);
if ($db->count > 0)
    foreach ($users as $user) { 
        print_r ($user);
    }
```

or select just one row
```php
$db->where ("id", 1);
$user = $db->getOne ("users");
echo $user['id'];

$stats = $db->getOne ("users", "sum(id), count(*) as cnt");
echo "total ".$stats['cnt']. "users found";
```

### Running raw SQL queries
```php
$users = $db->rawQuery('SELECT * from users where id >= ?', Array (10));
foreach ($users as $user) {
    print_r ($user);
}
```
To avoid long if checks there are couple helper functions to work with raw query select results:

Get 1 row of results:
```php
$user = $db->rawQueryOne('SELECT * from users where id=?', Array(10));
echo $user['login'];
// Object return type
$user = $db->ObjectBuilder()->rawQueryOne('SELECT * from users where id=?', Array(10));
echo $user->login;
```
Get 1 column value as a string:
```php
$password = $db->rawQueryValue('SELECT password from users where id=? limit 1', Array(10));
echo "Password is {$password}";
NOTE: for a rawQueryValue() to return string instead of an array 'limit 1' should be added to the end of the query.
```
Get 1 column value from multiple rows:
```php
$logins = $db->rawQueryValue('SELECT login from users limit 10');
foreach ($logins as $login)
    echo $login;
```

More advanced examples:
```php
$params = Array(1, 'admin');
$users = $db->rawQuery("SELECT id, firstName, lastName FROM users WHERE id = ? AND login = ?", $params);
print_r($users); // contains Array of returned rows

// will handle any SQL query
$params = Array(10, 1, 10, 11, 2, 10);
$q = "(
    SELECT a FROM t1
        WHERE a = ? AND B = ?
        ORDER BY a LIMIT ?
) UNION (
    SELECT a FROM t2 
        WHERE a = ? AND B = ?
        ORDER BY a LIMIT ?
)";
$results = $db->rawQuery ($q, $params);
print_r ($results); // contains Array of returned rows
```

### Where / Having Methods
`where()`, `orWhere()`, `having()` and `orHaving()` methods allows you to specify where and having conditions of the query. All conditions supported by where() are supported by having() as well.

WARNING: In order to use column to column comparisons only raw where conditions should be used as column name or functions cannot be passed as a bind variable.

Regular == operator with variables:
```php
$db->where ('id', 1);
$db->where ('login', 'admin');
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE id=1 AND login='admin';
```

```php
$db->where ('id', 1);
$db->having ('login', 'admin');
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE id=1 HAVING login='admin';
```


Regular == operator with column to column comparison:
```php
// WRONG
$db->where ('lastLogin', 'createdAt');
// CORRECT
$db->where ('lastLogin = createdAt');
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE lastLogin = createdAt;
```

```php
$db->where ('id', 50, ">=");
// or $db->where ('id', Array ('>=' => 50));
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE id >= 50;
```

BETWEEN / NOT BETWEEN:
```php
$db->where('id', Array (4, 20), 'BETWEEN');
// or $db->where ('id', Array ('BETWEEN' => Array(4, 20)));

$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id BETWEEN 4 AND 20
```

IN / NOT IN:
```php
$db->where('id', Array(1, 5, 27, -1, 'd'), 'IN');
// or $db->where('id', Array( 'IN' => Array(1, 5, 27, -1, 'd') ) );

$results = $db->get('users');
// Gives: SELECT * FROM users WHERE id IN (1, 5, 27, -1, 'd');
```

OR CASE:
```php
$db->where ('firstName', 'John');
$db->orWhere ('firstName', 'Peter');
$results = $db->get ('users');
// Gives: SELECT * FROM users WHERE firstName='John' OR firstName='peter'
```

NULL comparison:
```php
$db->where ("lastName", NULL, 'IS NOT');
$results = $db->get("users");
// Gives: SELECT * FROM users where lastName IS NOT NULL
```

LIKE comparison:
```php
$db->where ("fullName", 'John%', 'like');
$results = $db->get("users");
// Gives: SELECT * FROM users where fullName like 'John%'
```

Also you can use raw where conditions:
```php
$db->where ("id != companyId");
$db->where ("DATE(createdAt) = DATE(lastLogin)");
$results = $db->get("users");
```

Or raw condition with variables:
```php
$db->where ("(id = ? or id = ?)", Array(6,2));
$db->where ("login","mike");
$res = $db->get ("users");
// Gives: SELECT * FROM users WHERE (id = 6 or id = 2) and login='mike';
```


Find the total number of rows matched. Simple pagination example:
```php
$offset = 10;
$count = 15;
$users = $db->withTotalCount()->get('users', Array ($offset, $count));
echo "Showing {$count} from {$db->totalCount}";
```

### Delete Query
```php
$db->where('id', 1);
if($db->delete('users')) echo 'successfully deleted';
```

### Ordering method
```php
$db->orderBy("id","asc");
$db->orderBy("login","Desc");
$db->orderBy("RAND ()");
$results = $db->get('users');
// Gives: SELECT * FROM users ORDER BY id ASC,login DESC, RAND ();
```

Order by values example:
```php
$db->orderBy('userGroup', 'ASC', array('superuser', 'admin', 'users'));
$db->get('users');
// Gives: SELECT * FROM users ORDER BY FIELD (userGroup, 'superuser', 'admin', 'users') ASC;
```

If you are using setPrefix () functionality and need to use table names in orderBy() method make sure that table names are escaped with ``.

```php
$db->setPrefix ("t_");
$db->orderBy ("users.id","asc");
$results = $db->get ('users');
// WRONG: That will give: SELECT * FROM t_users ORDER BY users.id ASC;

$db->setPrefix ("t_");
$db->orderBy ("`users`.id", "asc");
$results = $db->get ('users');
// CORRECT: That will give: SELECT * FROM t_users ORDER BY t_users.id ASC;
```

### Grouping method
```php
$db->groupBy ("name");
$results = $db->get ('users');
// Gives: SELECT * FROM users GROUP BY name;
```

### JOIN method
Join table products with table users with LEFT JOIN by tenantID
```php
$db->join("users u", "p.tenantID=u.tenantID", "LEFT");
$db->where("u.id", 6);
$products = $db->get ("products p", null, "u.name, p.productName");
print_r ($products);
// Gives: SELECT u.name, p.productName FROM products p LEFT JOIN users u ON p.tenantID=u.tenantID WHERE u.id = 6
```

### Join Conditions
Add AND condition to join statement
```php
$db->join("users u", "p.tenantID=u.tenantID", "LEFT");
$db->joinWhere("users u", "u.tenantID", 5);
$products = $db->get ("products p", null, "u.name, p.productName");
print_r ($products);
// Gives: SELECT  u.name, p.productName FROM products p LEFT JOIN users u ON (p.tenantID=u.tenantID AND u.tenantID = 5)
```
Add OR condition to join statement
```php
$db->join("users u", "p.tenantID=u.tenantID", "LEFT");
$db->joinOrWhere("users u", "u.tenantID", 5);
$products = $db->get ("products p", null, "u.name, p.productName");
print_r ($products);
// Gives: SELECT  u.login, p.productName FROM products p LEFT JOIN users u ON (p.tenantID=u.tenantID OR u.tenantID = 5)
```

### Has method
A convenient function that returns TRUE if exists at least an element that satisfy the where condition specified calling the "where" method before this one.
```php
$db->where("user", $user);
$db->where("password", md5($password));
if($db->has("users")) {
    return "You are logged";
} else {
    return "Wrong user/password";
}
``` 
### Helper methods
Disconnect from the database:
```php
    $db->disconnect();
```

Reconnect in case mysql connection died:
```php
if (!$db->ping())
    $db->connect()
```

Get last executed SQL query:
Please note that this method returns the SQL query only for debugging purposes as its execution most likely will fail due to missing quotes around char variables.
```php
    $db->get('users');
    echo "Last executed query was ". $db->getLastQuery();
```

Check if table exists:
```php
    if ($db->tableExists ('users'))
        echo "hooray";
```

### Error helpers
After you executed a query you have options to check if there was an error. You can get the MySQL error string or the error code for the last executed query. 
```php
$db->where('login', 'admin')->update('users', ['firstName' => 'Jack']);

if ($db->getLastErrno() === 0)
    echo 'Update succesfull';
else
    echo 'Update failed. Error: '. $db->getLastError();
```
