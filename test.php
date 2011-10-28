<?php
require("NBT/nbt.class.php");
require("blf.class.php");

error_reporting(E_ALL);

$blf = new BLF();
$blf->verbose = true;
$nbt = new NBT();

$nbt->loadFile("NBT/smalltest.nbt");
$nbt->loadFile("NBT/bigtest.nbt");

$blf->header[] = array("offset"=>0, "size"=>0, "time"=>time(), "chunk"=>$nbt->root[0]);
$blf->header[] = array("offset"=>0, "size"=>0, "time"=>time(), "chunk"=>$nbt->root[1]);

$blf->writeFile("test.mcr");

unset($blf, $nbt);

$blf = new BLF();
$blf->verbose = true;

$blf->loadFile("test.mcr");
var_dump($blf->header);
?>
