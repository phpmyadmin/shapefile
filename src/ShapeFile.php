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

namespace PhpMyAdmin\ShapeFile;

use function chr;
use function count;
use function extension_loaded;
use function fclose;
use function feof;
use function file_exists;
use function fopen;
use function fread;
use function fwrite;
use function in_array;
use function is_readable;
use function ord;
use function pack;
use function sprintf;
use function str_replace;
use function strpos;
use function strtoupper;
use function substr;
use function trim;
use function unlink;

/**
 * ShapeFile class.
 */
class ShapeFile
{
    public final const MAGIC = 0x270a;

    /** @var resource|false */
    private $shpFile = false;

    /** @var resource|false */
    private $shxFile = false;

    /** @var resource|false */
    private $dbfFile = false;

    /** @var mixed[]|null */
    private array|null $dbfHeader = null;

    public string $lastError = '';

    /**
     * The value for file length is the total length of the file in 16-bit words
     * (including the fifty 16-bit words that make up the header).
     */
    private int $fileLength = 50;

    /** @var array<int, ShapeRecord> */
    public array $records = [];

    /**
     * Checks whether dbase manipulations are supported.
     */
    public static function supportsDbase(): bool
    {
        return extension_loaded('dbase');
    }

    /**
     * @param int         $shapeType   File shape type, should be same as all records
     * @param mixed[]     $boundingBox File bounding box
     * @param string|null $fileName    File name
     */
    public function __construct(
        public int $shapeType,
        public array $boundingBox = [
            'xmin' => 0.0,
            'ymin' => 0.0,
            'xmax' => 0.0,
            'ymax' => 0.0,
        ],
        public string|null $fileName = null,
    ) {
    }

    /**
     * Loads shapefile and dbase (if supported).
     *
     * @param string $fileName File mask to load (eg. example.*)
     */
    public function loadFromFile(string $fileName): bool
    {
        if ($fileName !== '') {
            $this->fileName = $fileName;
            $result = $this->openSHPFile();
        } else {
            /* We operate on buffer emulated by readSHP / eofSHP */
            $result = true;
        }

        if ($result && ($this->openDBFFile())) {
            if (! $this->loadHeaders()) {
                $this->closeSHPFile();
                $this->closeDBFFile();

                return false;
            }

            if (! $this->loadRecords()) {
                $this->closeSHPFile();
                $this->closeDBFFile();

                return false;
            }

            $this->closeSHPFile();
            $this->closeDBFFile();

            return true;
        }

        return false;
    }

    /**
     * Saves shapefile.
     *
     * @param string|null $fileName Name of file, otherwise existing is used
     */
    public function saveToFile(string|null $fileName = null): bool
    {
        if ($fileName !== null) {
            $this->fileName = $fileName;
        }

        if (! $this->openSHPFile(true) || (! $this->openSHXFile(true)) || (! $this->createDBFFile())) {
            return false;
        }

        $this->saveHeaders();
        $this->saveRecords();
        $this->closeSHPFile();
        $this->closeSHXFile();
        $this->closeDBFFile();

        return true;
    }

    /**
     * Generates filename with given extension.
     *
     * @param string $extension Extension to use (including dot)
     */
    private function getFilename(string $extension): string
    {
        return str_replace('.*', $extension, (string) $this->fileName);
    }

    /**
     * Updates bounding box based on shpData.
     *
     * @param string  $type Type of box
     * @param mixed[] $data ShapeRecord shpData
     */
    private function updateBBox(string $type, array $data): void
    {
        $min = $type . 'min';
        $max = $type . 'max';

        if (
            ! isset($this->boundingBox[$min])
            || $this->boundingBox[$min] == 0.0 // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators
            || ($this->boundingBox[$min] > $data[$min])
        ) {
            $this->boundingBox[$min] = $data[$min];
        }

        if (
            isset($this->boundingBox[$max])
            && $this->boundingBox[$max] != 0.0 // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators
            && ($this->boundingBox[$max] >= $data[$max])
        ) {
            return;
        }

        $this->boundingBox[$max] = $data[$max];
    }

