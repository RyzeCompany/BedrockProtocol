<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\PlayerAction;

final class PlayerActionLegacyCompatTest extends TestCase{

	/**
	 * Wire values changed with Bedrock protocol 2207, not protocol 2193.
	 * In particular, 13 is DimensionChangeAck on 2193 while 14 is StartGliding.
	 */
	public function testLegacyAndModernActionIds() : void{
		foreach([
			[PlayerAction::START_BREAK, 1, 0],
			[PlayerAction::CREATIVE_PLAYER_DESTROY_BLOCK, 12, 13],
			[PlayerAction::DIMENSION_CHANGE_ACK, 13, 14],
			[PlayerAction::START_GLIDE, 14, 15],
			[PlayerAction::START_SWIMMING, 18, 21],
			[PlayerAction::PREDICT_DESTROY_BLOCK, 22, 26],
			[PlayerAction::START_USING_ITEM, 32, 37]
		] as [$action, $legacyWire, $modernWire]){
			$this->assertAction(ProtocolInfo::PROTOCOL_1_26_45, $action, $legacyWire);
			$this->assertAction(ProtocolInfo::PROTOCOL_1_26_52, $action, $legacyWire);
			$this->assertAction(2207, $action, $modernWire);
		}
	}

	private function assertAction(int $protocolId, int $internalAction, int $expectedWire) : void{
		$pos = new BlockPosition(1, 64, 2);
		$packet = PlayerActionPacket::create(123, $internalAction, $pos, $pos, 0);
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocolId);
		$reader = new ByteBufferReader($out->getData());
		self::assertSame(ProtocolInfo::PLAYER_ACTION_PACKET, VarInt::readUnsignedInt($reader));
		self::assertSame(123, CommonTypes::getActorRuntimeId($reader));
		self::assertSame($expectedWire, VarInt::readSignedInt($reader));

		$decoded = new PlayerActionPacket();
		$decoded->decode(new ByteBufferReader($out->getData()), $protocolId);
		self::assertSame($internalAction, $decoded->action);
	}
}
