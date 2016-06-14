<?php
require_once('lib/ShapeFile.lib.php');

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
					), 'data/new_shape.dbf');

$shp->records[0]->DBFData['ID_DEN'] = '1';
$shp->records[0]->DBFData['EXPEDIENTE'] = 'AAAAAAAAA';

$shp->records[1]->DBFData['ID_DEN'] = '2';
$shp->records[1]->DBFData['EXPEDIENTE'] = 'BBBBBBBBBB';

$shp->records[2]->DBFData['ID_DEN'] = '3';
$shp->records[2]->DBFData['EXPEDIENTE'] = 'CCCCCCCCCCC';

$shp->saveToFile('data/new_shape.shp', 'data/new_shape.shx', 'data/new_shape.dbf');
}
?>