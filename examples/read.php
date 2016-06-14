<?php
/**
 * Displays content of given file
 *
 * @param string $filename File to open
 */
function display_file($filename)
{
    $shp = new ShapeFile(1);
    $shp->loadFromFile($filename);

    $i = 1;
    foreach ($shp->records as $i => $record) {
        echo "<pre>";
        echo "Record No. $i:\n\n\n";
        // All the data related to the record
        echo "SHP Data = ";
        print_r($record->SHPData);
        print_r("\n\n\n");
        // All the information related to each record
        echo "DBF Data = ";
        print_r($record->DBFData);
        print_r("\n\n\n");
        echo "</pre>";
    }

    echo "The ShapeFile was completely readed.<br />\n";
    echo "Return to the <a href='index.php'>index</a>.";
}
