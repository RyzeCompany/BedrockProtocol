<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

/** Passenger begins/stops riding a block; introduced in 2225 (ID 357). */
class SetPassengerOfBlockPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::SET_PASSENGER_OF_BLOCK_PACKET;

	private int $passenger;
	private ?BlockPosition $position;
	private ?Vector3 $offset;
	private float $rotation;
	private float $rotationLimit;
	private int $emoteType;

	public static function create(int $passenger, ?BlockPosition $position = null, ?Vector3 $offset = null, float $rotation = 0.0, float $rotationLimit = 0.0, int $emoteType = 0) : self{
		if(($position === null) !== ($offset === null)){
			throw new \InvalidArgumentException("Position and offset must either both exist or both be absent");
		}
		if($emoteType < 0 || $emoteType > 2){
			throw new \InvalidArgumentException("Invalid passenger emote type $emoteType");
		}
		$result = new self;
		$result->passenger = $passenger;
		$result->position = $position;
		$result->offset = $offset;
		$result->rotation = $rotation;
		$result->rotationLimit = $rotationLimit;
		$result->emoteType = $emoteType;
		return $result;
	}

	public function getPassenger() : int{ return $this->passenger; }
	public function getPosition() : ?BlockPosition{ return $this->position; }
	public function getOffset() : ?Vector3{ return $this->offset; }
	public function getRotation() : float{ return $this->rotation; }
	public function getRotationLimit() : float{ return $this->rotationLimit; }
	public function getEmoteType() : int{ return $this->emoteType; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->passenger = CommonTypes::getActorUniqueId($in);
		$payload = CommonTypes::readOptional($in, static function(ByteBufferReader $in) : array{
			$position = CommonTypes::getBlockPosition($in);
			$offset = CommonTypes::getVector3($in);
			$rotation = LE::readFloat($in);
			$rotationLimit = LE::readFloat($in);
			$emoteType = Byte::readUnsigned($in);
			if($emoteType > 2){
				throw new PacketDecodeException("Invalid passenger emote type $emoteType");
			}
			return [$position, $offset, $rotation, $rotationLimit, $emoteType];
		});
		[$this->position, $this->offset, $this->rotation, $this->rotationLimit, $this->emoteType] = $payload ?? [null, null, 0.0, 0.0, 0];
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putActorUniqueId($out, $this->passenger);
		CommonTypes::writeOptional($out, $this->position, function(ByteBufferWriter $out, BlockPosition $position) : void{
			CommonTypes::putBlockPosition($out, $position);
			CommonTypes::putVector3($out, $this->offset ?? throw new \LogicException("Passenger offset required"));
			LE::writeFloat($out, $this->rotation);
			LE::writeFloat($out, $this->rotationLimit);
			Byte::writeUnsigned($out, $this->emoteType);
		});
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleSetPassengerOfBlock($this);
	}
}
