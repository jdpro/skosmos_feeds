<?php
$devPath = __DIR__ . '/../vendor/semsol';
$path = Drupal::root().'/../vendor/semsol';
set_include_path(get_include_path() . PATH_SEPARATOR . $devPath. PATH_SEPARATOR . $path);
require_once 'arc2/ARC2.php';