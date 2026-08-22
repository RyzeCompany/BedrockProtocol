<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\BitSet;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\types\ScoreboardIdentityPacketEntry;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\MineBlockStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use Ramsey\Uuid\Uuid;

final class MultiVersionProtocolTest extends TestCase{

	/** @return array<string, array{int}> */
	public static function protocolVersions() : array{
		return [
			'26.30' => [ProtocolInfo::PROTOCOL_1_26_30],
			'26.40' => [ProtocolInfo::PROTOCOL_1_26_40],
			'26.45' => [ProtocolInfo::PROTOCOL_1_26_45]
		];
	}

	private static function encodePacket(DataPacket $packet, int $protocolId) : string{
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocolId);
		return $out->getData();
	}

	public function testDoubleOptionalWireFormat() : void{
		$out = new ByteBufferWriter();
		CommonTypes::writeDoubleOptional($out, 'x', CommonTypes::putString(...));
		self::assertSame('01010178', bin2hex($out->getData()));
		self::assertSame('x', CommonTypes::readDoubleOptional(new ByteBufferReader($out->getData()), CommonTypes::getString(...)));

		$out = new ByteBufferWriter();
		CommonTypes::writeDoubleOptional($out, null, static function(ByteBufferWriter $out, mixed $value) : void{});
		self::assertSame('0100', bin2hex($out->getData()));
		self::assertNull(CommonTypes::readDoubleOptional(new ByteBufferReader($out->getData()), CommonTypes::getString(...)));
	}

	public function testDummyOptionalRejectsInvalidMarker() : void{
		$this->expectException(PacketDecodeException::class);
		CommonTypes::readDummyOptional(new ByteBufferReader("\x00"));
	}

	#[DataProvider('protocolVersions')]
	public function testItemStackNetIdVariantRoundTrip(int $protocolId) : void{
		$out = new ByteBufferWriter();
		CommonTypes::writeItemStackNetIdVariant($out, -123, $protocolId);
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? '85ffffff' : 'f501', bin2hex($out->getData()));
		self::assertSame(-123, CommonTypes::readItemStackNetIdVariant(new ByteBufferReader($out->getData()), $protocolId));
	}

	#[DataProvider('protocolVersions')]
	public function testResourcePackResponseRoundTrip(int $protocolId) : void{
		foreach([
			ResourcePackClientResponsePacket::STATUS_REFUSED,
			ResourcePackClientResponsePacket::STATUS_SEND_PACKS,
			ResourcePackClientResponsePacket::STATUS_HAVE_ALL_PACKS,
			ResourcePackClientResponsePacket::STATUS_COMPLETED
		] as $status){
			$packIds = $status === ResourcePackClientResponsePacket::STATUS_SEND_PACKS ? ['sample-pack'] : [];
			$packet = ResourcePackClientResponsePacket::create($status, $packIds);
			$decoded = new ResourcePackClientResponsePacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
			self::assertSame($status, $decoded->status);
			self::assertSame($packIds, $decoded->packIds);
		}
	}

	#[DataProvider('protocolVersions')]
	public function testResourcePackInfoRoundTrip(int $protocolId) : void{
		$packet = ResourcePacksInfoPacket::create([], [], false, false, false, false, [], Uuid::fromString('00000000-0000-0000-0000-000000000000'), '1.0.0', false);
		$decoded = new ResourcePacksInfoPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame([], $decoded->resourcePackEntries);
		self::assertSame('1.0.0', $decoded->getWorldTemplateVersion());
	}

	#[DataProvider('protocolVersions')]
	public function testMovePlayerRoundTrip(int $protocolId) : void{
		foreach([MovePlayerPacket::MODE_NORMAL, MovePlayerPacket::MODE_TELEPORT] as $mode){
			$packet = MovePlayerPacket::create(17, new Vector3(1.5, 64.25, -3.75), 10.0, 20.0, 30.0, $mode, true, 0, 4, 9, 22);
			$decoded = new MovePlayerPacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
			self::assertSame($mode, $decoded->mode);
			self::assertSame(22, $decoded->tick);
			if($mode === MovePlayerPacket::MODE_TELEPORT){
				self::assertSame(4, $decoded->teleportCause);
				self::assertSame(9, $decoded->teleportItem);
			}
		}
	}

	#[DataProvider('protocolVersions')]
	public function testLevelChunkRoundTrip(int $protocolId) : void{
		$packet = LevelChunkPacket::create(new ChunkPosition(3, -2), 0, 8, false, null, 'chunk');
		$decoded = new LevelChunkPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame(3, $decoded->getChunkPosition()->getX());
		self::assertSame(-2, $decoded->getChunkPosition()->getZ());
		self::assertSame(8, $decoded->getSubChunkCount());
		self::assertFalse($decoded->isCacheEnabled());
		self::assertSame('chunk', $decoded->getExtraPayload());
	}

	#[DataProvider('protocolVersions')]
	public function testItemStackRequestRoundTrip(int $protocolId) : void{
		$request = new ItemStackRequest(-3, [new MineBlockStackRequestAction(2, 13, -123)], ['filter'], 7);
		$out = new ByteBufferWriter();
		$request->write($out, $protocolId);
		$decoded = ItemStackRequest::read(new ByteBufferReader($out->getData()), $protocolId);
		self::assertSame(-3, $decoded->getRequestId());
		self::assertSame(['filter'], $decoded->getFilterStrings());
		self::assertCount(1, $decoded->getActions());
		self::assertInstanceOf(MineBlockStackRequestAction::class, $decoded->getActions()[0]);
		self::assertSame(-123, $decoded->getActions()[0]->getStackId());
	}

	#[DataProvider('protocolVersions')]
	public function testItemStackResponseRoundTrip(int $protocolId) : void{
		foreach([ItemStackResponse::RESULT_OK, ItemStackResponse::RESULT_ERROR] as $result){
			$response = new ItemStackResponse($result, -7, []);
			$out = new ByteBufferWriter();
			$response->write($out, $protocolId);
			$decoded = ItemStackResponse::read(new ByteBufferReader($out->getData()), $protocolId);
			self::assertSame($result, $decoded->getResult());
			self::assertSame(-7, $decoded->getRequestId());
			self::assertSame([], $decoded->getContainerInfos());
		}
	}

	#[DataProvider('protocolVersions')]
	public function testItemStackResponseSlotRoundTrip(int $protocolId) : void{
		$slot = new ItemStackResponseSlotInfo(2, 3, 4, 17, 'name', 'filtered', 6);
		$out = new ByteBufferWriter();
		$slot->write($out, $protocolId);
		$decoded = ItemStackResponseSlotInfo::read(new ByteBufferReader($out->getData()), $protocolId);
		self::assertSame(17, $decoded->getItemStackId());
		self::assertSame('filtered', $decoded->getFilteredCustomName());
		self::assertSame(6, $decoded->getDurabilityCorrection());
	}

	#[DataProvider('protocolVersions')]
	public function testScoreboardRoundTrip(int $protocolId) : void{
		$entry = new ScorePacketEntry();
		$entry->scoreboardId = 12;
		$entry->objectiveName = 'objective';
		$entry->score = 7;
		$entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
		$entry->customName = 'line';

		foreach([SetScorePacket::TYPE_CHANGE, SetScorePacket::TYPE_REMOVE] as $type){
			$packet = SetScorePacket::create($type, [$entry]);
			$decoded = new SetScorePacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
			self::assertSame($type, $decoded->type);
			self::assertSame('objective', $decoded->entries[0]->objectiveName);
			if($type === SetScorePacket::TYPE_CHANGE){
				self::assertSame('line', $decoded->entries[0]->customName);
			}
		}
	}

	#[DataProvider('protocolVersions')]
	public function testPlayerAuthInputRoundTrip(int $protocolId) : void{
		$flags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
		$flags->set(PlayerAuthInputFlags::START_SPRINTING, true);
		$packet = PlayerAuthInputPacket::create(new Vector3(1, 64, 2), 1, 2, 3, 0.25, 0.5, $flags, 1, 0, 1, null, new Vector2(0, 0), 10, new Vector3(0, 0, 0), null, null, null, null, 0, 0, new Vector3(0, 0, 0), new Vector2(0, 0));
		$decoded = new PlayerAuthInputPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame(10, $decoded->getTick());
		self::assertTrue($decoded->getInputFlags()->get(PlayerAuthInputFlags::START_SPRINTING));
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? PlayerAuthInputFlags::NUMBER_OF_FLAGS_1_26_40 : PlayerAuthInputFlags::NUMBER_OF_FLAGS, $decoded->getInputFlags()->getLength());
	}

	#[DataProvider('protocolVersions')]
	public function testInventoryContentRoundTrip(int $protocolId) : void{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$empty = new ItemStackWrapper(0, ItemStack::null());
		$packet = InventoryContentPacket::create(2, [$item], new FullContainerName(12), 0, $empty);
		$decoded = new InventoryContentPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame(7, $decoded->items[0]->getItemStack()->getId());
		self::assertSame(5, $decoded->items[0]->getStackId());
	}

	#[DataProvider('protocolVersions')]
	public function testInventorySlotRoundTrip(int $protocolId) : void{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$packet = InventorySlotPacket::create(2, 4, new FullContainerName(12), 0, null, $item);
		$decoded = new InventorySlotPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame(7, $decoded->item->getItemStack()->getId());
		self::assertSame(5, $decoded->item->getStackId());
	}

	#[DataProvider('protocolVersions')]
	public function testMobEquipmentRoundTrip(int $protocolId) : void{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$packet = MobEquipmentPacket::create(4, $item, 3, 2, 1);
		$decoded = new MobEquipmentPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame(7, $decoded->item->getItemStack()->getId());
		self::assertSame(5, $decoded->item->getStackId());
	}

	#[DataProvider('protocolVersions')]
	public function testIntegerGameRuleWireFormat(int $protocolId) : void{
		$rule = new IntGameRule(300, false);
		$out = new ByteBufferWriter();
		$rule->encode($out, $protocolId, true);
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? '2c010000' : 'ac02', bin2hex($out->getData()));
		self::assertSame(300, IntGameRule::decode(new ByteBufferReader($out->getData()), $protocolId, false, true)->getValue());
	}

	#[DataProvider('protocolVersions')]
	public function testScoreboardIdentityRoundTrip(int $protocolId) : void{
		$entry = new ScoreboardIdentityPacketEntry();
		$entry->scoreboardId = 7;
		$entry->actorUniqueId = 23;
		$packet = SetScoreboardIdentityPacket::create(SetScoreboardIdentityPacket::TYPE_REGISTER_IDENTITY, [$entry]);
		$decoded = new SetScoreboardIdentityPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame(23, $decoded->entries[0]->actorUniqueId);
	}

	public function testNewSubChunkRequestRoundTrip() : void{
		foreach([ProtocolInfo::PROTOCOL_1_26_40, ProtocolInfo::PROTOCOL_1_26_45] as $protocolId){
			$packet = SubChunkRequestPacket::create(0, new SubChunkPosition(3, 4, -2), [new SubChunkPositionOffset(1, 0, -1)]);
			$decoded = new SubChunkRequestPacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
			self::assertSame(3, $decoded->getBasePosition()->getX());
			self::assertSame(-1, $decoded->getEntries()[0]->getZOffset());
		}
	}
}
