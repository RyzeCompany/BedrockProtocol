<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

/** Minecraft Bedrock protocol 2225, packet 355. */
class ClientboundStonecutterSetRecipePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_STONECUTTER_SET_RECIPE_PACKET;

	private int $playerId;
	private int $containerId;
	private int $recipeIndex;

	public static function create(int $playerId, int $containerId, int $recipeIndex) : self{
		$result = new self;
		$result->playerId = $playerId;
		$result->containerId = $containerId;
		$result->recipeIndex = $recipeIndex;
		return $result;
	}

	public function getPlayerId() : int{ return $this->playerId; }
	public function getContainerId() : int{ return $this->containerId; }
	public function getRecipeIndex() : int{ return $this->recipeIndex; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->playerId = CommonTypes::getActorUniqueId($in);
		$this->containerId = Byte::readUnsigned($in);
		$this->recipeIndex = VarInt::readSignedInt($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putActorUniqueId($out, $this->playerId);
		Byte::writeUnsigned($out, $this->containerId);
		VarInt::writeSignedInt($out, $this->recipeIndex);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleClientboundStonecutterSetRecipe($this);
	}
}
