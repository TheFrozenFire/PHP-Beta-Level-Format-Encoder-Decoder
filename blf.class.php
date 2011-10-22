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
class BLF extends NBT {
	public $header;

	public function loadFile($filename) {
		$fp = fopen($filename, "rb");
		$this->readHeader($fp);
		return true;
	}
	
	public function writeFile($filename) {
		$fp = fopen($filename, "wb");
		fseek($fp, 8192);
		foreach($header as $location) if(!$this->writeChunk($fp, $location)) return false;
		$this->writeHeader($fp);
		return true;
	}
	
	public function readHeader($fp) {
		$header = array();
		for($headerItem = 0; $headerItem < 1024; $headerItem++) {
			$location = array();
			fseek($fp, $headerItem * 4);
			list(,$location["offset"]) = unpack("N", fread($fp, 3)); // Offset in file
			if($location[0] == 0) continue; // Chunk isn't present
			list(,$location["size"]) = fread($fp, 1); // Size
			fseek($fp, ($headerItem * 4) + 4096);
			list(,$location["time"]) = unpack("N", fread($fp, 4)); // Modification timestamp
			$header[$headerItem] = $location;
		}
	}
	
	public function writeHeader($fp) {
		foreach($this->header as $key => $location) {
			fseek($fp, $key * 4);
			fwrite($fp, substr(pack("N", $location["offset"]), 0, 3)); // Offset in file
			fwrite($fp, pack("c", $location["size"])); // Size
			fseek($fp, ($key * 4) + 4096);
			fwrite($fp, pack("N", $location["time"])); // Modification timestamp
		}
	}
	
	public readChunk($fp, &$location) {
		fseek($fp, $location["offset"]*4096);
		list(,$length) = unpack("N", fread($fp, 4));
		list(,$compression) = unpack("c", fread($fp, 1));
		
		$filter = stream_filter_prepend($fp, "zlib.inflate", STREAM_FILTER_READ);
		
		$this->traverseTag($fp, $this->root);
		$location["chunk"] =& end($this->root);
		
		stream_filter_remove($filter);
	}
	
	public function writeChunk($fp, &$location) {
		$location["offset"] = ftell($fp) / 4096;
		fseek($fp, 4, SEEK_CUR);
		
		$start = ftell($fp);
		fwrite(pack("c", 2));
		
		$filter = stream_filter_prepend($fp, "zlib.deflate", STREAM_FILTER_WRITE);
		
		$this->writeTag($fp, $location["chunk"]);
		
		stream_filter_remove($filter);
		$size = ftell($fp)-$start+1;
		
		fseek($fp, $start-4);
		fwrite(pack("N", $size);
		
		$location["size"] = ceil($size / 4096);
		fseek($fp, $location["offset"] + ($location["size"] * 4096));
	}
}
?>
