<?php
/**
 * BytesFall ShapeFiles library
 *
 * The library implements the 2D variants of the ShapeFile format as defined in
 * http://www.esri.com/library/whitepapers/pdfs/shapefile.pdf.
 * The library currently supports reading and editing of ShapeFiles and the
 * Associated information (DBF file).
 *
 * @package bfShapeFiles
 * @version 0.0.2
 * @link http://bfshapefiles.sourceforge.net/
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2-or-later
 *
 * Copyright 2006-2007 Ovidio <ovidio AT users.sourceforge.net>
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
 *
 */
namespace ShapeFile;

/**
 * ShapeFile class
 *
 * @package bfShapeFiles
 */
class ShapeFile {
    private $FileName;

    private $SHPFile;
    private $SHXFile;
    private $DBFFile;

    private $DBFHeader;

    public $lastError = "";

    private $boundingBox = array("xmin" => 0.0, "ymin" => 0.0, "xmax" => 0.0, "ymax" => 0.0);
    private $fileLength = 0;
    private $shapeType = 0;

    public $records;

    public function __construct($shapeType, $boundingBox = array("xmin" => 0.0, "ymin" => 0.0, "xmax" => 0.0, "ymax" => 0.0), $FileName = NULL) {
        $this->shapeType = $shapeType;
        $this->boundingBox = $boundingBox;
        $this->FileName = $FileName;
        $this->fileLength = 50; // The value for file length is the total length of the file in 16-bit words (including the fifty 16-bit words that make up the header).
    }

    public function loadFromFile($FileName) {
        $this->FileName = $FileName;

        if (($this->_openSHPFile()) && ($this->_openDBFFile())) {
            $this->_loadHeaders();
            $this->_loadRecords();
            $this->_closeSHPFile();
            $this->_closeDBFFile();
        } else {
            return false;
        }
    }

    public function saveToFile($FileName = NULL) {
        if ($FileName != NULL) $this->FileName = $FileName;

        if (($this->_openSHPFile(TRUE)) && ($this->_openSHXFile(TRUE)) && ($this->_openDBFFile(TRUE))) {
            $this->_saveHeaders();
            $this->_saveRecords();
            $this->_closeSHPFile();
            $this->_closeSHXFile();
            $this->_closeDBFFile();
        } else {
            return false;
        }
    }

    public function addRecord($record) {
        if ((isset($this->DBFHeader)) && (is_array($this->DBFHeader))) {
            $record->updateDBFInfo($this->DBFHeader);
        }

        $this->fileLength += ($record->getContentLength() + 4);
        $this->records[] = $record;
        $this->records[count($this->records) - 1]->recordNumber = count($this->records);

        if ($this->boundingBox["xmin"]==0.0 || ($this->boundingBox["xmin"]>$record->SHPData["xmin"])) $this->boundingBox["xmin"] = $record->SHPData["xmin"];
        if ($this->boundingBox["xmax"]==0.0 || ($this->boundingBox["xmax"]<$record->SHPData["xmax"])) $this->boundingBox["xmax"] = $record->SHPData["xmax"];

        if ($this->boundingBox["ymin"]==0.0 || ($this->boundingBox["ymin"]>$record->SHPData["ymin"])) $this->boundingBox["ymin"] = $record->SHPData["ymin"];
        if ($this->boundingBox["ymax"]==0.0 || ($this->boundingBox["ymax"]<$record->SHPData["ymax"])) $this->boundingBox["ymax"] = $record->SHPData["ymax"];

        if (in_array($this->shapeType,array(11,13,15,18,21,23,25,28))) {
            if (!isset($this->boundingBox["mmin"]) || $this->boundingBox["mmin"]==0.0 || ($this->boundingBox["mmin"]>$record->SHPData["mmin"])) $this->boundingBox["mmin"] = $record->SHPData["mmin"];
            if (!isset($this->boundingBox["mmax"]) || $this->boundingBox["mmax"]==0.0 || ($this->boundingBox["mmax"]<$record->SHPData["mmax"])) $this->boundingBox["mmax"] = $record->SHPData["mmax"];
        }

        if (in_array($this->shapeType,array(11,13,15,18))) {
            if (!isset($this->boundingBox["zmin"]) || $this->boundingBox["zmin"]==0.0 || ($this->boundingBox["zmin"]>$record->SHPData["zmin"])) $this->boundingBox["zmin"] = $record->SHPData["zmin"];
            if (!isset($this->boundingBox["zmax"]) || $this->boundingBox["zmax"]==0.0 || ($this->boundingBox["zmax"]<$record->SHPData["zmax"])) $this->boundingBox["zmax"] = $record->SHPData["zmax"];
        }

        return (count($this->records) - 1);
    }

