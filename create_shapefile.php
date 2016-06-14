<?php
require_once('src/ShapeFile.lib.php');

$shp = new ShapeFile(1, array("xmin" => 464079.002268, "ymin" => 2120153.74792, "xmax" => 505213.52849, "ymax" => 2163205.70036));

$record0 = new ShapeRecord(1);
$record0->addPoint(array("x" => 482131.764567, "y" => 2143634.39608));

$record1 = new ShapeRecord(1);
$record1->addPoint(array("x" => 472131.764567, "y" => 2143634.39608));

$record2 = new ShapeRecord(1);
$record2->addPoint(array("x" => 492131.764567, "y" => 2143634.39608));

$shp->addRecord($record0);
$shp->addRecord($record1);
$shp->addRecord($record2);

$shp->setDBFHeader(array(
						array('ID', 'N', 8, 0),
						array('DESC', 'C', 50, 0)
					));

$shp->records[0]->DBFData['ID'] = '1';
$shp->records[0]->DBFData['DESC'] = 'AAAAAAAAA';

$shp->records[1]->DBFData['ID'] = '2';
$shp->records[1]->DBFData['DESC'] = 'BBBBBBBBBB';

$shp->records[2]->DBFData['ID'] = '3';
$shp->records[2]->DBFData['DESC'] = 'CCCCCCCCCCC';

$shp->saveToFile('data/new_shape.*');

echo "The ShapeFile was created.<br />\n";
echo "Return to the <a href='index.php'>index</a>.";
?>
