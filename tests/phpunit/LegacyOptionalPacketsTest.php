<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;

final class LegacyOptionalPacketsTest extends TestCase{

	/** Frozen 419 payloads based on the field order of MultiVersion's v419 adapters. */
	public static function payloads() : array{
		return [
			[AnimateEntityPacket::class, '016100017301630000003f0107'],
			[CameraShakePacket::class, '0000003f0000004001'],
			[EmotePacket::class, '07016501'],
			[HurtArmorPacket::class, '0204'],
			[NpcRequestPacket::class, '0701016302'],
			[SpawnParticleEffectPacket::class, '000e0000803f00000040000040400170'],
			[ActorPickRequestPacket::class, '070000000000000002'],
			[MapInfoRequestPacket::class, '0e'],
			[ClientboundMapItemDataPacket::class, '0e000000'],
			[PhotoTransferPacket::class, '017001640162'],
			[EducationSettingsPacket::class, '01610162010001'],
			[AddVolumeEntityPacket::class, '070a0000'],
			[RemoveVolumeEntityPacket::class, '07'],
		];
	}

	#[DataProvider('payloads')]
	public function test419PacketConsumesOnlyItsHistoricalFields(string $class, string $hex) : void{
		$header = new ByteBufferWriter();
		VarInt::writeUnsignedInt($header, $class::NETWORK_ID);
		$wire = $header->getData() . hex2bin($hex);
		$packet = new $class();
		$in = new ByteBufferReader($wire);
		$packet->decode($in, 419);
		self::assertSame(0, $in->getUnreadLength());
		$out = new ByteBufferWriter();
		$packet->encode($out, 419);
		self::assertSame($wire, $out->getData());
	}
}
