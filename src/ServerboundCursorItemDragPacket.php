<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;

/** Cursor drag start/stop (protocol 2225, packet 358). */
class ServerboundCursorItemDragPacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_CURSOR_ITEM_DRAG_PACKET;
	public const START = 0;
	public const STOP = 1;

	private int $state;

	public static function create(int $state) : self{
		if($state !== self::START && $state !== self::STOP){
			throw new \InvalidArgumentException("Unknown cursor drag state: $state");
		}
		$result = new self;
		$result->state = $state;
		return $result;
	}

	public function getState() : int{ return $this->state; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$state = Byte::readUnsigned($in);
		if($state !== self::START && $state !== self::STOP){
			throw new PacketDecodeException("Unknown cursor drag state: $state");
		}
		$this->state = $state;
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		Byte::writeUnsigned($out, $this->state);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleServerboundCursorItemDrag($this);
	}
}