    /**
     * Adds record to shape file.
     *
     * @return int Number of added record
     */
    public function addRecord(ShapeRecord $record): int
    {
        if ($this->dbfHeader !== null) {
            $record->updateDBFInfo($this->dbfHeader);
        }

        $this->fileLength += $record->getContentLength() + 4;
        $this->records[] = $record;
        $this->records[count($this->records) - 1]->recordNumber = count($this->records);

        $this->updateBBox('x', $record->shpData);
        $this->updateBBox('y', $record->shpData);

        if (in_array($this->shapeType, ShapeType::MEASURED_TYPES, true)) {
            $this->updateBBox('m', $record->shpData);
        }

        if (in_array($this->shapeType, ShapeType::TYPES_WITH_Z, true)) {
            $this->updateBBox('z', $record->shpData);
        }

        return count($this->records) - 1;
    }

    /**
     * Deletes record from shapefile.
     */
    public function deleteRecord(int $index): void
    {
        if (! isset($this->records[$index])) {
            return;
        }

        $this->fileLength -= $this->records[$index]->getContentLength() + 4;
        $count = count($this->records) - 1;
        for ($i = $index; $i < $count; ++$i) {
            $this->records[$i] = $this->records[$i + 1];
        }

        unset($this->records[count($this->records) - 1]);
        $this->deleteRecordFromDBF($index);
    }

    /**
     * Returns array defining fields in DBF file.
     *
     * @return mixed[]|null see setDBFHeader for more information
     */
    public function getDBFHeader(): array|null
    {
        return $this->dbfHeader;
    }

    /**
     * Changes array defining fields in DBF file, used in dbase_create call.
     *
     * @param mixed[] $header An array of arrays, each array describing the
     *                        format of one field of the database. Each
     *                        field consists of a name, a character indicating
     *                        the field type, and optionally, a length,
     *                        a precision and a nullable flag.
     */
    public function setDBFHeader(array $header): void
    {
        $this->dbfHeader = $header;

        foreach ($this->records as $record) {
            $record->updateDBFInfo($header);
        }
    }

