<?php
use ShapeFile\ShapeFile;

class ShapeMoFilesTest extends PHPUnit_Framework_TestCase
{
    /**
     * Tests loading of a file
     *
     * @param string  $filename Name of file
     * @param integer $records  Expected number of records
     *
     * @return void
     *
     * @dataProvider provideFiles
     */
    public function testLoad($filename, $records)
    {
        $shp = new ShapeFile(1);
        $shp->loadFromFile($filename);
        $this->assertEquals($records, count($shp->records));
    }

    /**
     * Data provider for file loading tests.
     *
     * @return array
     */
    public function provideFiles()
    {
        return array(
            array('data/capitals.*', 652),
            array('data/mexico.*', 32),
        );
    }
}
