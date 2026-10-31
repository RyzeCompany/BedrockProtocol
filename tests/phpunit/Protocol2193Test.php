<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;

final class Protocol2193Test extends TestCase{

	public function testStableVersionsShareProtocol2193() : void{
		self::assertSame(2193, ProtocolInfo::PROTOCOL_1_26_50);
		self::assertSame(2193, ProtocolInfo::PROTOCOL_1_26_51);
		self::assertSame(2193, ProtocolInfo::PROTOCOL_1_26_52);
		self::assertSame(2169, ProtocolInfo::PROTOCOL_1_26_45);
		self::assertContains(ProtocolInfo::PROTOCOL_1_26_52, ProtocolInfo::ACCEPTED_PROTOCOL);
	}
	public function testPlayerAuthInput2193RetainsSinglePresenceMarkers() : void{
		$flags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
		$packet = PlayerAuthInputPacket::create(
			new Vector3(1, 64, 2),
			1, 2, 3,
			0.25, 0.5,
			$flags,
			1, 0, 1,
			null,
			new Vector2(0, 0),
			10,
			new Vector3(0, 0, 0),
			null, null, null, null,
			0, 0,
			new Vector3(0, 0, 0),
			new Vector2(0, 0)
		);

		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::PROTOCOL_1_26_51);

		// Five optional fields retain one presence byte each; only the outer dummy markers were removed.
		self::assertSame(92, strlen($out->getData()));
	}

	public function testPlayerAuthInput2193ReadsAbsentOptionalFields() : void{
		$flags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
		$packet = PlayerAuthInputPacket::create(
			new Vector3(1, 64, 2),
			1, 2, 3,
			0.25, 0.5,
			$flags,
			1, 0, 1,
			null,
			new Vector2(0, 0),
			10,
			new Vector3(0, 0, 0),
			null, null, null, null,
			0, 0,
			new Vector3(0, 0, 0),
			new Vector2(0, 0)
		);

		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::PROTOCOL_1_26_51);

		$decoded = new PlayerAuthInputPacket();
		$decoded->decode(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_26_51);

		self::assertNull($decoded->getItemInteractionData());
		self::assertNull($decoded->getItemStackRequest());
		self::assertNull($decoded->getBlockActions());
		self::assertNull($decoded->getVehicleInfo());
	}

	public function testNewPacketIds() : void{
		self::assertSame(351, SetPlayerFurnaceOptionsPacket::NETWORK_ID);
		self::assertSame(352, RecordStartedPacket::NETWORK_ID);
	}
}
