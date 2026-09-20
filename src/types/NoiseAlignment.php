<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;

final class NoiseAlignment{
	public const TYPE_MIN_LOCAL_TRANSITION_END = 0;
	public function __construct(private int $type = self::TYPE_MIN_LOCAL_TRANSITION_END, private int $value = 0){}
	public function getType() : int{ return $this->type; }
	public function getValue() : int{ return $this->value; }
	public static function read(ByteBufferReader $in) : self{ return new self(Byte::readUnsigned($in), VarInt::readUnsignedInt($in)); }
	public function write(ByteBufferWriter $out) : void{ Byte::writeUnsigned($out,$this->type); VarInt::writeUnsignedInt($out,$this->value); }
}
