# PDODb Database Class

A lightweight and efficient PDO database wrapper for PHP 5.6 and above. This class provides an easy-to-use interface for database operations with support for method chaining.

Disclaimer: This database class is completely inspired by the mysqli class project, the responsibility belongs to the user.

Inspired Project:
[PHP-MySQLi-Database-Class](https://github.com/ThingEngineer/PHP-MySQLi-Database-Class/)

## Support Me

This software is developed during my free time and I will be glad if somebody will support me.

Everyone's time should be valuable, so please consider donating.

[https://buymeacoffee.com/oxcakmak](https://buymeacoffee.com/oxcakmak)

## Features
- PDO-based implementation for secure database operations
- Method chaining support
- Prepared statements
- Transaction support
- Join operations
- Where conditions with AND/OR
- Order by and Group by operations
- Limit and offset support
- Error handling
- Table prefix support
- PHP 5.6+ compatibility

### Table of Contents

**[Initialization](#initialization)**  
**[Insert Query](#insert-query)**  
**[Update Query](#update-query)**  
**[Select Query](#select-query)**  
**[Delete Query](#delete-query)**  
**[Where Conditions](#where-conditions)**  
**[Order Conditions](#where-conditions)**  
**[Group Conditions](#where-conditions)**  
**[Joining Tables](#join-method)**  
**[Has method](#has-method)**  
**[Helper Methods](#helper-methods)**  
**[Error Helpers](#error-helpers)**  

## Installation

Include the class file directly:

```php
require_once 'PDODb.php';
```

## Usage Examples

### Initialization
```php
$db = new PDODb('localhost', 'username', 'password', 'database_name');
```

### Insert Query
```php
$data = array(
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created' => date('Y-m-d H:i:s')
);

$db->insert('users', $data);
$lastInsertId = $db->getLastInsertId();
```

### Select Query
```php
// Simple select
$users = $db->get('users');

// Select with where condition
$users = $db->where('age', 25, '>')
           ->where('city', 'New York')
           ->get('users');

// Select specific columns
$users = $db->get('users', null, ['id', 'name', 'email']);

// Get single record
$user = $db->where('id', 1)->getOne('users');
```

### Update Query
```php
$data = array('status' => 'active');
$db->where('id', 1)
   ->update('users', $data);
```

### Delete Query
```php
$db->where('id', 1)->delete('users');
```

### Joining Tables
```php
$results = $db->where('u.id', 1)
             ->join('orders o', 'o.user_id=u.id', 'LEFT')
             ->get('users u');
```

### Transaction Support
```php
try {
    $db->startTransaction();
    
    $db->insert('orders', [
        'user_id' => 1,
        'total' => 99.99
    ]);
    
    $orderId = $db->getLastInsertId();
    
    $db->insert('order_items', [
        'order_id' => $orderId,
        'product_id' => 123
    ]);
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    echo $e->getMessage();
}
```

### Where Conditions
```php
// Equals
$db->where('id', 1);

// Custom operator
$db->where('age', 21, ">=");

// OR condition
$db->where('age', 21, ">=")
   ->orWhere('age', 18, ">=");

// IN condition
$db->where('id', [1, 2, 3], 'IN');
```

### Order Conditions and Group Conditions
```php
$db->orderBy("id", "ASC")
   ->orderBy("name", "DESC")
   ->groupBy("category")
   ->get('products');
```

## Error Handling
```php
if ($db->getLastError()) {
    echo "Error: " . $db->getLastError();
}
```

## Version
Current Version: 1.0.6

## Contributing
Contributions, issues, and feature requests are welcome!

## Support
If you encounter any problems or have suggestions, please open an issue on GitHub.