    public function deleteRecord($index) {
        if (isset($this->records[$index])) {
            $this->fileLength -= ($this->records[$index]->getContentLength() + 4);
            for ($i = $index; $i < (count($this->records) - 1); $i++) {
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

        for ($i = 0; $i < count($this->records); $i++) {
            $this->records[$i]->updateDBFInfo($header);
        }
    }

    public function getIndexFromDBFData($field, $value) {
        $result = -1;
        for ($i = 0; $i < (count($this->records) - 1); $i++) {
            if (isset($this->records[$i]->DBFData[$field]) && (strtoupper($this->records[$i]->DBFData[$field]) == strtoupper($value))) {
                $result = $i;
            }
        }

        return $result;
    }

    private function _loadDBFHeader() {
        $DBFFile = fopen(str_replace('.*', '.dbf', $this->FileName), 'r');

        $result = array();
        $buff32 = array();
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
        fseek($this->SHPFile, 24, SEEK_SET);
        $this->fileLength = Util::loadData("N", fread($this->SHPFile, 4));

        fseek($this->SHPFile, 32, SEEK_SET);
        $this->shapeType = Util::loadData("V", fread($this->SHPFile, 4));

        $this->boundingBox = array();
        $this->boundingBox["xmin"] = Util::loadData("d", fread($this->SHPFile, 8));
        $this->boundingBox["ymin"] = Util::loadData("d", fread($this->SHPFile, 8));
        $this->boundingBox["xmax"] = Util::loadData("d", fread($this->SHPFile, 8));
        $this->boundingBox["ymax"] = Util::loadData("d", fread($this->SHPFile, 8));
        $this->boundingBox["zmin"] = Util::loadData("d", fread($this->SHPFile, 8));
        $this->boundingBox["zmax"] = Util::loadData("d", fread($this->SHPFile, 8));
        $this->boundingBox["mmin"] = Util::loadData("d", fread($this->SHPFile, 8));
        $this->boundingBox["mmax"] = Util::loadData("d", fread($this->SHPFile, 8));

        $this->DBFHeader = $this->_loadDBFHeader();
    }

    private function _saveHeaders() {
        fwrite($this->SHPFile, pack("NNNNNN", 9994, 0, 0, 0, 0, 0));
        fwrite($this->SHPFile, pack("N", $this->fileLength));
        fwrite($this->SHPFile, pack("V", 1000));
        fwrite($this->SHPFile, pack("V", $this->shapeType));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['xmin']));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['ymin']));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['xmax']));
        fwrite($this->SHPFile, Util::packDouble($this->boundingBox['ymax']));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['zmin'])?$this->boundingBox['zmin']:0));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['zmax'])?$this->boundingBox['zmax']:0));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['mmin'])?$this->boundingBox['mmin']:0));
        fwrite($this->SHPFile, Util::packDouble(isset($this->boundingBox['mmax'])?$this->boundingBox['mmax']:0));

        fwrite($this->SHXFile, pack("NNNNNN", 9994, 0, 0, 0, 0, 0));
        fwrite($this->SHXFile, pack("N", 50 + 4*count($this->records)));
        fwrite($this->SHXFile, pack("V", 1000));
        fwrite($this->SHXFile, pack("V", $this->shapeType));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['xmin']));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['ymin']));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['xmax']));
        fwrite($this->SHXFile, Util::packDouble($this->boundingBox['ymax']));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['zmin'])?$this->boundingBox['zmin']:0));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['zmax'])?$this->boundingBox['zmax']:0));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['mmin'])?$this->boundingBox['mmin']:0));
        fwrite($this->SHXFile, Util::packDouble(isset($this->boundingBox['mmax'])?$this->boundingBox['mmax']:0));
    }

    private function _loadRecords() {
        fseek($this->SHPFile, 100);
        while (!feof($this->SHPFile)) {
            $bByte = ftell($this->SHPFile);
            $record = new ShapeRecord(-1);
            $record->loadFromFile($this->SHPFile, $this->DBFFile);
            $eByte = ftell($this->SHPFile);
            if (($eByte <= $bByte) || ($record->lastError != "")) {
                return false;
            }

            $this->records[] = $record;
        }
    }

    private function _saveRecords() {
        if (file_exists(str_replace('.*', '.dbf', $this->FileName))) {
            @unlink(str_replace('.*', '.dbf', $this->FileName));
        }
        if (!($this->DBFFile = @dbase_create(str_replace('.*', '.dbf', $this->FileName), $this->DBFHeader))) {
            return $this->setError(sprintf("It wasn't possible to create the DBase file '%s'", str_replace('.*', '.dbf', $this->FileName)));
        }

        $offset = 50;
        if (is_array($this->records) && (count($this->records) > 0)) {
            reset($this->records);
            while (list($index, $record) = each($this->records)) {
                //Save the record to the .shp file
                $record->saveToFile($this->SHPFile, $this->DBFFile, $index + 1);

                //Save the record to the .shx file
                fwrite($this->SHXFile, pack("N", $offset));
                fwrite($this->SHXFile, pack("N", $record->getContentLength()));
                $offset += (4 + $record->getContentLength());
            }
        }
        @dbase_pack($this->DBFFile);
    }

    private function _openSHPFile($toWrite = false) {
        $this->SHPFile = @fopen(str_replace('.*', '.shp', $this->FileName), ($toWrite ? "wb+" : "rb"));
        if (!$this->SHPFile) {
            return $this->setError(sprintf("It wasn't possible to open the Shape file '%s'", str_replace('.*', '.shp', $this->FileName)));
        }

        return TRUE;
    }

    private function _closeSHPFile() {
        if ($this->SHPFile) {
            fclose($this->SHPFile);
            $this->SHPFile = NULL;
        }
    }

    private function _openSHXFile($toWrite = false) {
        $this->SHXFile = @fopen(str_replace('.*', '.shx', $this->FileName), ($toWrite ? "wb+" : "rb"));
        if (!$this->SHXFile) {
            return $this->setError(sprintf("It wasn't possible to open the Index file '%s'", str_replace('.*', '.shx', $this->FileName)));
        }

        return TRUE;
    }

    private function _closeSHXFile() {
        if ($this->SHXFile) {
            fclose($this->SHXFile);
            $this->SHXFile = NULL;
        }
    }

    private function _openDBFFile($toWrite = false) {
        $checkFunction = $toWrite ? "is_writable" : "is_readable";
        if (($toWrite) && (!file_exists(str_replace('.*', '.dbf', $this->FileName)))) {
            if (!@dbase_create(str_replace('.*', '.dbf', $this->FileName), $this->DBFHeader)) {
                return $this->setError(sprintf("It wasn't possible to create the DBase file '%s'", str_replace('.*', '.dbf', $this->FileName)));
            }
        }
        if ($checkFunction(str_replace('.*', '.dbf', $this->FileName))) {
            $this->DBFFile = dbase_open(str_replace('.*', '.dbf', $this->FileName), ($toWrite ? 2 : 0));
            if (!$this->DBFFile) {
                return $this->setError(sprintf("It wasn't possible to open the DBase file '%s'", str_replace('.*', '.dbf', $this->FileName)));
            }
        } else {
            return $this->setError(sprintf("It wasn't possible to find the DBase file '%s'", str_replace('.*', '.dbf', $this->FileName)));
        }
        return TRUE;
    }

    private function _closeDBFFile() {
        if ($this->DBFFile) {
            dbase_close($this->DBFFile);
            $this->DBFFile = NULL;
        }
    }

    public function setError($error) {
        $this->lastError = $error;
        return false;
    }
}

