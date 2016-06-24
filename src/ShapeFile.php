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
namespace ShapeFile;

/**
 * ShapeFile class
 *
 * @package ShapeFile
 */
class ShapeFile {
    public $FileName;

    private $SHPFile = null;
    private $SHXFile = null;
    private $DBFFile = null;

    private $DBFHeader;

    public $lastError = '';

    public $boundingBox = array('xmin' => 0.0, 'ymin' => 0.0, 'xmax' => 0.0, 'ymax' => 0.0);
    private $fileLength = 0;
    public $shapeType = 0;

    public $records;

    static $shape_names = array(
        0 => 'Null Shape',
        1 => 'Point',
        3 => 'PolyLine',
        5 => 'Polygon',
        8 => 'MultiPoint',
        11 => 'PointZ',
        13 => 'PolyLineZ',
        15 => 'PolygonZ',
        18 => 'MultiPointZ',
        21 => 'PointM',
        23 => 'PolyLineM',
        25 => 'PolygonM',
        28 => 'MultiPointM',
        31 => 'MultiPatch',
    );

    /**
     * Checks whether dbase manipuations are supported.
     *
     * @return bool
     */
    public static function supports_dbase()
    {
        return extension_loaded('dbase');
    }

    /**
     * @param integer $shapeType
     */
    public function __construct($shapeType, $boundingBox = array('xmin' => 0.0, 'ymin' => 0.0, 'xmax' => 0.0, 'ymax' => 0.0), $FileName = null) {
        $this->shapeType = $shapeType;
        $this->boundingBox = $boundingBox;
        $this->FileName = $FileName;
        $this->fileLength = 50; // The value for file length is the total length of the file in 16-bit words (including the fifty 16-bit words that make up the header).
    }

