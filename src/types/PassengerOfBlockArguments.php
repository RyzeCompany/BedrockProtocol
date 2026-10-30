<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

/**
 * Protocol 2225 optional passenger-of-block state in AddActor/AddPlayer.
 * SetPassengerOfBlockPacket uses the same wire payload after actor ID.
 */
final class PassengerOfBlockArguments{

	public function __construct(
		public readonly BlockPosition $position,
		public readonly Vector3 $offset,
		public readonly float $rotation,
		public readonly float $rotationLimit,
		public readonly int $emoteType
	){
		if($emoteType < 0 || $emoteType > 2){
			throw new \InvalidArgumentException("Passenger emote type must be 0..2");
		}
	}

	public static function read(ByteBufferReader $in) : self{
		$position = CommonTypes::getBlockPosition($in);
		$offset = CommonTypes::getVector3($in);
		$rotation = LE::readFloat($in);
		$rotationLimit = LE::readFloat($in);
		$emote = Byte::readUnsigned($in);
		if($emote > 2){
			throw new PacketDecodeException("Unknown block passenger emote $emote");
		}
		return new self($position, $offset, $rotation, $rotationLimit, $emote);
	}

	public function write(ByteBufferWriter $out) : void{
		CommonTypes::putBlockPosition($out, $this->position);
		CommonTypes::putVector3($out, $this->offset);
		LE::writeFloat($out, $this->rotation);
		LE::writeFloat($out, $this->rotationLimit);
		Byte::writeUnsigned($out, $this->emoteType);
	}
}
