<?php
use ShapeFile\ShapeFile;
use ShapeFile\ShapeRecord;

class ShapeFileTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests loading of a file
     *
     * @param string  $filename Name of file
     * @param integer $records  Expected number of records
     * @param integer $parts    Expected number of parts in first record
     *
     * @return void
     *
     * @dataProvider provideFiles
     */
    public function testLoad($filename, $records, $parts)
    {
        $shp = new ShapeFile(1);
        $shp->loadFromFile($filename);
        $this->assertEquals($records, count($shp->records));
        if (!is_null($parts)) {
            $this->assertEquals($parts, count($shp->records[0]->SHPData["parts"]));
        }
    }

    /**
     * Data provider for file loading tests.
     *
     * @return array
     */
    public function provideFiles()
    {
        return array(
            array('data/capitals.*', 652, null),
            array('data/mexico.*', 32, 3),
            array('data/Czech_Republic_AL2.*', 1, 1),
        );
    }

    /**
     * Tests creating file
     *
     * @return void
     */
    public function testCreate()
    {
        $shp = new ShapeFile(1);

        $record0 = new ShapeRecord(1);
        $record0->addPoint(array("x" => 482131.764567, "y" => 2143634.39608));

        $record1 = new ShapeRecord(1);
        $record1->addPoint(array("x" => 472131.764567, "y" => 2143634.39608));

        $record2 = new ShapeRecord(1);
        $record2->addPoint(array("x" => 492131.764567, "y" => 2143634.39608));

        $shp->addRecord($record0);
        $shp->addRecord($record1);
        $shp->addRecord($record2);

        $shp->setDBFHeader(
            array(
                array('ID', 'N', 8, 0),
                array('DESC', 'C', 50, 0)
            )
        );

        $shp->records[0]->DBFData['ID'] = '1';
        $shp->records[0]->DBFData['DESC'] = 'AAAAAAAAA';

        $shp->records[1]->DBFData['ID'] = '2';
        $shp->records[1]->DBFData['DESC'] = 'BBBBBBBBBB';

        $shp->records[2]->DBFData['ID'] = '3';
        $shp->records[2]->DBFData['DESC'] = 'CCCCCCCCCCC';

        $shp->saveToFile('./data/test_shape.*');

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $this->assertEquals(3, count($shp->records));
    }
}
