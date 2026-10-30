<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;

final class Protocol2225PacketTest extends TestCase{

	private function assertRoundTrip(DataPacket $packet, callable $assertDecoded) : void{
		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::PROTOCOL_1_26_60);
		$packetById = PacketPool::getInstance()->getPacketById($packet->pid());
		self::assertNotNull($packetById);
		self::assertInstanceOf($packet::class, $packetById);
		$packetById->decode(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_26_60);
		$assertDecoded($packetById);
	}

	public function testVersionNotPrematurelyEnabled() : void{
		self::assertSame(2225, ProtocolInfo::PROTOCOL_1_26_60);
		self::assertSame(2225, ProtocolInfo::PROTOCOL_1_26_61);
		self::assertNotContains(2225, ProtocolInfo::ACCEPTED_PROTOCOL);
		self::assertSame(2193, ProtocolInfo::CURRENT_PROTOCOL);
	}

	public function testServerboundStonecutter() : void{
		$this->assertRoundTrip(
			ServerboundStonecutterSetRecipePacket::create(12, -34),
			function(ServerboundStonecutterSetRecipePacket $p) : void{
				self::assertSame(12, $p->getContainerId());
				self::assertSame(-34, $p->getRecipeIndex());
			}
		);
	}

	public function testClientboundStonecutter() : void{
		$this->assertRoundTrip(
			ClientboundStonecutterSetRecipePacket::create(98765, 3, 19),
			function(ClientboundStonecutterSetRecipePacket $p) : void{
				self::assertSame(98765, $p->getPlayerId());
				self::assertSame(3, $p->getContainerId());
				self::assertSame(19, $p->getRecipeIndex());
			}
		);
	}

	public function testMatchmakingCancelPacket() : void{
		$this->assertRoundTrip(ServerboundMatchmakingCancelPacket::create(), function(ServerboundMatchmakingCancelPacket $packet) : void{
			self::assertSame(356, $packet->pid());
		});
	}

	public function testCursorDragStates() : void{
		foreach([ServerboundCursorItemDragPacket::START, ServerboundCursorItemDragPacket::STOP] as $state){
			$this->assertRoundTrip(
				ServerboundCursorItemDragPacket::create($state),
				function(ServerboundCursorItemDragPacket $p) use($state) : void{
					self::assertSame($state, $p->getState());
				}
			);
		}
	}
	public function testLevelChunkFlagOnlyForProtocol2225() : void{
		$chunk = LevelChunkPacket::create(
			new \pocketmine\network\mcpe\protocol\types\ChunkPosition(8, -7),
			0,
			0,
			false,
			null,
			"test-chunk"
		);
		$chunk->setClientBiomeUpdate(true);
		$legacyBuffer = new ByteBufferWriter();
		$chunk->encode($legacyBuffer, ProtocolInfo::PROTOCOL_1_26_52);
		$newBuffer = new ByteBufferWriter();
		$chunk->encode($newBuffer, ProtocolInfo::PROTOCOL_1_26_60);
		self::assertSame($legacyBuffer->getData() . "\\x01", $newBuffer->getData());
		$oldPacket = new LevelChunkPacket();
		$oldPacket->decode(new ByteBufferReader($legacyBuffer->getData()), ProtocolInfo::PROTOCOL_1_26_52);
		self::assertFalse($oldPacket->isClientBiomeUpdate());
		$newPacket = new LevelChunkPacket();
		$newPacket->decode(new ByteBufferReader($newBuffer->getData()), ProtocolInfo::PROTOCOL_1_26_60);
		self::assertTrue($newPacket->isClientBiomeUpdate());
	}

}
