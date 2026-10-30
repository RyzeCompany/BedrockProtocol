<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

/** 2225 clientbound matchmaking state, packet ID 353. */
class ClientboundMatchmakingStatePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_MATCHMAKING_STATE_PACKET;

	private int $state;
	private string $destinationName;
	/** @var array{?string, ?bool}|null */
	private ?array $options;

	/** @param array{?string, ?bool}|null $options */
	public static function create(int $state, string $destinationName, ?array $options = null) : self{
		if($state < 0 || $state > 8){
			throw new \InvalidArgumentException("Invalid matchmaking state $state");
		}
		$result = new self;
		$result->state = $state;
		$result->destinationName = $destinationName;
		$result->options = $options;
		return $result;
	}

	public function getState() : int{ return $this->state; }
	public function getDestinationName() : string{ return $this->destinationName; }
	/** @return array{?string, ?bool}|null */
	public function getOptions() : ?array{ return $this->options; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->state = Byte::readUnsigned($in);
		if($this->state > 8){
			throw new PacketDecodeException("Invalid matchmaking state " . $this->state);
		}
		$this->destinationName = CommonTypes::getString($in);
		$this->options = CommonTypes::readOptional($in, static fn(ByteBufferReader $in) : array => [
			CommonTypes::readOptional($in, CommonTypes::getString(...)),
			CommonTypes::readOptional($in, CommonTypes::getBool(...))
		]);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		Byte::writeUnsigned($out, $this->state);
		CommonTypes::putString($out, $this->destinationName);
		CommonTypes::writeOptional($out, $this->options, static function(ByteBufferWriter $out, array $options) : void{
			CommonTypes::writeOptional($out, $options[0], CommonTypes::putString(...));
			CommonTypes::writeOptional($out, $options[1], CommonTypes::putBool(...));
		});
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleClientboundMatchmakingState($this);
	}
}
