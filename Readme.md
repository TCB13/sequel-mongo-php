# Sequel Mongo PHP

A lightweight, expressive, framework agnostic **query builder for PHP** that empowers you to run **SQL-like queries on MongoDB databases**. Enjoy the best of the two worlds!

### Installation

Pull the package via composer.
```shell
$ composer require TCB13/sequel-mongo-php
```

### Usage

**Use `MongoDB\Client` to connect to your Database**:

```php
// Start a MongoDB Client to create a connection to your Database
$mongoConnection = new \MongoDB\Client("mongodb://127.0.0.1", [
	"username" => "user",
	"password" => "pass"
]);

/** @var \MongoDB\Database $mongo */
$mongo = $mongoConnection->selectDatabase("DatabaseName");
```

**Get one item from a collection**:

```php
$qb = new QueryBuilder($mongo);
$qb->collection("Users")
   ->select("_id")
   ->find();
$result = $qb->toObject();
var_dump($result);
```

**Get multiple items**:

```php
$qb = new QueryBuilder($mongo);
$qb->collection("Users")
   ->select("_id")
   ->limit(2)
   ->findAll();
$result = $qb->toObject();
var_dump($result);
```

- `select()` takes the desired fields as an `array` of strings or a variable number of parameters.

**Run a complex query** with multiple `where` conditions, `limit` and `order`.
```php
// Use the Query Builder
$qb = new QueryBuilder($mongo);
$qb->collection("Users")
   ->select("_id", "name", "email", "active")
   ->where("setting", "!=", "other")
   ->where("active", false)
   ->where(function ($qb) {
	   $qb->where("isValid", true)
	      ->orWhere("dateActivated", "!=", null);
   })
   ->limit(100)
   ->order("dateCreated", "ASC")
   ->findAll();
$result = $qb->toObject();
var_dump($result);
```
- If the operator is omitted in `where()` queries, `=` will be used. Eg. `->where("active", false)`;
- You can group `where` conditions by providing closures. Eg. `WHERE id = 1 AND (isValid = true OR dateActivated != null)` can be written as: 
```php
->where("id", 1)
->where(function ($qb) {
    $qb->where("isValid", true)
       ->orWhere("dateActivated", "!=", null);
})
```
- `where()` supports the following operators `=`, `!=`, `>`, `>=`, `<`, `<=`;
- SQL's `WHERE IN()` is also available:
```php
$qb = new QueryBuilder($mongo);
		$qb->collection("Orders")
		   ->whereIn("itemCount", [22,4])
		   ->findAll();
		$result = $qb->toArray();
```
- `WHERE NOT IN()` is also available via `whereNotIn()` and `orWhereNotIn()` respectively;

- Examples of other useful String queries:
```php
->whereStartsWith("item", "start")
->whereEndsWith("item", "end")
->whereContains("item", "-middle-")
->whereRegex("item", ".*-middle-.*")
```
- For more complex String queries you may also use regex:
```php
->whereRegex("item", ".*-middle-.*")
```

**Count Documents**

You may count the number of documents/records that match a query with the `count()` method:

```php
$qb = new QueryBuilder($mongo);
$result = $qb->collection("Users")
   ->where("userid", $this->id)
   ->count();
var_dump($result);
```

Note that you may not call `find()` or `findAll()` in combination with this method.

**Insert a document**:
```php
$qb = new QueryBuilder($mongo);
$result = $qb->collection("TestCollection")
             ->insert([
	             "item" => "test-insert",
	             "xpto" => microtime(true)
             ]);
var_dump($result);
```
- You may also insert multiple documents at once:
```php
$items = [
	[
		"item" => "test-insert-multi",
		"xpto" => microtime(true)
	],
	[
		"item" => "test-insert-multi",
		"xpto" => microtime(true)
	]
];

$qb     = new QueryBuilder($mongo);
$result = $qb->collection("TestCollection")
             ->insert($items);
```

**Delete a Document**:
```php
$qb     = new QueryBuilder($mongo);
$result = $qb->collection("TestCollection")
             ->whereStartsWith("item", "test-insert-")
             ->delete();
var_dump($result);
```

**Update a Document**:
```php
// Update item
$qb     = new QueryBuilder($mongo);
$result = $qb->collection($collection)
             ->where("_id", new MongoID("51ee74e944670a09028d4fc9"))
             ->update([
	             "item" => "updated-value " . microtime(true)
             ]);
var_dump($result);
```
- You may update only a few fields or an entire document - like like an SQL `update` statement.

**Join Collections**:

_**join($collectionToJoin, $localField, $operatorOrForeignField, $foreignField)**_
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Orders")
    //->select("_id", "products#joined.sku")
    //->join(["products" => "products#joined"], "sku", "=", "item"])
    //->join("products", "sku", "=", "item")
   ->join("Products", "sku", "item")
   ->findAll();
$result = $qb->toArray();
var_dump($result);
```

**Special Functions**:

*Max(string $property, ?string $alias = null)* - get the maximum value in a set of values:
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Orders")
   ->select("id", new Max("datecreated", "lastorder"))
   ->where("userid", "u123")
   ->find();
$result = $qb->toArray();
var_dump($result);
```
*Min(string $property, ?string $alias = null)* - get the minimum value in a set of values:
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Orders")
   ->select("id", new Min("datecreated", "lastorder"))
   ->where("userid", "u123")
   ->find();
$result = $qb->toArray();
var_dump($result);
```                
*Increment(string $propertyName, int $incrementBy = 1)* - increment or decrement a document property by a value:
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Orders")
   ->where("id", 12345)
   ->update([
    	new Increment("status")
    ]);
```

*ArrayContains(string $arrayProperty, $needles)* - check if an array in a document contains a value (or at least one value if an array is passed):
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Orders")
   ->select("id")
   ->where(new ArrayContains("prioritaryItems", "123"))
   ->findAll();
$result = $qb->toArray();
var_dump($result);
```
*ArrayLength(string $arrayProperty, ?string $alias = null)* - get the length of an array:
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Orders")
   ->select("id", new ArrayLength("prioritaryItems", "prioritaryItems_lenght"))
   ->where("prioritaryItems_lenght", ">", 0)
   ->findAll();
$result = $qb->toArray();
var_dump($result);
```
*ArrayPush(string $arrayProperty, mixed $value)* - add an element to an array. Example: document with a `tokens` property that is an array:
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Users")
   ->where("id", 123)
   ->update([
        new ArrayPush("tokens", "...")
    ]);
$result = $qb->toArray();
var_dump($result);
```
*ArrayPull(string $arrayProperty, mixed $value)* - remove an element from an array. Example: document with a `tokens` property that is an array:
```php
$qb = new QueryBuilder($mongo);
$qb->collection("Users")
   ->where("id", 123)
   ->update([
        new ArrayPull("tokens", "...")
    ]);
$result = $qb->toArray();
var_dump($result);
```
**Debug Queries**:

It is possible possible to debug the query pipeline built by the Query Builder for each query.
```php
QueryBuilder::$pipelineDebug = true; // Enable pipeline debugging!

// Run a query
$result = (new QueryBuilder())->collection("xyz")
            ->where("active", true)
            ->findAll()
            ->toArray();

// Fetch the pipeline built by the Query Builder
var_dump(QueryBuilder::getLastPipelineLog()); // Get the pipeline built for the last query
var_dump(QueryBuilder::getPipelineLogs()); // Get all pipelines ever built by the query builder
```

**For more examples check out the `examples` directory.**
