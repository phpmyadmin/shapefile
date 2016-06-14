<?php
require_once('lib/ShapeFile.lib.php');

$shp = new ShapeFile(1); 
$shp->loadFromFile('data/capitals.shp', 'data/capitals.shx', 'data/capitals.dbf');

$i = 1;
foreach($shp->records as $record){
   echo "<pre>";
   echo "Record No. $i:\n\n\n";
   echo "SHP Data = ";
   print_r($record->SHPData);   //All the data related to the point
   print_r("\n\n\n");
   echo "DBF Data = ";
   print_r($record->DBFData);   //All the information related to each point
   print_r("\n\n\n");
   echo "</pre>";
   $i++;
}
?>