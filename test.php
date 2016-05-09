<?php

$client = new DZRedis('127.0.0.1','6379');
$result = $client->run('keys *');

echo '<pre>';
print_r($result);
echo '</pre>';
