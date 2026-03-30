<?php

$self = basename(__FILE__);

function scanAndPrint($dir, $self) {
    $items = scandir($dir);

    foreach ($items as $item) {

        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if ($item === $self) {
            continue;
        }

        if (is_file($path)) {
            echo "File path: $path\n\n";
            echo "File contents:\n";
            echo file_get_contents($path);
            echo "\n\n-----------------------------\n\n";
        }

        if (is_dir($path)) {
            scanAndPrint($path, $self);
        }
    }
}

scanAndPrint(__DIR__, $self);
