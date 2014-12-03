json_query allows you to query JSON flat files in a limited but SQL ORM like way. json_query treats directories of JSON files like databases and individual files in the directory as treated tables. Everything is lazy loaded, so no files get read into memory until they are actually queried. Additionally, queries may be cached to file. Each JSON file should contain an array of JSON objects. The first object defines the "schema". All keys of the first object are treated as that table's row names.

See example/index.php for slightly more detailed explanation and example usage.

See json_query.php for more detailed class documenation.