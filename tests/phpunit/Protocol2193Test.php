<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;

final class Protocol2193Test extends TestCase{
	public function testStableVersionsShareProtocol2193() : void{
		self::assertSame(2193, ProtocolInfo::PROTOCOL_1_26_50);
		self::assertSame(2193, ProtocolInfo::PROTOCOL_1_26_51);
		self::assertSame(2169, ProtocolInfo::PROTOCOL_1_26_45);
		self::assertContains(ProtocolInfo::PROTOCOL_1_26_51, ProtocolInfo::ACCEPTED_PROTOCOL);
	}
	public function testNewPacketIds() : void{
		self::assertSame(351, SetPlayerFurnaceOptionsPacket::NETWORK_ID);
		self::assertSame(352, RecordStartedPacket::NETWORK_ID);
	}
}
