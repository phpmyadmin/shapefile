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
 * https://www.gnu.org/copyleft/gpl.html.
 */
namespace ShapeFileTest;

use ShapeFile\ShapeFile;
use ShapeFile\ShapeRecord;

class ShapeFileTest extends \PHPUnit_Framework_TestCase
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
        $this->assertEquals('', $shp->lastError);
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
            array('data/w001n05f.*', 16, 1),
            array('data/bc_hospitals.*', 44, null),
            array('data/multipoint.*', 312, null),
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
        $result = array(
            array('data/no-shp.*'),
            array('data/missing.*'),
        );

        if (ShapeFile::supports_dbase()) {
            $result[] = array('data/no-dbf.*');
            $result[] = array('data/invalid-dbf.*');
        }

        return $result;
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

        $record3 = new ShapeRecord(3);
        $record3->addPoint(array("x" => 482131.764567, "y" => 2143634.39608), 0);
        $record3->addPoint(array("x" => 482132.764567, "y" => 2143635.39608), 0);
        $record3->addPoint(array("x" => 482131.764567, "y" => 2143635.39608), 1);
        $record3->addPoint(array("x" => 482132.764567, "y" => 2143636.39608), 1);

        $shp->addRecord($record0);
        $shp->addRecord($record1);
        $shp->addRecord($record2);
        $shp->addRecord($record3);

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

        $shp->records[3]->DBFData['ID'] = '4';
        $shp->records[3]->DBFData['DESC'] = 'CCCCCCCCCCC';

        $shp->saveToFile('./data/test_shape.*');
    }

    /**
     * Tests creating file
     *
     * @return void
     */
    public function testCreate()
    {
        if (!ShapeFile::supports_dbase()) {
            $this->markTestSkipped('dbase extension missing');
        }
        $this->createTestData();

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $this->assertEquals(4, count($shp->records));
    }

    /**
     * Tests removing record from a file
     *
     * @return void
     */
    public function testDelete()
    {
        if (!ShapeFile::supports_dbase()) {
            $this->markTestSkipped('dbase extension missing');
        }
        $this->createTestData();

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $shp->deleteRecord(1);
        $shp->saveToFile();
        $this->assertEquals(3, count($shp->records));

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $this->assertEquals(3, count($shp->records));
    }

    /**
     * Test adding record to a file
     *
     * @return void
     */
    public function testAdd()
    {
        if (!ShapeFile::supports_dbase()) {
            $this->markTestSkipped('dbase extension missing');
        }
        $this->createTestData();

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');

        $record0 = new ShapeRecord(1);
        $record0->addPoint(array("x" => 482131.764567, "y" => 2143634.39608));

        $shp->addRecord($record0);
        $shp->records[4]->DBFData['ID'] = '4';
        $shp->records[4]->DBFData['DESC'] = 'CCCCCCCCCCC';

        $shp->saveToFile();
        $this->assertEquals(5, count($shp->records));

        $shp = new ShapeFile(1);
        $shp->loadFromFile('./data/test_shape.*');
        $this->assertEquals(5, count($shp->records));
    }

    /**
     * Test shape naming.
     *
     * @return void
     */
    public function testShapeName()
    {
        $obj = new ShapeRecord(1);
        $this->assertEquals('Point', $obj->getShapeName());
        $obj = new Shapefile(1);
        $this->assertEquals('Point', $obj->getShapeName());
        $obj = new ShapeRecord(-1);
        $this->assertEquals('Shape -1', $obj->getShapeName());
    }

    /**
     * Test shapes save/load round robin
     *
     * @param int   $type   Shape type
     * @param array $points Points
     *
     * @return void
     *
     * @dataProvider shapes
     */
    public function testShapeSaveLoad($type, $points)
    {
        $filename = "./data/test_shape-$type.*";
        $shp = new ShapeFile($type);
        $shp->setDBFHeader(array(
            array('ID', 'N', 19, 0),
            array('DESC', 'C', 14, 0),
        ));

        $record0 = new ShapeRecord($type);

        foreach ($points as $point) {
            $record0->addPoint($point[0], $point[1]);
        }

        $shp->addRecord($record0);

        $shp->saveToFile($filename);

        $shp2 = new ShapeFile($type);
        $shp2->loadFromFile($filename);

        $this->assertEquals(
            count($shp->records),
            count($shp2->records)
        );

        $record = $shp->records[0];
        $record2 = $shp2->records[0];

        $items = array('numparts', 'numpoints');
        foreach ($items as $item) {
            if (isset($record->SHPData[$item])) {
                $this->assertEquals(
                    $record->SHPData[$item],
                    $record2->SHPData[$item]
                );
            }
        }

        /* Test deletion works */
        $record->deletePoint();
    }

    /**
     * Test shapes save/load round robin with z coordinate
     *
     * @param int   $type   Shape type
     * @param array $points Points
     *
     * @return void
     *
     * @dataProvider shapes
     */
    public function testZetShapeSaveLoad($type, $points)
    {
        $this->testShapeSaveLoad($type + 10, $points);
    }

    /**
     * Test shapes save/load round robin with measure
     *
     * @param int   $type   Shape type
     * @param array $points Points
     *
     * @return void
     *
     * @dataProvider shapes
     */
    public function testMeasureShapeSaveLoad($type, $points)
    {
        $this->testShapeSaveLoad($type + 20, $points);
    }

    /**
     * Data provider for save/load testing
     *
     * @return array
     */
    public function shapes()
    {
        return array(
            array(
                1,
                array(
                    array(array('x' => 10, 'y' => 20), 0),
                )
            ),
            array(
                3,
                array(
                    array(array('x' => 10, 'y' => 20), 0),
                    array(array('x' => 20, 'y' => 20), 0),
                    array(array('x' => 20, 'y' => 20), 1),
                    array(array('x' => 20, 'y' => 10), 1),
                )
            ),
            array(
                5,
                array(
                    array(array('x' => 10, 'y' => 20), 0),
                    array(array('x' => 20, 'y' => 20), 0),
                    array(array('x' => 20, 'y' => 20), 1),
                    array(array('x' => 20, 'y' => 10), 1),
                    array(array('x' => 20, 'y' => 10), 2),
                    array(array('x' => 10, 'y' => 20), 2),
                )
            ),
            array(
                8,
                array(
                    array(array('x' => 10, 'y' => 20), 0),
                    array(array('x' => 20, 'y' => 20), 0),
                    array(array('x' => 20, 'y' => 10), 0),
                )
            ),
        );
    }
}