    /**
     * Lookups value in the DBF file and returns index.
     */
    public function getIndexFromDBFData(string $field, string $value): int
    {
        foreach ($this->records as $index => $record) {
            if (
                isset($record->dbfData[$field]) &&
                (trim(strtoupper($record->dbfData[$field])) === strtoupper($value))
            ) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * Loads DBF metadata.
     *
     * @return array{string, string, int, int}[]
     */
    private function loadDBFHeader(): array
    {
        $dbfFile = fopen($this->getFilename('.dbf'), 'r');
        if ($dbfFile === false) {
            return [];
        }

        $result = [];
        $i = 1;

        while (true) {
            if (feof($dbfFile)) {
                break;
            }

            $buff32 = fread($dbfFile, 32);
            if ($i > 1) {
                if (substr($buff32, 0, 1) === chr(13)) {
                    break;
                }

                $pos = strpos(substr($buff32, 0, 10), chr(0));
                $pos = ($pos === false ? 10 : $pos);

                $fieldName = substr($buff32, 0, $pos);
                $fieldType = substr($buff32, 11, 1);
                $fieldLen = ord(substr($buff32, 16, 1));
                $fieldDec = ord(substr($buff32, 17, 1));

                $result[] = [$fieldName, $fieldType, $fieldLen, $fieldDec];
            }

            ++$i;
        }

        fclose($dbfFile);

        return $result;
    }

    /**
     * Deletes record from the DBF file.
     */
    private function deleteRecordFromDBF(int $index): void
    {
        if ($this->dbfFile === false || ! @dbase_delete_record($this->dbfFile, $index)) {
            return;
        }

        dbase_pack($this->dbfFile);
    }

    /**
     * Loads SHP file metadata.
     */
    private function loadHeaders(): bool
    {
        if (Util::loadData('N', $this->readSHP(4)) !== self::MAGIC) {
            $this->setError('Not a SHP file (file code mismatch)');

            return false;
        }

        /* Skip 20 unused bytes */
        $this->readSHP(20);

        $this->fileLength = (int) Util::loadData('N', $this->readSHP(4));

        /* We currently ignore version */
        $this->readSHP(4);

        $shapeType = Util::loadData('V', $this->readSHP(4));
        $this->shapeType = $shapeType === false ? -1 : (int) $shapeType;

        $this->boundingBox = [];
        $this->boundingBox['xmin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['ymin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['xmax'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['ymax'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['zmin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['zmax'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['mmin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['mmax'] = Util::loadData('d', $this->readSHP(8));

        if (self::supportsDbase()) {
            $this->dbfHeader = $this->loadDBFHeader();
        }

        return true;
    }

    /**
     * Saves bounding box record, possibly using 0 instead of not set values.
     *
     * @param resource $file File object
     * @param string   $type Bounding box dimension (eg. xmax, mmin...)
     */
    private function saveBBoxRecord($file, string $type): void
    {
        fwrite($file, Util::packDouble(
            $this->boundingBox[$type] ?? 0,
        ));
    }

    /**
     * Saves bounding box to a file.
     *
     * @param resource $file File object
     */
    private function saveBBox($file): void
    {
        $this->saveBBoxRecord($file, 'xmin');
        $this->saveBBoxRecord($file, 'ymin');
        $this->saveBBoxRecord($file, 'xmax');
        $this->saveBBoxRecord($file, 'ymax');
        $this->saveBBoxRecord($file, 'zmin');
        $this->saveBBoxRecord($file, 'zmax');
        $this->saveBBoxRecord($file, 'mmin');
        $this->saveBBoxRecord($file, 'mmax');
    }

    /**
     * Saves SHP and SHX file metadata.
     */
    private function saveHeaders(): void
    {
        if ($this->shpFile === false) {
            return;
        }

        fwrite($this->shpFile, pack('NNNNNN', self::MAGIC, 0, 0, 0, 0, 0));
        fwrite($this->shpFile, pack('N', $this->fileLength));
        fwrite($this->shpFile, pack('V', 1000));
        fwrite($this->shpFile, pack('V', $this->shapeType));
        $this->saveBBox($this->shpFile);

        if ($this->shxFile === false) {
            return;
        }

        fwrite($this->shxFile, pack('NNNNNN', self::MAGIC, 0, 0, 0, 0, 0));
        fwrite($this->shxFile, pack('N', 50 + 4 * count($this->records)));
        fwrite($this->shxFile, pack('V', 1000));
        fwrite($this->shxFile, pack('V', $this->shapeType));
        $this->saveBBox($this->shxFile);
    }

    /**
     * Loads records from SHP file (and DBF).
     */
    private function loadRecords(): bool
    {
        /* Need to start at offset 100 */
        while (! $this->eofSHP()) {
            $record = new ShapeRecord(-1);
            $record->loadFromFile($this, $this->dbfFile);
            if ($record->lastError !== '') {
                $this->setError($record->lastError);

                return false;
            }

            if (($record->shapeType === -1) && $this->eofSHP()) {
                break;
            }

            $this->records[] = $record;
        }

        return true;
    }

    /**
     * Saves records to SHP and SHX files.
     */
    private function saveRecords(): void
    {
        $offset = 50;
        if ($this->records === [] || $this->shxFile === false || $this->shpFile === false) {
            return;
        }

        foreach ($this->records as $index => $record) {
            //Save the record to the .shp file
            $record->saveToFile($this->shpFile, $this->dbfFile, $index + 1);

            //Save the record to the .shx file
            fwrite($this->shxFile, pack('N', $offset));
            fwrite($this->shxFile, pack('N', $record->getContentLength()));
            $offset += 4 + $record->getContentLength();
        }
    }

    /**
     * Generic interface to open files.
     *
     * @param bool   $toWrite   Whether file should be opened for writing
     * @param string $extension File extension
     * @param string $name      Verbose file name to report errors
     *
     * @return resource|false File handle
     */
    private function openFile(bool $toWrite, string $extension, string $name)
    {
        $shpName = $this->getFilename($extension);
        $result = @fopen($shpName, ($toWrite ? 'wb+' : 'rb'));
        if (! $result) {
            $this->setError(sprintf('It wasn\'t possible to open the %s file "%s"', $name, $shpName));

            return false;
        }

        return $result;
    }

    /**
     * Opens SHP file.
     *
     * @param bool $toWrite Whether file should be opened for writing
     */
    private function openSHPFile(bool $toWrite = false): bool
    {
        $this->shpFile = $this->openFile($toWrite, '.shp', 'Shape');

        return (bool) $this->shpFile;
    }

    /**
     * Closes SHP file.
     */
    private function closeSHPFile(): void
    {
        if ($this->shpFile === false) {
            return;
        }

        fclose($this->shpFile);
        $this->shpFile = false;
    }

    /**
     * Opens SHX file.
     *
     * @param bool $toWrite Whether file should be opened for writing
     */
    private function openSHXFile(bool $toWrite = false): bool
    {
        $this->shxFile = $this->openFile($toWrite, '.shx', 'Index');

        return (bool) $this->shxFile;
    }

    /**
     * Closes SHX file.
     */
    private function closeSHXFile(): void
    {
        if ($this->shxFile === false) {
            return;
        }

        fclose($this->shxFile);
        $this->shxFile = false;
    }

    /**
     * Creates DBF file.
     */
    private function createDBFFile(): bool
    {
        if (! self::supportsDbase() || $this->dbfHeader === null || $this->dbfHeader === []) {
            $this->dbfFile = false;

            return true;
        }

        $dbfName = $this->getFilename('.dbf');

        /* Unlink existing file */
        if (file_exists($dbfName)) {
            unlink($dbfName);
        }

        /* Create new file */
        $this->dbfFile = @dbase_create($dbfName, $this->dbfHeader);
        if ($this->dbfFile === false) {
            $this->setError(sprintf('It wasn\'t possible to create the DBase file "%s"', $dbfName));

            return false;
        }

        return true;
    }

    /**
     * Loads DBF file if supported.
     */
    private function openDBFFile(): bool
    {
        if (! self::supportsDbase()) {
            $this->dbfFile = false;

            return true;
        }

        $dbfName = $this->getFilename('.dbf');
        if (! is_readable($dbfName)) {
            $this->setError(sprintf('It wasn\'t possible to find the DBase file "%s"', $dbfName));

            return false;
        }

        $this->dbfFile = @dbase_open($dbfName, 0);
        if ($this->dbfFile === false) {
            $this->setError(sprintf('It wasn\'t possible to open the DBase file "%s"', $dbfName));

            return false;
        }

        return true;
    }

    /**
     * Closes DBF file.
     */
    private function closeDBFFile(): void
    {
        if ($this->dbfFile === false) {
            return;
        }

        dbase_close($this->dbfFile);
        $this->dbfFile = false;
    }

    /**
     * Sets error message.
     */
    public function setError(string $error): void
    {
        $this->lastError = $error;
    }

    /**
     * Reads given number of bytes from SHP file.
     *
     * @param int<0, max> $bytes
     */
    public function readSHP(int $bytes): string|false
    {
        if ($this->shpFile === false) {
            return false;
        }

        return fread($this->shpFile, $bytes);
    }

    /**
     * Checks whether file is at EOF.
     */
    public function eofSHP(): bool
    {
        return feof($this->shpFile);
    }

    /**
     * Returns shape name.
     *
     * @psalm-return non-empty-string
     */
    public function getShapeName(): string
    {
        return ShapeType::name($this->shapeType);
    }

    /**
     * Check whether file contains measure data.
     *
     * For some reason this is distinguished by zero bounding box in the
     * specification.
     */
    public function hasMeasure(): bool
    {
        // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedNotEqualOperator
        return $this->boundingBox['mmin'] != 0 || $this->boundingBox['mmax'] != 0;
    }
}
