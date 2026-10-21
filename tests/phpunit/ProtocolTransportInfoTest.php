<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;

final class ProtocolTransportInfoTest extends TestCase{
	public function testLegacyTransportDoesNotEnableGameplay() : void{
		self::assertSame(419, ProtocolInfo::PROTOCOL_1_16_100);
		self::assertNotContains(419, ProtocolInfo::ACCEPTED_PROTOCOL);
		self::assertSame(10, ProtocolTransportInfo::getRakNetVersion(419));
		self::assertFalse(ProtocolTransportInfo::requiresNetworkSettings(419));
		self::assertFalse(ProtocolTransportInfo::usesCompressionHeader(419));
		self::assertTrue(ProtocolTransportInfo::usesCfb8Encryption(419));
	}

	public function testCurrentTransportProfilesAreUnchanged() : void{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocol){
			self::assertSame(11, ProtocolTransportInfo::getRakNetVersion($protocol));
			self::assertTrue(ProtocolTransportInfo::requiresNetworkSettings($protocol));
			self::assertSame($protocol >= 649, ProtocolTransportInfo::usesCompressionHeader($protocol));
			self::assertFalse(ProtocolTransportInfo::usesCfb8Encryption($protocol));
		}
	}

	public function testUnknownProtocolHasNoImplicitTransportProfile() : void{
		$this->expectException(\InvalidArgumentException::class);
		ProtocolTransportInfo::getRakNetVersion(420);
	}
}
