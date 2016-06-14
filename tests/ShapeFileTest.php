<?php
/**
 * phpMyAdmin ShapeFile library
 * <https://github.com/phpmyadmin/shapefile/>
 *
 * Copyright 2006-2007 Ovidio <ovidio AT users.sourceforge.net>
 * Copyright 2016 Michal Čihař <michal@cihar.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, you can download one from
 * http://www.gnu.org/copyleft/gpl.html.
 */
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
        $this->assertEquals('', $shp->lastError);
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
            array('data/w001n05f.*', 1, 1),
        );
    }

    /**
     * Test error handling in loader
     *
     * @param string $filename name to load
     *
     * @return void
     *
     * @dataProvider provideErrorFiles
     */
    public function testLoadError($filename)
    {
        $shp = new ShapeFile(1);
        $shp->loadFromFile($filename);
        $this->assertNotEquals('', $shp->lastError);
    }

    /**
     * Data provider for file loading error tests.
     *
     * @return array
     */
    public function provideErrorFiles()
    {
        return array(
            array('data/no-dbf.*'),
            array('data/no-shp.*'),
            array('data/invalid-dbf.*'),
            array('data/missing.*'),
        );
    }

    /**
     * Creates test data
     *
     * @return void
     */
    private function createTestData()
    {
        $shp = new ShapeFile(1);

        $record0 = new ShapeRecord(1);
        $record0->addPoint(array("x" => 482131.764567, "y" => 2143634.39608));

        $record1 = new ShapeRecord(11);
        $record1->addPoint(array("x" => 472131.764567, "y" => 2143634.39608, 'z' => 220, 'm' => 120));

        $record2 = new ShapeRecord(21);
        $record2->addPoint(array("x" => 492131.764567, "y" => 2143634.39608, 'z' => 150, 'm' => 80));

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
    }

    /**
     * Tests creating file
     *
     * @return void
     */
    public function testCreate()
    {
        $this->createTestData();

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $this->assertEquals(3, count($shp->records));
    }

    /**
     * Tests creating file
     *
     * @return void
     */
    public function testDelete()
    {
        $this->createTestData();

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $shp->deleteRecord(1);
        $shp->saveToFile();
        $this->assertEquals(2, count($shp->records));

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $this->assertEquals(2, count($shp->records));
    }
}