    /**
     * @param string $FileName
     */
    public function loadFromFile($FileName) {
        if (!empty($FileName)) {
            $this->FileName = $FileName;
            $result = $this->_openSHPFile();
        } else {
            /* We operate on buffer emulated by readSHP / eofSHP */
            $result = true;
        }

        if ($result && ($this->_openDBFFile())) {
            if (!$this->_loadHeaders()) {
                $this->_closeSHPFile();
                $this->_closeDBFFile();
                return false;
            }
            if (!$this->_loadRecords()) {
                $this->_closeSHPFile();
                $this->_closeDBFFile();
                return false;
            }
            $this->_closeSHPFile();
            $this->_closeDBFFile();
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string|null $FileName Name of file to open
     */
    public function saveToFile($FileName = null) {
        if (!is_null($FileName)) {
            $this->FileName = $FileName;
        }

        if (($this->_openSHPFile(true)) && ($this->_openSHXFile(true)) && ($this->_openDBFFile(true))) {
            $this->_saveHeaders();
            $this->_saveRecords();
            $this->_closeSHPFile();
            $this->_closeSHXFile();
            $this->_closeDBFFile();
        } else {
            return false;
        }
    }

    /**
     * Generates filename with given extension
     *
     * @param string $extension Extension to use (including dot)
     *
     * @return string
     */
    private function _getFilename($extension)
    {
        return str_replace('.*', $extension, $this->FileName);
    }

    /**
     * @param ShapeRecord $record
     */
    public function addRecord($record) {
        if ((isset($this->DBFHeader)) && (is_array($this->DBFHeader))) {
            $record->updateDBFInfo($this->DBFHeader);
        }

        $this->fileLength += ($record->getContentLength() + 4);
        $this->records[] = $record;
        $this->records[count($this->records) - 1]->recordNumber = count($this->records);

        if ($this->boundingBox['xmin'] == 0.0 || ($this->boundingBox['xmin'] > $record->SHPData['xmin'])) {
            $this->boundingBox['xmin'] = $record->SHPData['xmin'];
        }
        if ($this->boundingBox['xmax'] == 0.0 || ($this->boundingBox['xmax'] < $record->SHPData['xmax'])) {
            $this->boundingBox['xmax'] = $record->SHPData['xmax'];
        }

        if ($this->boundingBox['ymin'] == 0.0 || ($this->boundingBox['ymin'] > $record->SHPData['ymin'])) {
            $this->boundingBox['ymin'] = $record->SHPData['ymin'];
        }
        if ($this->boundingBox['ymax'] == 0.0 || ($this->boundingBox['ymax'] < $record->SHPData['ymax'])) {
            $this->boundingBox['ymax'] = $record->SHPData['ymax'];
        }

        if (in_array($this->shapeType, array(11, 13, 15, 18, 21, 23, 25, 28))) {
            if (!isset($this->boundingBox['mmin']) || $this->boundingBox['mmin'] == 0.0 || ($this->boundingBox['mmin'] > $record->SHPData['mmin'])) {
                $this->boundingBox['mmin'] = $record->SHPData['mmin'];
            }
            if (!isset($this->boundingBox['mmax']) || $this->boundingBox['mmax'] == 0.0 || ($this->boundingBox['mmax'] < $record->SHPData['mmax'])) {
                $this->boundingBox['mmax'] = $record->SHPData['mmax'];
            }
        }

        if (in_array($this->shapeType, array(11, 13, 15, 18))) {
            if (!isset($this->boundingBox['zmin']) || $this->boundingBox['zmin'] == 0.0 || ($this->boundingBox['zmin'] > $record->SHPData['zmin'])) {
                $this->boundingBox['zmin'] = $record->SHPData['zmin'];
            }
            if (!isset($this->boundingBox['zmax']) || $this->boundingBox['zmax'] == 0.0 || ($this->boundingBox['zmax'] < $record->SHPData['zmax'])) {
                $this->boundingBox['zmax'] = $record->SHPData['zmax'];
            }
        }

        return (count($this->records) - 1);
    }

    public function deleteRecord($index) {
        if (isset($this->records[$index])) {
            $this->fileLength -= ($this->records[$index]->getContentLength() + 4);
            $count = count($this->records) - 1;
            for ($i = $index; $i < $count; $i++) {
                $this->records[$i] = $this->records[$i + 1];
            }
            unset($this->records[count($this->records) - 1]);
            $this->_deleteRecordFromDBF($index);
        }
    }

    public function getDBFHeader() {
        return $this->DBFHeader;
    }

    public function setDBFHeader($header) {
        $this->DBFHeader = $header;

        $count = count($this->records);
        for ($i = 0; $i < $count; $i++) {
            $this->records[$i]->updateDBFInfo($header);
        }
    }

    public function getIndexFromDBFData($field, $value) {
        $result = -1;
        $count = count($this->records) - 1;
        for ($i = 0; $i < $count; $i++) {
            if (isset($this->records[$i]->DBFData[$field]) && (strtoupper($this->records[$i]->DBFData[$field]) == strtoupper($value))) {
                $result = $i;
            }
        }

        return $result;
    }

    private function _loadDBFHeader() {
        $DBFFile = fopen($this->_getFilename('.dbf'), 'r');

        $result = array();
        $i = 1;
        $inHeader = true;

        while ($inHeader) {
            if (!feof($DBFFile)) {
                $buff32 = fread($DBFFile, 32);
                if ($i > 1) {
                    if (substr($buff32, 0, 1) == chr(13)) {
                        $inHeader = false;
                    } else {
                        $pos = strpos(substr($buff32, 0, 10), chr(0));
                        $pos = ($pos == 0 ? 10 : $pos);

                        $fieldName = substr($buff32, 0, $pos);
                        $fieldType = substr($buff32, 11, 1);
                        $fieldLen = ord(substr($buff32, 16, 1));
                        $fieldDec = ord(substr($buff32, 17, 1));

                        array_push($result, array($fieldName, $fieldType, $fieldLen, $fieldDec));
                    }
                }
                $i++;
            } else {
                $inHeader = false;
            }
        }

        fclose($DBFFile);
        return($result);
    }

    private function _deleteRecordFromDBF($index) {
        if (@dbase_delete_record($this->DBFFile, $index)) {
            @dbase_pack($this->DBFFile);
        }
    }

    private function _loadHeaders() {
        if (Util::loadData('N', $this->readSHP(4)) != 9994) {
            $this->setError('Not a SHP file (file code mismatch)');
            return false;
        }

        /* Skip 20 unused bytes */
        $this->readSHP(20);

        $this->fileLength = Util::loadData('N', $this->readSHP(4));

        /* We currently ignore version */
        $this->readSHP(4);

        $this->shapeType = Util::loadData('V', $this->readSHP(4));

        $this->boundingBox = array();
        $this->boundingBox['xmin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['ymin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['xmax'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['ymax'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['zmin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['zmax'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['mmin'] = Util::loadData('d', $this->readSHP(8));
        $this->boundingBox['mmax'] = Util::loadData('d', $this->readSHP(8));

        if (ShapeFile::supports_dbase()) {
            $this->DBFHeader = $this->_loadDBFHeader();
        }
        return true;
    }

    private function _saveHeaders() {
        fwrite($this->SHPFile, pack('NNNNNN', 9994, 0, 0, 0, 0, 0));
        fwrite($this->SHPFile, pack('N', $this->fileLength));
        fwrite($this->SHPFile, pack('V', 1000));
        fwrite($this->SHPFile, pack('V', $this->shapeType));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['xmin']));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['ymin']));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['xmax']));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['ymax']));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['zmin']) ? $this->boundingBox['zmin'] : 0));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['zmax']) ? $this->boundingBox['zmax'] : 0));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['mmin']) ? $this->boundingBox['mmin'] : 0));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['mmax']) ? $this->boundingBox['mmax'] : 0));

        fwrite($this->SHXFile, pack('NNNNNN', 9994, 0, 0, 0, 0, 0));
        fwrite($this->SHXFile, pack('N', 50 + 4 * count($this->records)));
        fwrite($this->SHXFile, pack('V', 1000));
        fwrite($this->SHXFile, pack('V', $this->shapeType));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['xmin']));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['ymin']));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['xmax']));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['ymax']));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['zmin']) ? $this->boundingBox['zmin'] : 0));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['zmax']) ? $this->boundingBox['zmax'] : 0));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['mmin']) ? $this->boundingBox['mmin'] : 0));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['mmax']) ? $this->boundingBox['mmax'] : 0));
    }

    private function _loadRecords() {
        /* Need to start at offset 100 */
        while (!$this->eofSHP()) {
            $record = new ShapeRecord(-1);
            $record->loadFromFile($this, $this->SHPFile, $this->DBFFile);
            if ($record->lastError != '') {
                $this->setError($record->lastError);
                return false;
            }
            if ($record->shapeType === '' && $this->eofSHP()) {
                break;
            }

            $this->records[] = $record;
        }
        return true;
    }

    private function _saveRecords() {
        if (!ShapeFile::supports_dbase()) {
            return;
        }
        $dbf_name = $this->_getFilename('.dbf');
        if (file_exists($dbf_name)) {
            @unlink($dbf_name);
        }
        if (!($this->DBFFile = @dbase_create($dbf_name, $this->DBFHeader))) {
            return $this->setError(sprintf('It wasn\'t possible to create the DBase file "%s"', $dbf_name));
        }

        $offset = 50;
        if (is_array($this->records) && (count($this->records) > 0)) {
            foreach ($this->records as $index => $record) {
                //Save the record to the .shp file
                $record->saveToFile($this->SHPFile, $this->DBFFile, $index + 1);

                //Save the record to the .shx file
                fwrite($this->SHXFile, pack('N', $offset));
                fwrite($this->SHXFile, pack('N', $record->getContentLength()));
                $offset += (4 + $record->getContentLength());
            }
        }
        @dbase_pack($this->DBFFile);
    }

    private function _openSHPFile($toWrite = false) {
        $shp_name = $this->_getFilename('.shp');
        $this->SHPFile = @fopen($shp_name, ($toWrite ? 'wb+' : 'rb'));
        if (!$this->SHPFile) {
            return $this->setError(sprintf('It wasn\'t possible to open the Shape file "%s"', $shp_name));
        }

        return true;
    }

    private function _closeSHPFile() {
        if ($this->SHPFile) {
            fclose($this->SHPFile);
            $this->SHPFile = null;
        }
    }

    private function _openSHXFile($toWrite = false) {
        $shx_name = $this->_getFilename('.shx');
        $this->SHXFile = @fopen($shx_name, ($toWrite ? 'wb+' : 'rb'));
        if (!$this->SHXFile) {
            return $this->setError(sprintf('It wasn\'t possible to open the Index file "%s"', $shx_name));
        }

        return true;
    }

    private function _closeSHXFile() {
        if ($this->SHXFile) {
            fclose($this->SHXFile);
            $this->SHXFile = null;
        }
    }

    /**
     * Loads DBF file if supported
     *
     * @return bool
     */
    private function _openDBFFile($toWrite = false) {
        if (!ShapeFile::supports_dbase()) {
            return true;
        }
        $dbf_name = $this->_getFilename('.dbf');
        $checkFunction = $toWrite ? 'is_writable' : 'is_readable';
        if (($toWrite) && (!file_exists($dbf_name))) {
            if (!@dbase_create($dbf_name, $this->DBFHeader)) {
                return $this->setError(sprintf('It wasn\'t possible to create the DBase file "%s"', $dbf_name));
            }
        }
        if ($checkFunction($dbf_name)) {
            $this->DBFFile = @dbase_open($dbf_name, ($toWrite ? 2 : 0));
            if (!$this->DBFFile) {
                return $this->setError(sprintf('It wasn\'t possible to open the DBase file "%s"', $dbf_name));
            }
        } else {
            return $this->setError(sprintf('It wasn\'t possible to find the DBase file "%s"', $dbf_name));
        }
        return true;
    }

    private function _closeDBFFile() {
        if ($this->DBFFile) {
            dbase_close($this->DBFFile);
            $this->DBFFile = null;
        }
    }

    /**
     * Sets error message
     *
     * @param string $error
     *
     * @return bool
     */
    public function setError($error) {
        $this->lastError = $error;
        return false;
    }

    /**
     * Reads given number of bytes from SHP file
     *
     * @return string|false
     */
    public function readSHP($bytes)
    {
        return fread($this->SHPFile, $bytes);
    }

    /**
     * Checks whether file is at EOF
     *
     * @return bool
     */
    public function eofSHP()
    {
        return feof($this->SHPFile);
    }

    /**
     * Returns shape name
     *
     * @return string
     */
    public function getShapeName()
    {
        return ShapeFile::nameShape($this->shapeType);
    }

    public static function nameShape($type)
    {
        if (isset(self::$shape_names[$type])) {
            return self::$shape_names[$type];
        }
        return sprintf('Shape %d', $type);
    }
}

