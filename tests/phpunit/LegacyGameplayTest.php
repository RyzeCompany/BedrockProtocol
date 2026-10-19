<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\serializer\Legacy419EntityMetadata;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags as Flags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties as Properties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;

final class LegacyGameplayTest extends TestCase{
	public static function wireFixtures() : array{
		$result = [];
		foreach(json_decode(file_get_contents(__DIR__ . '/fixtures/gameplay_419.json'), true, flags: JSON_THROW_ON_ERROR) as $entry){
			$result[$entry['name']] = [$entry['bytes']];
		}
		return $result;
	}

	#[DataProvider('wireFixtures')]
	public function testHistoricalPacketBytes(string $base64) : void{
		$bytes = base64_decode($base64, true);
		$packet = (new PacketPool())->getPacket($bytes);
		self::assertNotNull($packet);
		$in = new ByteBufferReader($bytes);
		$packet->decode($in, ProtocolInfo::PROTOCOL_1_16_100);
		self::assertSame(0, $in->getUnreadLength());
		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::PROTOCOL_1_16_100);
		self::assertSame($bytes, $out->getData());
	}

	public function testLegacySlotsUseAuxMetadataAndNoStackOrBlockRuntimeIds() : void{
		// stone, count 3, metadata 2; empty NBT and restriction lists
		$bytes = hex2bin('02860800000000');
		$item = CommonTypes::getItemStackWrapper(new ByteBufferReader($bytes), 419);
		self::assertSame(1, $item->getItemStack()->getId());
		self::assertSame(2, $item->getItemStack()->getMeta());
		self::assertSame(3, $item->getItemStack()->getCount());
		self::assertSame(0, $item->getItemStack()->getBlockRuntimeId());
		$out = new ByteBufferWriter();
		CommonTypes::putItemStackWrapper($out, $item, 419);
		self::assertSame($bytes, $out->getData());
	}

	public function testMetadataProjectionUsesHistoricalIdsWithoutMutatingCurrentData() : void{
		$metadata = [
			Properties::NAMETAG => new StringMetadataProperty('Tester'),
			Properties::FLAGS => new LongMetadataProperty(1 << Flags::SWIMMING),
			Properties::FLAGS2 => new LongMetadataProperty(0)
		];
		$projected = Legacy419EntityMetadata::fromCurrent($metadata);
		self::assertSame('Tester', $projected[4]->getValue());
		self::assertSame(1 << 56, $projected[0]->getValue());
		self::assertSame(1 << Flags::SWIMMING, $metadata[Properties::FLAGS]->getValue());
		$restored = Legacy419EntityMetadata::toCurrent($projected);
		self::assertSame($metadata[Properties::FLAGS]->getValue(), $restored[Properties::FLAGS]->getValue());
	}

	public function test419DoesNotBecomeAnAcceptedReleaseProtocol() : void{
		self::assertNotContains(419, ProtocolInfo::ACCEPTED_PROTOCOL);
	}
}
