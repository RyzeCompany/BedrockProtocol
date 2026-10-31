<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;

/** Minecraft Bedrock protocol 2225, packet 356 has no payload. */
class ServerboundMatchmakingCancelPacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_MATCHMAKING_CANCEL_PACKET;

	public static function create() : self{
		return new self;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		// No fields in the official 2225 wire format.
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		// No fields in the official 2225 wire format.
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleServerboundMatchmakingCancel($this);
	}
}
