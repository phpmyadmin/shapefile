<?php
use ShapeFile\ShapeFile;

require_once '../vendor/autoload.php';

$shp = new ShapeFile(1);
$shp->loadFromFile('../data/mexico.*');

$i = 1;
foreach($shp->records as $record){
    echo "<pre>";
    echo "Record No. $i:\n\n\n";
    echo "SHP Data = ";
    print_r($record->SHPData);   //All the data related to the poligon
    print_r("\n\n\n");
    echo "DBF Data = ";
    print_r($record->DBFData);   //All the information related to each poligon
    print_r("\n\n\n");
    echo "</pre>";
    $i++;
}

echo "The ShapeFile was completely readed.<br />\n";
echo "Return to the <a href='index.php'>index</a>.";
?>
