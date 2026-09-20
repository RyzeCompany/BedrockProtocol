<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class ArrayPackSetting extends PackSetting{
	public function __construct(string $name, private array $values){
		parent::__construct($name);
	}
	public function getValues() : array{ return $this->values; }
	public function getTypeId() : PackSettingType{ return PackSettingType::ARRAY; }
	public static function read(ByteBufferReader $in, string $name) : self{
		$values=[];
		for($i=0,$n=VarInt::readUnsignedInt($in);$i<$n;$i++) $values[]=CommonTypes::getString($in);
		return new self($name,$values);
	}
	public function write(ByteBufferWriter $out) : void{
		VarInt::writeUnsignedInt($out,count($this->values));
		foreach($this->values as $v) CommonTypes::putString($out,$v);
	}
}
