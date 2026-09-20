<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

class SetPlayerFurnaceOptionsPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::SET_PLAYER_FURNACE_OPTIONS_PACKET;

	public const FURNACE_NONE = 0;
	public const FURNACE_FURNACE = 1;
	public const FURNACE_BLAST_FURNACE = 2;
	public const FURNACE_SMOKER = 3;

	public const TAB_NONE = 0;
	public const TAB_RECIPE_FOOD = 1;
	public const TAB_RECIPE_ITEMS = 2;
	public const TAB_RECIPE_BLOCKS = 3;
	public const TAB_RECIPE_SEARCH = 4;
	public const TAB_INVENTORY = 5;

	public const LAYOUT_NONE = 0;
	public const LAYOUT_INVENTORY_ONLY = 1;
	public const LAYOUT_DEFAULT = 2;

	public int $furnaceType = self::FURNACE_NONE;
	public int $leftFurnaceTab = self::TAB_NONE;
	public bool $filtering = false;
	public int $layout = self::LAYOUT_NONE;

	/** @generate-create-func */
	public static function create(int $furnaceType, int $leftFurnaceTab, bool $filtering, int $layout) : self{
		$result = new self;
		$result->furnaceType = $furnaceType;
		$result->leftFurnaceTab = $leftFurnaceTab;
		$result->filtering = $filtering;
		$result->layout = $layout;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->furnaceType = Byte::readUnsigned($in);
		$this->leftFurnaceTab = VarInt::readSignedInt($in);
		$this->filtering = $this->readBool($in);
		$this->layout = VarInt::readSignedInt($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		Byte::writeUnsigned($out, $this->furnaceType);
		VarInt::writeSignedInt($out, $this->leftFurnaceTab);
		CommonTypes::putBool($out, $this->filtering);
		VarInt::writeSignedInt($out, $this->layout);
	}

	private function readBool(ByteBufferReader $in) : bool{
		return CommonTypes::getBool($in);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleSetPlayerFurnaceOptions($this);
	}
}
