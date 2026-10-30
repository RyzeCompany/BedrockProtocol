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
		self::assertSame($legacyBuffer->getData() . "\x01", $newBuffer->getData());
		$oldPacket = new LevelChunkPacket();
		$oldPacket->decode(new ByteBufferReader($legacyBuffer->getData()), ProtocolInfo::PROTOCOL_1_26_52);
		self::assertFalse($oldPacket->isClientBiomeUpdate());
		$newPacket = new LevelChunkPacket();
		$newPacket->decode(new ByteBufferReader($newBuffer->getData()), ProtocolInfo::PROTOCOL_1_26_60);
		self::assertTrue($newPacket->isClientBiomeUpdate());
	}

	public function testMatchmakingStateWithNestedOptions() : void{
		$this->assertRoundTrip(
			ClientboundMatchmakingStatePacket::create(2, "play.example.net", ["Player", false]),
			function(ClientboundMatchmakingStatePacket $p) : void{
				self::assertSame(2, $p->getState());
				self::assertSame("play.example.net", $p->getDestinationName());
				self::assertSame(["Player", false], $p->getOptions());
			}
		);
	}

	public function testBlockPassengerStartStop() : void{
		$this->assertRoundTrip(
			SetPassengerOfBlockPacket::create(54321),
			function(SetPassengerOfBlockPacket $p) : void{
				self::assertSame(54321, $p->getPassenger());
				self::assertNull($p->getPosition());
				self::assertNull($p->getOffset());
			}
		);
		$this->assertRoundTrip(
			SetPassengerOfBlockPacket::create(
				54321,
				new \pocketmine\network\mcpe\protocol\types\BlockPosition(5, 75, -2),
				new \pocketmine\math\Vector3(1.0, 1.25, 0.0),
				90.0, 35.0, 1
			),
			function(SetPassengerOfBlockPacket $p) : void{
				self::assertSame(54321, $p->getPassenger());
				self::assertNotNull($p->getPosition());
				self::assertSame(5, $p->getPosition()->getX());
				self::assertSame(75, $p->getPosition()->getY());
				self::assertSame(-2, $p->getPosition()->getZ());
				self::assertSame(1, $p->getEmoteType());
			}
		);
	}

	public function testSignedAudioContentRegistration() : void{
		$entries = [['id' => 'audio-id', 'shared' => 'jwt-1', 'server' => 'jwt-2', 'playback' => 'jwt-3']];
		$this->assertRoundTrip(
			ServerboundRegisterAudioContentPacket::create($entries),
			function(ServerboundRegisterAudioContentPacket $p) use($entries) : void{
				self::assertSame($entries, $p->getRegistrations());
			}
		);
	}

	public function testSignedAudioPlaybackSharesPlaySoundWirePayload() : void{
		$sound = PlaySoundPacket::create("note.play", 1.0, 2.0, 3.0, 0.5, 1.0, null);
		$this->assertRoundTrip(
			ClientboundPlayAudioContentPacket::createAudioContent("shared.jwt", "playback.jwt", "Sound", $sound),
			function(ClientboundPlayAudioContentPacket $p) : void{
				self::assertSame("shared.jwt", $p->getSharedMetadataJwt());
				self::assertSame("playback.jwt", $p->getPlaybackContentJwt());
				self::assertSame("Sound", $p->getPlaybackType());
				self::assertSame("note.play", $p->soundName);
				self::assertSame(1.0, $p->x);
				self::assertSame(2.0, $p->y);
				self::assertSame(3.0, $p->z);
			}
		);
	}

	public function testNoneCompressionWireValueIsVersioned() : void{
		$packet = NetworkSettingsPacket::create(0, \pocketmine\network\mcpe\protocol\types\CompressionAlgorithm::NONE, false, 0, 0.0);
		foreach([ProtocolInfo::PROTOCOL_1_26_52 => "\x02\x00", ProtocolInfo::PROTOCOL_1_26_60 => "\xff\xff"] as $protocol => $expected){
			$out = new ByteBufferWriter();
			$packet->encode($out, $protocol);
			$data = $out->getData();
			// Uncompressed threshold uint16 followed by algorithm uint16.
			self::assertSame($expected, substr($data, 4, 2));
			$decoded = new NetworkSettingsPacket();
			$decoded->decode(new ByteBufferReader($data), $protocol);
			self::assertSame(\pocketmine\network\mcpe\protocol\types\CompressionAlgorithm::NONE, $decoded->getCompressionAlgorithm());
		}
	}

	public function testAnimation2225AddsExactlyOneHandByte() : void{
		$animation = AnimatePacket::create(11, AnimatePacket::ACTION_SWING_ARM, 0.0, "Attack", 1);
		$old = new ByteBufferWriter();
		$animation->encode($old, ProtocolInfo::PROTOCOL_1_26_52);
		$modern = new ByteBufferWriter();
		$animation->encode($modern, ProtocolInfo::PROTOCOL_1_26_60);
		self::assertSame($old->getData() . "\x01", $modern->getData());
		$decoded = new AnimatePacket();
		$decoded->decode(new ByteBufferReader($modern->getData()), ProtocolInfo::PROTOCOL_1_26_60);
		self::assertSame(1, $decoded->hand);
		$legacyDecoded = new AnimatePacket();
		$legacyDecoded->decode(new ByteBufferReader($old->getData()), ProtocolInfo::PROTOCOL_1_26_52);
		self::assertSame(0, $legacyDecoded->hand);
	}

	public function testActorSpawnOptionalPassengerOnlyOn2225() : void{
		$actor = AddActorPacket::create(
			1, 11, "minecraft:cow",
			new \pocketmine\math\Vector3(3.0, 4.0, 5.0),
			new \pocketmine\math\Vector3(0.0, 0.0, 0.0),
			0.0, 0.0, 0.0, 0.0, [], [],
			new \pocketmine\network\mcpe\protocol\types\entity\PropertySyncData([], []),
			[]
		);
		$old = new ByteBufferWriter();
		$actor->encode($old, ProtocolInfo::PROTOCOL_1_26_52);
		$noPassenger = new ByteBufferWriter();
		$actor->encode($noPassenger, ProtocolInfo::PROTOCOL_1_26_60);
		self::assertSame($old->getData() . "\x00", $noPassenger->getData());

		$passenger = new \pocketmine\network\mcpe\protocol\types\PassengerOfBlockArguments(
			new \pocketmine\network\mcpe\protocol\types\BlockPosition(1, 64, 2),
			new \pocketmine\math\Vector3(0.25, 0.75, 0.5),
			90.0, 45.0, 1
		);
		$actor->passengerBlockData = $passenger;
		$out = new ByteBufferWriter();
		$actor->encode($out, ProtocolInfo::PROTOCOL_1_26_60);
		$decoded = new AddActorPacket();
		$decoded->decode(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_26_60);
		self::assertNotNull($decoded->passengerBlockData);
		self::assertSame(1, $decoded->passengerBlockData->position->getX());
		self::assertSame(64, $decoded->passengerBlockData->position->getY());
		self::assertSame(2, $decoded->passengerBlockData->position->getZ());
		self::assertSame(1, $decoded->passengerBlockData->emoteType);
	}

	public function testPlayerListMovesPlayFabOutOfSkinIn2225() : void{
		$skin = new \pocketmine\network\mcpe\protocol\types\skin\SkinData(
			"skin-1", "pfid-123", "{}", new \pocketmine\network\mcpe\protocol\types\skin\SkinImage(1, 1, "\xff\xff\xff\xff")
		);
		$entry = \pocketmine\network\mcpe\protocol\types\PlayerListEntry::createAdditionEntry(
			\Ramsey\Uuid\Uuid::uuid4(), 12, "PlayerOne", $skin, "xuid-123"
		);
		$packet = PlayerListPacket::add([$entry]);
		foreach([ProtocolInfo::PROTOCOL_1_26_52, ProtocolInfo::PROTOCOL_1_26_60] as $protocol){
			$out = new ByteBufferWriter();
			$packet->encode($out, $protocol);
			$decoded = new PlayerListPacket();
			$decoded->decode(new ByteBufferReader($out->getData()), $protocol);
			self::assertCount(1, $decoded->entries);
			self::assertSame("PlayerOne", $decoded->entries[0]->username);
			self::assertSame("xuid-123", $decoded->entries[0]->xboxUserId);
			if($protocol >= ProtocolInfo::PROTOCOL_1_26_60){
				self::assertSame("pfid-123", $decoded->entries[0]->playFabId);
				self::assertSame("", $decoded->entries[0]->skinData->getPlayFabId());
			}else{
				self::assertSame("pfid-123", $decoded->entries[0]->skinData->getPlayFabId());
			}
		}
	}

}
