<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

class RecordStartedPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::RECORD_STARTED_PACKET;

	public int $x;
	public int $y;
	public int $z;
	public int $serverSoundHandle;

	/** @generate-create-func */
	public static function create(int $x, int $y, int $z, int $serverSoundHandle) : self{
		$result = new self;
		$result->x = $x;
		$result->y = $y;
		$result->z = $z;
		$result->serverSoundHandle = $serverSoundHandle;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->x = \pmmp\encoding\VarInt::readSignedInt($in);
		$this->y = \pmmp\encoding\VarInt::readSignedInt($in);
		$this->z = \pmmp\encoding\VarInt::readSignedInt($in);
		$this->serverSoundHandle = LE::readUnsignedLong($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		\pmmp\encoding\VarInt::writeSignedInt($out, $this->x);
		\pmmp\encoding\VarInt::writeSignedInt($out, $this->y);
		\pmmp\encoding\VarInt::writeSignedInt($out, $this->z);
		LE::writeUnsignedLong($out, $this->serverSoundHandle);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleRecordStarted($this);
	}
}
