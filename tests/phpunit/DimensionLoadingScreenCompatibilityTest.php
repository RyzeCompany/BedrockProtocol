<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\hud\LoadingScreenType;

final class DimensionLoadingScreenCompatibilityTest extends TestCase{

	public function testChangeDimensionUsesFixedUint32On2193() : void{
		$packet = ChangeDimensionPacket::create(1, new Vector3(1, 64, 2), false, 123456);
		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::PROTOCOL_1_26_52);
		self::assertSame(pack('V', 123456), substr($out->getData(), -4));

		$decoded = new ChangeDimensionPacket();
		$decoded->decode(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_26_52);
		self::assertSame(123456, $decoded->getLoadingScreenId());
	}

	public function testChangeDimensionUsesOptionalUint32After2207() : void{
		$packet = ChangeDimensionPacket::create(2, new Vector3(1, 64, 2), false, 123456);
		$out = new ByteBufferWriter();
		$packet->encode($out, 2207);
		self::assertSame("\x01" . pack('V', 123456), substr($out->getData(), -5));
		$decoded = new ChangeDimensionPacket();
		$decoded->decode(new ByteBufferReader($out->getData()), 2207);
		self::assertSame(123456, $decoded->getLoadingScreenId());
	}

	public function testLoadingScreenPacketUsesLegacyTypeAndFixedIdOn2193() : void{
		$this->assertLegacyLoadingScreen(LoadingScreenType::START_LOADING_SCREEN, 0);
		$this->assertLegacyLoadingScreen(LoadingScreenType::STOP_LOADING_SCREEN, 1);
	}

	private function assertLegacyLoadingScreen(LoadingScreenType $type, int $onWire) : void{
		$packet = ServerboundLoadingScreenPacket::create($type, 123456);
		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::PROTOCOL_1_26_52);
		self::assertSame(chr($onWire * 2) . pack('V', 123456), substr($out->getData(), -5));
		$decoded = new ServerboundLoadingScreenPacket();
		$decoded->decode(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_26_52);
		self::assertSame($type, $decoded->getLoadingScreenType());
		self::assertSame(123456, $decoded->getLoadingScreenId());
	}

	public function testLoadingScreenPacketAfter2207() : void{
		$packet = ServerboundLoadingScreenPacket::create(LoadingScreenType::STOP_LOADING_SCREEN, 123456);
		$out = new ByteBufferWriter();
		$packet->encode($out, 2207);
		self::assertSame("\x04\x01" . pack('V', 123456), substr($out->getData(), -6));
		$decoded = new ServerboundLoadingScreenPacket();
		$decoded->decode(new ByteBufferReader($out->getData()), 2207);
		self::assertSame(LoadingScreenType::STOP_LOADING_SCREEN, $decoded->getLoadingScreenType());
		self::assertSame(123456, $decoded->getLoadingScreenId());
	}
}
