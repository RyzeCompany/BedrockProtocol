<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;

/** Minecraft Bedrock protocol 2225, packet 354. */
class ServerboundStonecutterSetRecipePacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_STONECUTTER_SET_RECIPE_PACKET;

	private int $containerId;
	private int $recipeIndex;

	public static function create(int $containerId, int $recipeIndex) : self{
		$result = new self;
		$result->containerId = $containerId;
		$result->recipeIndex = $recipeIndex;
		return $result;
	}

	public function getContainerId() : int{ return $this->containerId; }
	public function getRecipeIndex() : int{ return $this->recipeIndex; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->containerId = Byte::readUnsigned($in);
		$this->recipeIndex = VarInt::readSignedInt($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		Byte::writeUnsigned($out, $this->containerId);
		VarInt::writeSignedInt($out, $this->recipeIndex);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleServerboundStonecutterSetRecipe($this);
	}
}
