<?php

use \MongoDB\Client;
use \SequelMongo\QueryBuilder;

function connectToMongo()
{
	$mongoConnection = new Client("mongodb://127.0.0.1", [
		"username" => "user",
		"password" => "pass"
	]);

	/** @var \MongoDB\Database $mongo */
	return $mongoConnection->selectDatabase("DatabaseName");
}

function find()
{

	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	// Get all items in the collection - as array
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->limit(2)
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);

	// Get all items in the collection - as array of objects
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->limit(4)
	   ->order("_id", "DESC")
	   ->findAll();
	$result = $qb->toObject();
	var_dump($result);

	// Get all items in the collection - as JSON
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->limit(2)
	   ->findAll();
	$result = $qb->toJson();
	var_dump($result);

	// Get top 1 - as array
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->find();
	$result = $qb->toArray();
	var_dump($result);

	// Get top 1 - as JSON
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->find();
	$result = $qb->toJson();
	var_dump($result);

	// Get top 1 - as Object
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->find();
	$result = $qb->toObject();
	var_dump($result);
}

function findSelect()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->select("_id")
	   ->limit(2)
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);
}

function findLimitOffset()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->select("_id")
	   ->limit(1)
	   ->offset(2)
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);
}

function findWhere()
{

	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->where("item", "value")
	   ->where("xpto", "!=", "other")
	   ->limit(2)
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);

	print "\n--------------------------------------\n";
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->orWhere("item", "not")
	   ->orWhere("xpto", "r")
	   ->limit(2)
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);

	print "\n--------------------------------------\n";
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->whereIn("xpto", [
		   "22",
		   4
	   ])
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);

	print "\n--------------------------------------\n";
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->whereRegex("item", ".*-son-.*")
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);

	print "\n--------------------------------------\n";
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->whereContains("item", "-son-")
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);

	print "\n--------------------------------------\n";
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->whereStartsWith("item", "sd")
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);

	print "\n--------------------------------------\n";
	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->whereEndsWith("xpto", "end")
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);
}

function findWhereNoResults()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->where("item", microtime())
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);
}

function findWhereNoResult()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->where("item", microtime())
	   ->find();
	$result = $qb->toArray();
	var_dump($result);
}

function nestedFindWhere()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	$qb = new QueryBuilder($mongo);
	$qb->collection($collection)
	   ->select("item", "xpto")
	   ->whereStartsWith("item", "value")
	   ->where(function ($qb) {
		   $qb->where("xpto", "not")
		      ->orWhere("xpto", "22");
	   })
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);
}

function insert()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	$qb     = new QueryBuilder($mongo);
	$result = $qb->collection($collection)
	             ->insert([
		             "item" => "test-insert",
		             "xpto" => microtime(true)
	             ]);
	var_dump($result);

	print "\n--------------------------------------\n";

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
	$result = $qb->collection($collection)
	             ->insert($items);
	var_dump($result);
}

function delete()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	// Insert an item to delete
	$qb     = new QueryBuilder($mongo);
	$result = $qb->collection($collection)
	             ->insert([
		             "item" => "test-delete",
		             "xpto" => microtime(true)
	             ]);
	var_dump($result);
	print "\n--------------------------------------\n";

	// Delete item
	$qb     = new QueryBuilder($mongo);
	$result = $qb->collection($collection)
	             ->where("item", "test-delete")
	             ->delete();
	var_dump($result);
	// Delete by ID, [ "_id" => new MongoID("51ee74e944670a09028d4fc9") ]
}

function update()
{

	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	// Insert an item to update later
	$qb           = new QueryBuilder($mongo);
	$insertResult = $qb->collection($collection)
	                   ->insert([
		                   "item" => "test-update",
		                   "xpto" => "original-value " . microtime(true)
	                   ]);
	var_dump($insertResult);
	print "\n--------------------------------------\n";

	// Update item
	$qb     = new QueryBuilder($mongo);
	$result = $qb->collection($collection)
	             ->where("_id", $insertResult->getInsertedIds()[0])
	             ->update([
		             "xpto" => "updated-value " . microtime(true)
	             ]);
	var_dump($result);
}

function addDataAgg()
{

	$orders = \json_decode('[
		{ "_id" : 1, "item" : "abc", "price" : 12, "quantity" : 2 },
		{ "_id" : 2, "item" : "jkl", "price" : 20, "quantity" : 1 },
		{ "_id" : 3  }
		]');

	$products = \json_decode('[
		{ "_id" : 1, "sku" : "abc", "description": "product 1", "instock" : 120 },
		{ "_id" : 2, "sku" : "def", "description": "product 2", "instock" : 80 },
		{ "_id" : 3, "sku" : "ijk", "description": "product 3", "instock" : 60 },
		{ "_id" : 4, "sku" : "jkl", "description": "product 4", "instock" : 70 },
		{ "_id" : 5, "sku": null, "description": "Incomplete" },
		{ "_id" : 6 }
		]');

	/** @var \MongoDB\Database $mongo */
	$mongo = connectToMongo();

	// Insert orders
	$qb     = new QueryBuilder($mongo);
	$result = $qb->collection("orders")
	             ->insert($orders);
	var_dump($result);

	// Insert Products
	$qb     = new QueryBuilder($mongo);
	$result = $qb->collection("products")
	             ->insert($products);
	var_dump($result);
}

function join()
{
	/** @var \MongoDB\Database $mongo */
	$mongo      = connectToMongo();
	$collection = "test";

	// Mongo equivalent query: {"$lookup":{"from":"products","let":{"SEQUELMONGOForeignFieldAlias":"$item"},"pipeline":[{"$match":{"$expr":{"$and":{"$eq":["$sku","$$SEQUELMONGOForeignFieldAlias"]}}}}],"as":"products#joined"}}

	$qb = new QueryBuilder($mongo);
	$qb->collection("orders")
	   ->select("_id", "products#joined.sku")
		//->join(["products" => "products#joined"], "sku", "=", "item"])
		//->join("products", "sku", "=", "item")
	   ->join("products", "sku", "item")
	   ->findAll();
	$result = $qb->toArray();
	var_dump($result);
}
