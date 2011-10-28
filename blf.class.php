<?php
/**
 * Class for reading in Beta-Level-Format region files.
 * 
 * @author  Justin Martin <frozenfire@thefrozenfire.com>
 * @version 1.0
 *
 * Dependencies:
 *  PHP 4.3+ (5.3+ recommended)
 *  NBT class (https://github.com/TheFrozenFire/PHP-NBT-Decoder-Encoder)
 */
class BLF {
	public $verbose = false;
	public $header = array(); // Array ( "offset" => int, "size" => int, "time" => int, "chunk" => NBT::root[] )

	public function loadFile($filename, $readall = true) {
		if($this->verbose) trigger_error("Loading file \"{$filename}\".", E_USER_NOTICE);
		$fp = fopen($filename, "rb");
		if($this->verbose) trigger_error("Loading header from file.", E_USER_NOTICE);
		$this->readHeader($fp);
		if($readall) {
			if($this->verbose) trigger_error("Loading all chunks.", E_USER_NOTICE);
			foreach($this->header as &$location) $this->readChunk($fp, $location);
		} else {
			if($this->verbose) trigger_error("Returning file resource.", E_USER_NOTICE);
			return $fp;
		}
	}
	
	public function writeFile($filename) {
		if($this->verbose) trigger_error("Writing ".count($this->header)." chunks to \"{$filename}\".", E_USER_NOTICE);
		$fp = fopen($filename, "wb");
		fseek($fp, 8192);
		foreach($this->header as $locationNum => &$location) if(!$this->writeChunk($fp, $location)) {
			trigger_error("Failed to write chunk #{$locationNum}.", E_USER_WARNING);
			return false;
		}
		if($this->verbose) trigger_error("Writing header to file.", E_USER_NOTICE);
		$this->writeHeader($fp);
		return true;
	}
	
	public function readHeader($fp) {
		$this->header = array();
		for($headerItem = 0; $headerItem < 1024; $headerItem++) {
			$location = array();
			fseek($fp, $headerItem * 4);
			list(,$location["offset"]) = unpack("N", "\0".fread($fp, 3)); // Offset in file
			if($location["offset"] == 0) continue; // Chunk isn't present
			if($this->verbose) trigger_error("Reading header item starting at offset ".($headerItem*4).".", E_USER_NOTICE);
			list(,$location["size"]) = unpack("C", fread($fp, 1)); // Size
			fseek($fp, ($headerItem * 4) + 4096);
			list(,$location["time"]) = unpack("N", fread($fp, 4)); // Modification timestamp
			if($this->verbose) trigger_error("Header item {\"offset\"=>{$location["offset"]}, \"size\"=>{$location["size"]}, \"time\"=>{$location["time"]}}", E_USER_NOTICE);
			$this->header[] = $location;
		}
	}
	
	public function writeHeader($fp) {
		foreach($this->header as $key => $location) {
			if($this->verbose) trigger_error("Header item #{$key} being written at offset ".($key * 4).", with timestamp at ".($key * 4 + 4096)." {\"offset\"=>{$location["offset"]}, \"size\"=>{$location["size"]}, \"time\"=>{$location["time"]}}", E_USER_NOTICE);
			fseek($fp, $key * 4);
			fwrite($fp, substr(pack("N", $location["offset"]), 1, 3)); // Offset in file
			fwrite($fp, pack("C", $location["size"])); // Size
			fseek($fp, $key * 4 + 4096);
			fwrite($fp, pack("N", $location["time"])); // Modification timestamp
		}
	}
	
	public function readChunk($fp, &$location) {
		fseek($fp, $location["offset"]*4096);
		list(,$length) = unpack("N", fread($fp, 4));
		list(,$compression) = unpack("c", fread($fp, 1));
		
		if($this->verbose) trigger_error("Reading chunk at offset ".($location["offset"]*4096)." of {$length} bytes using compression #{$compression}.", E_USER_NOTICE);

		$data = gzuncompress(stream_get_contents($fp, $length));
		$temp = fopen("php://temp", "r+");
		fwrite($temp, $data);
		rewind($temp);
		unset($data);

		$nbt = new NBT();
		$location["chunk"] = $nbt->loadFile($temp, null);
	}
	
	public function writeChunk($fp, &$location) {
		$location["offset"] = floor(ftell($fp) / 4096);
		if($this->verbose) trigger_error("Chunk is being written at offset ".ftell($fp).".", E_USER_NOTICE);
		fseek($fp, 4, SEEK_CUR);

		$start = ftell($fp);
		fwrite($fp, pack("c", 2));

		$temp = fopen("php://temp", "r+");
		$nbt = new NBT();
		$nbt->writeTag($temp, $location["chunk"]);
		rewind($temp);
		$compressed = gzcompress(stream_get_contents($temp)); // Round-about, yes, I know.
		fclose($temp);
		fwrite($fp, $compressed);

		$size = ftell($fp)-($start+1);
		fseek($fp, $start-4);
		fwrite($fp, pack("N", $size));

		$location["size"] = ceil($size / 4096);
		fseek($fp, ($location["offset"] * 4096) + ($location["size"] * 4096));
		return true;
	}
}
?>
