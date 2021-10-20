# PicoDatabase

SQL builder.

- create database object: `new PicoDatabase([host, [username, [password, [database, [encoding]]]]]);`

- basic query: `query('UPDATE table SET field = 1')`

- query with placeholders: `query('UPDATE table SET ?@ = ?_', 'field', '123')` = ``` UPDATE table SET `field` = '123' ```

- possible placeholders:
  - `?_` - parameter will be escaped as value (`123\456'789` --> `'123\\456\'789'`)
  - `?@` - parameter will be escaped as field name (`some_field` --> ``` `some_field` ```)
  - `?*` - parameter will be inserted as-is
  - `??` - "?" will be inserted

- to start build query: `select()`, `insert()`, `update()`, `delete()`, `replace()` or `buildQuery()`

- to finish query: `execute()` or `fetch[Val/All/Col]`

- simple chain: `select('field')->from('table')->fetchAll()`

- each chain call will be translated to SQL operator. Spaces will be added before big letters. `buildQuery()->optimizeTable('table')` = `OPTIMIZE TABLE table`

- one or several call parameters (strings or numbers, without placeholders) will be just added to the query separated by comma: `select('field1', 'field2')` = `SELECT field1, field2`

- several same calls are exactly the same as one call with all parameters: `select('field1')->select('field2')` = `SELECT field1, field2`

- non-associative array also will be processed as several parameters: `select(array('field1', 'field2'))` = `SELECT field1, field2`

- exceptions are `where`, `on` and `having`. They will be added to the query separated by `AND`: `select('*')->from('table')->where(array('field1', 'field2'))` = `SELECT field FROM table WHERE field1 AND field2`

- exceptions are `where`, `set`, `on` and `having`. Call with several parameters will be processed as associative array with first parameter as key and second as value (see below): `select('*')->from('table')->where('field1', 1)` = `SELECT * FROM table WHERE field1 = '1'`

- if first parameter contains placeholders ("?" symbol) then other parameters (only in that call) will be used as parameters for placeholders: `select('?@', 'field1', 'field2')` = ``` SELECT `field1` ``` (yes, we lost `field2` because we have only one placeholder)

- all elements from an array (of any type - associative or not) which is used as placeholder parameter will be processed as specified in the placeholder and added to the query separated by comma: `select('?@', array('field1', 'field2'))` = ``` SELECT `field1`, `field2` ```

- associative arrays are processed by keys:
  - if key does not contain placeholders then it will generate `(<key> = '<escaped value>)'` (`=` will not be added if key contains `=`, `>` or `<`)
  - if value is an array then `IN` will be used instead of `=`, all elements will be escaped and added to the query separated by comma and in brackets
  - if key contains placeholders then values (they should be in array, non-array value will be used as array with one element) will be used as parameters for placeholders: 
  - one array can have different types of keys
  ```
  select('*')->from('table')->where(array(
    'field1' => 'val1',
    'field2 >' => 'val2',
    'field3' => array('val3.1', 'val3.2'),
    'field4 = ?* + ?_' => array('NOW()', 1)
  ))
  ```
  =
  ```
  SELECT * FROM table
    WHERE (field1 = 'val1')
    AND (field2 > 'val2')
    AND (field3 IN ('val3.1', 'val3.2'))
    AND (field4 = NOW() + '1')"
  ```

- `set()` is automatically added to insert/update/replace: `update('table', array('field1' => 1))` = `UPDATE table SET field1 = '1'`
