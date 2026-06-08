<?php
$conn = @mysqli_connect('127.0.0.1', 'root', '', 'jobfind');
if (!$conn) {
    echo "FAIL\n";
    exit;
}

$result = mysqli_query($conn, 'SHOW TABLES');
$tables = [];
while ($row = mysqli_fetch_row($result)) {
    $tables[] = $row[0];
}
sort($tables, SORT_STRING | SORT_FLAG_CASE);
foreach ($tables as $table) {
    echo $table . PHP_EOL;
}
