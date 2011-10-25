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
	public $header;

	public function loadFile($filename, $readall = true) {
		$fp = fopen($filename, "rb");
		$this->readHeader($fp);
		if($readall) foreach($this->header as $location) $this->readChunk($fp, $location); else return $fp;
	}
	
	public function writeFile($filename) {
		$fp = fopen($filename, "wb");
		fseek($fp, 8192);
		foreach($header as $location) if(!$this->writeChunk($fp, $location)) return false;
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
			list(,$location["size"]) = unpack("c", fread($fp, 1)); // Size
			fseek($fp, ($headerItem * 4) + 4096);
			list(,$location["time"]) = unpack("N", fread($fp, 4)); // Modification timestamp
			$this->header[$headerItem] = $location;
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
	
	public function readChunk($fp, &$location) {
		fseek($fp, $location["offset"]*4096);
		list(,$length) = unpack("N", fread($fp, 4));
		list(,$compression) = unpack("c", fread($fp, 1));

		$data = gzuncompress(stream_get_contents($fp, $length));
		$temp = fopen("php://temp", "r+");
		fwrite($temp, $data);
		rewind($temp);
		unset($data);

		$nbt = new NBT();
		$location["chunk"] = $nbt->loadFile($temp, null);
	}
	
	public function writeChunk($fp, &$location) {
		$location["offset"] = ftell($fp) / 4096;
		fseek($fp, 4, SEEK_CUR);

		$start = ftell($fp);
		fwrite(pack("c", 2));

		$temp = fopen("php://temp", "r+");
		$nbt = new NBT();
		$nbt->writeTag($temp, $location["chunk"]);
		rewind($temp);
		$compressed = gzcompress(stream_get_contents($temp)); // Round-about, yes, I know.
		fclose($temp);
		fwrite($fp, $compressed);

		$size = ftell($fp)-$start+1;
		fseek($fp, $start-4);
		fwrite(pack("N", $size));

		$location["size"] = ceil($size / 4096);
		fseek($fp, $location["offset"] + ($location["size"] * 4096));
	}
}
?>
