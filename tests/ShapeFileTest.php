<?php

declare(strict_types=1);

/**
 * phpMyAdmin ShapeFile library
 * <https://github.com/phpmyadmin/shapefile/>.
 *
 * Copyright 2006-2007 Ovidio <ovidio AT users.sourceforge.net>
 * Copyright 2016 - 2017 Michal Čihař <michal@cihar.com>
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

namespace PhpMyAdminTest\ShapeFile;

use PhpMyAdmin\ShapeFile\ShapeFile;
use PhpMyAdmin\ShapeFile\ShapeRecord;
use PhpMyAdmin\ShapeFile\ShapeType;
use PHPUnit\Framework\TestCase;

use function count;

class ShapeFileTest extends TestCase
{
    /**
     * Tests loading of a file.
     *
     * @param string   $filename Name of file
     * @param int      $records  Expected number of records
     * @param int|null $parts    Expected number of parts in first record
     *
     * @dataProvider provideFiles
     */
    public function testLoad(string $filename, int $records, int|null $parts): void
    {
        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile($filename);
        self::assertEquals('', $shp->lastError);
        self::assertEquals($records, count($shp->records));
        if ($parts === null) {
            return;
        }

        self::assertEquals($parts, count($shp->records[0]->shpData['parts']));
    }

    /**
     * Data provider for file loading tests.
     *
     * @psalm-return list<array{string, int, int|null}>
     */
    public static function provideFiles(): array
    {
        return [
            [
                'data/capitals.*',
                652,
                null,
            ],
            [
                'data/mexico.*',
                32,
                3,
            ],
            [
                'data/Czech_Republic_AL2.*',
                1,
                1,
            ],
            [
                'data/w001n05f.*',
                16,
                1,
            ],
            [
                'data/bc_hospitals.*',
                44,
                null,
            ],
            [
                'data/multipoint.*',
                312,
                null,
            ],
        ];
    }

    /**
     * Test error handling in loader.
     *
     * @param string $filename name to load
     *
     * @dataProvider provideErrorFiles
     */
    public function testLoadError(string $filename): void
    {
        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile($filename);
        self::assertNotEquals('', $shp->lastError);
    }

    /**
     * Test load an empty file name
     */
    public function testLoadEmptyFilename(): void
    {
        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile('');
        if (ShapeFile::supportsDbase()) {
            self::assertEquals('It wasn\'t possible to find the DBase file ""', $shp->lastError);

            return;
        }

        self::assertEquals('Not a SHP file (file code mismatch)', $shp->lastError);
    }

    /**
     * Test to call getDBFHeader on a non loaded file
     */
    public function testGetDBFHeader(): void
    {
        $shp = new ShapeFile(ShapeType::Point);
        self::assertNull($shp->getDBFHeader());
    }

    /**
     * Data provider for file loading error tests.
     *
     * @psalm-return list<array{string}>
     */
    public static function provideErrorFiles(): array
    {
        $result = [
            ['data/no-shp.*'],
            ['data/missing.*'],
            ['data/invalid-shp.*'],
        ];

        if (ShapeFile::supportsDbase()) {
            $result[] = ['data/no-dbf.*'];
            $result[] = ['data/invalid-dbf.*'];
        }

        return $result;
    }

    /**
     * Creates test data.
     */
    private function createTestData(): void
    {
        $shp = new ShapeFile(ShapeType::Point);

        $record0 = new ShapeRecord(ShapeType::Point);
        $record0->addPoint(['x' => 482131.764567, 'y' => 2143634.39608]);

        $record1 = new ShapeRecord(ShapeType::PointZ);
        $record1->addPoint(['x' => 472131.764567, 'y' => 2143634.39608, 'z' => 220, 'm' => 120]);

        $record2 = new ShapeRecord(ShapeType::PointM);
        $record2->addPoint(['x' => 492131.764567, 'y' => 2143634.39608, 'z' => 150, 'm' => 80]);

        $record3 = new ShapeRecord(ShapeType::PolyLine);
        $record3->addPoint(['x' => 482131.764567, 'y' => 2143634.39608], 0);
        $record3->addPoint(['x' => 482132.764567, 'y' => 2143635.39608], 0);
        $record3->addPoint(['x' => 482131.764567, 'y' => 2143635.39608], 1);
        $record3->addPoint(['x' => 482132.764567, 'y' => 2143636.39608], 1);

        $shp->addRecord($record0);
        $shp->addRecord($record1);
        $shp->addRecord($record2);
        $shp->addRecord($record3);

        $shp->setDBFHeader(
            [
                [
                    'ID',
                    'N',
                    8,
                    0,
                ],
                [
                    'DESC',
                    'C',
                    50,
                    0,
                ],
            ],
        );

        $shp->records[0]->dbfData['ID'] = '1';
        $shp->records[0]->dbfData['DESC'] = 'AAAAAAAAA';

        $shp->records[1]->dbfData['ID'] = '2';
        $shp->records[1]->dbfData['DESC'] = 'BBBBBBBBBB';

        $shp->records[2]->dbfData['ID'] = '3';
        $shp->records[2]->dbfData['DESC'] = 'CCCCCCCCCCC';

        $shp->records[3]->dbfData['ID'] = '4';
        $shp->records[3]->dbfData['DESC'] = 'CCCCCCCCCCC';

        $shp->saveToFile('./data/test_shape.*');
    }

    /**
     * Tests creating file.
     */
    public function testCreate(): void
    {
        if (! ShapeFile::supportsDbase()) {
            self::markTestSkipped('dbase extension missing');
        }

        $this->createTestData();

        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile('./data/test_shape.*');
        self::assertEquals(4, count($shp->records));
    }

    /**
     * Tests removing record from a file.
     */
    public function testDelete(): void
    {
        if (! ShapeFile::supportsDbase()) {
            self::markTestSkipped('dbase extension missing');
        }

        $this->createTestData();

        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile('./data/test_shape.*');
        $shp->deleteRecord(1);
        $shp->saveToFile();
        self::assertEquals(3, count($shp->records));

        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile('./data/test_shape.*');
        self::assertEquals(3, count($shp->records));
    }

    /**
     * Test adding record to a file.
     */
    public function testAdd(): void
    {
        if (! ShapeFile::supportsDbase()) {
            self::markTestSkipped('dbase extension missing');
        }

        $this->createTestData();

        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile('./data/test_shape.*');

        $record0 = new ShapeRecord(ShapeType::Point);
        $record0->addPoint(['x' => 482131.764567, 'y' => 2143634.39608]);

        $shp->addRecord($record0);
        $shp->records[4]->dbfData['ID'] = '4';
        $shp->records[4]->dbfData['DESC'] = 'CCCCCCCCCCC';

        $shp->saveToFile();
        self::assertEquals(5, count($shp->records));

        $shp = new ShapeFile(ShapeType::Point);
        $shp->loadFromFile('./data/test_shape.*');
        self::assertEquals(5, count($shp->records));
    }

    /**
     * Tests saving without DBF.
     */
    public function testSaveNoDBF(): void
    {
        $shp = new ShapeFile(ShapeType::Point);
        $shp->saveToFile('./data/test_nodbf.*');

        self::assertFileDoesNotExist('./data/test_nodbf.dbf');
    }

    /**
     * Test shape naming.
     */
    public function testShapeName(): void
    {
        $obj = new ShapeRecord(ShapeType::Point);
        self::assertEquals('Point', $obj->getShapeName());
        $obj = new ShapeFile(ShapeType::Point);
        self::assertEquals('Point', $obj->getShapeName());
        $obj = new ShapeRecord(ShapeType::Null);
        self::assertEquals('Null Shape', $obj->getShapeName());
        $obj = new ShapeRecord(ShapeType::Unknown);
        self::assertEquals('Unknown Shape', $obj->getShapeName());
    }

    /**
     * Test shapes save/load round-robin.
     *
     * @psalm-param list<array{mixed[], int}> $points
     *
     * @dataProvider shapesProvider
     */
    public function testShapeSaveLoad(ShapeType $shapeType, array $points): void
    {
        $filename = './data/test_shape-' . $shapeType->value . '.*';
        $shp = new ShapeFile($shapeType);
        $shp->setDBFHeader([['ID', 'N', 19, 0], ['DESC', 'C', 14, 0]]);

        $record0 = new ShapeRecord($shapeType);

        foreach ($points as $point) {
            $record0->addPoint($point[0], $point[1]);
        }

        $shp->addRecord($record0);

        $shp->saveToFile($filename);

        $shp2 = new ShapeFile($shapeType);
        $shp2->loadFromFile($filename);

        self::assertEquals(count($shp->records), count($shp2->records));

        $record = $shp->records[0];
        $record2 = $shp2->records[0];

        $items = ['numparts', 'numpoints'];
        foreach ($items as $item) {
            if (! isset($record->shpData[$item])) {
                continue;
            }

            self::assertEquals($record->shpData[$item], $record2->shpData[$item]);
        }

        /* Test deletion works */
        $record->deletePoint();
    }

    /**
     * Data provider for save/load testing.
     *
     * @psalm-return list<array{ShapeType, list<array{mixed[], int}>}>
     */
    public static function shapesProvider(): array
    {
        $pointsForPointType = [[['x' => 10, 'y' => 20], 0]];

        $pointsForPolyLineType = [
            [['x' => 10, 'y' => 20], 0],
            [['x' => 20, 'y' => 20], 0],
            [['x' => 20, 'y' => 20], 1],
            [['x' => 20, 'y' => 10], 1],
        ];

        $pointsForPolygonType = [
            [['x' => 10, 'y' => 20], 0],
            [['x' => 20, 'y' => 20], 0],
            [['x' => 20, 'y' => 20], 1],
            [['x' => 20, 'y' => 10], 1],
            [['x' => 20, 'y' => 10], 2],
            [['x' => 10, 'y' => 20], 2],
        ];

        $pointsForMultiPointType = [
            [['x' => 10, 'y' => 20], 0],
            [['x' => 20, 'y' => 20], 0],
            [['x' => 20, 'y' => 10], 0],
        ];

        return [
            [ShapeType::Point, $pointsForPointType],
            [ShapeType::PolyLine, $pointsForPolyLineType],
            [ShapeType::Polygon, $pointsForPolygonType],
            [ShapeType::MultiPoint, $pointsForMultiPointType],
            [ShapeType::PointZ, $pointsForPointType],
            [ShapeType::PolyLineZ, $pointsForPolyLineType],
            [ShapeType::PolygonZ, $pointsForPolygonType],
            [ShapeType::MultiPointZ, $pointsForMultiPointType],
            [ShapeType::PointM, $pointsForPointType],
            [ShapeType::PolyLineM, $pointsForPolyLineType],
            [ShapeType::PolygonM, $pointsForPolygonType],
            [ShapeType::MultiPointM, $pointsForMultiPointType],
        ];
    }

    public function testSearch(): void
    {
        $shp = new ShapeFile(ShapeType::Null);
        $shp->loadFromFile('data/capitals.*');
        /* Nonexisting entry or no dbase support */
        self::assertEquals(
            -1,
            $shp->getIndexFromDBFData('CNTRY_NAME', 'nonexisting'),
        );
        if (! ShapeFile::supportsDbase()) {
            return;
        }

        self::assertEquals(
            218,
            $shp->getIndexFromDBFData('CNTRY_NAME', 'Czech Republic'),
        );
    }

    public function testAllowsNoDbf(): void
    {
        if (! ShapeFile::supportsDbase()) {
            self::markTestSkipped();
        }

        $shp = new ShapeFile(ShapeType::Null);
        $shp->setAllowNoDbf(true);
        self::assertTrue($shp->loadFromFile('data/no-dbf.*'));
    }
}
