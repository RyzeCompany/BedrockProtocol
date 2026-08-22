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
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\network\mcpe\protocol\types\ScoreboardIdentityPacketEntry;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryCommon;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithCache;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithCacheList;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithoutCache;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithoutCacheList;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;
use pocketmine\network\mcpe\protocol\types\SubChunkRequestResult;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeGroupEntry;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeAutoStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\MineBlockStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeUnlockingRequirement;
use pocketmine\network\mcpe\protocol\types\recipe\ShapedRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\ShapelessRecipe;
use pocketmine\network\mcpe\protocol\types\recipe\StringIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\TagItemDescriptor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
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
	public function testItemStackWrapperExactWireFormat(int $protocolId) : void{
		$item = new ItemStackWrapper(5, new ItemStack(7, 1, 3, 2, 'data'));
		$out = new ByteBufferWriter();
		if($protocolId === ProtocolInfo::PROTOCOL_1_26_30){
			CommonTypes::putNetworkItemStackDescriptor($out, $item);
			self::assertSame('070003000101000a020464617461', bin2hex($out->getData()));
			$decoded = CommonTypes::getNetworkItemStackDescriptor(new ByteBufferReader($out->getData()));
		}else{
			CommonTypes::putItemStackWrapper($out, $item, $protocolId);
			self::assertSame('0700030001010a020464617461', bin2hex($out->getData()));
			$decoded = CommonTypes::getItemStackWrapper(new ByteBufferReader($out->getData()), $protocolId);
		}
		self::assertSame(7, $decoded->getItemStack()->getId());
		self::assertSame(5, $decoded->getStackId());
	}

	#[DataProvider('protocolVersions')]
	public function testEmptyItemStackExactWireFormat(int $protocolId) : void{
		$item = new ItemStackWrapper(0, ItemStack::null());
		$out = new ByteBufferWriter();
		CommonTypes::putItemStackWrapper($out, $item, $protocolId);
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? '0000000000000000' : '00', bin2hex($out->getData()));
		self::assertSame(0, CommonTypes::getItemStackWrapper(new ByteBufferReader($out->getData()), $protocolId)->getItemStack()->getId());

		$out = new ByteBufferWriter();
		CommonTypes::putItemStackWithoutStackId($out, ItemStack::null(), $protocolId);
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? '000000000000' : '00', bin2hex($out->getData()));
		self::assertSame(0, CommonTypes::getItemStackWithoutStackId(new ByteBufferReader($out->getData()), $protocolId)->getId());
	}

	#[DataProvider('protocolVersions')]
	public function testEntityMetadataExactWireFormat(int $protocolId) : void{
		$out = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($out, [5 => new IntMetadataProperty(12)], $protocolId);
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? '0105020218' : '01050218', bin2hex($out->getData()));
		$decoded = CommonTypes::getEntityMetadata(new ByteBufferReader($out->getData()), $protocolId);
		self::assertInstanceOf(IntMetadataProperty::class, $decoded[5]);
		self::assertSame(12, $decoded[5]->getValue());
	}

	public function testNewEntityMetadataRejectsMismatchedTypes() : void{
		$this->expectException(PacketDecodeException::class);
		CommonTypes::getEntityMetadata(new ByteBufferReader("\x01\x05\x02\x03\x18"), ProtocolInfo::PROTOCOL_1_26_40);
	}

	public function testNewEntityMetadataRejectsDuplicateKeys() : void{
		$this->expectException(PacketDecodeException::class);
		CommonTypes::getEntityMetadata(new ByteBufferReader("\x02\x05\x02\x02\x18\x05"), ProtocolInfo::PROTOCOL_1_26_40);
	}

	private static function createSkin() : SkinData{
		$image = new SkinImage(1, 1, "\x01\x02\x03\x04");
		return new SkinData(
			'skin',
			'play',
			'{}',
			$image,
			[new SkinAnimation($image, 2, 1.5, 3)],
			new SkinImage(0, 0, ''),
			'{}',
			'1',
			'',
			'',
			'full',
			SkinData::ARM_SIZE_SLIM,
			'#01020304',
			[new PersonaSkinPiece('piece', PersonaSkinPiece::PIECE_TYPE_PERSONA_HAIR, '00000000-0000-0000-0000-000000000001', true, 'product')],
			[new PersonaPieceTintColor(PersonaPieceTintColor::PIECE_TYPE_PERSONA_HAIR, ['#00000001', '#00000002', '#00000003', '#00000004'])],
			true,
			false,
			true,
			false,
			true,
			true,
			'true',
			'hash'
		);
	}

	#[DataProvider('protocolVersions')]
	public function testSkinRoundTrip(int $protocolId) : void{
		$out = new ByteBufferWriter();
		CommonTypes::putSkin($out, self::createSkin(), $protocolId);
		$decoded = CommonTypes::getSkin(new ByteBufferReader($out->getData()), $protocolId);
		self::assertSame('skin', $decoded->getSkinId());
		self::assertSame(SkinData::ARM_SIZE_SLIM, $decoded->getArmSize());
		self::assertSame('#01020304', $decoded->getSkinColor());
		self::assertSame(PersonaSkinPiece::PIECE_TYPE_PERSONA_HAIR, $decoded->getPersonaPieces()[0]->getPieceType());
		self::assertSame('00000000-0000-0000-0000-000000000001', $decoded->getPersonaPieces()[0]->getPackId());
		self::assertSame(['#00000001', '#00000002', '#00000003', '#00000004'], $decoded->getPieceTintColors()[0]->getColors());
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? 'hash' : '', $decoded->getProfileHash());
	}

	#[DataProvider('protocolVersions')]
	public function testPlayerListRoundTrip(int $protocolId) : void{
		$uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
		$addition = PlayerListPacket::add([PlayerListEntry::createAdditionEntry($uuid, 7, 'player', self::createSkin())]);
		$decodedAddition = new PlayerListPacket();
		$decodedAddition->decode(new ByteBufferReader(self::encodePacket($addition, $protocolId)), $protocolId);
		self::assertSame(PlayerListPacket::TYPE_ADD, $decodedAddition->type);
		self::assertSame('player', $decodedAddition->entries[0]->username);

		$removal = PlayerListPacket::remove([PlayerListEntry::createRemovalEntry($uuid)]);
		$encodedRemoval = self::encodePacket($removal, $protocolId);
		$decodedRemoval = new PlayerListPacket();
		$decodedRemoval->decode(new ByteBufferReader($encodedRemoval), $protocolId);
		self::assertSame(PlayerListPacket::TYPE_REMOVE, $decodedRemoval->type);
		self::assertSame($uuid->toString(), $decodedRemoval->entries[0]->uuid->toString());
	}

	#[DataProvider('protocolVersions')]
	public function testPlayerSkinRoundTrip(int $protocolId) : void{
		$packet = PlayerSkinPacket::create(Uuid::fromString('00000000-0000-0000-0000-000000000001'), 'old', 'new', self::createSkin());
		$decoded = new PlayerSkinPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame('new', $decoded->newSkinName);
		self::assertSame('old', $decoded->oldSkinName);
		self::assertTrue($decoded->skin->isVerified());
	}

	#[DataProvider('protocolVersions')]
	public function testCreativeContentWithEmptyIconsRoundTrip(int $protocolId) : void{
		$packet = CreativeContentPacket::create([new CreativeGroupEntry(1, 'group', ItemStack::null())], [new CreativeItemEntry(2, ItemStack::null(), 0)]);
		$decoded = new CreativeContentPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertSame(0, $decoded->getGroups()[0]->getIcon()->getId());
		self::assertSame(0, $decoded->getItems()[0]->getItem()->getId());
	}

	#[DataProvider('protocolVersions')]
	public function testRecipeIngredientRoundTrip(int $protocolId) : void{
		$descriptors = [null, new TagItemDescriptor('minecraft:logs')];
		$descriptors[] = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40
			? new StringIdMetaItemDescriptor('minecraft:stick', 2)
			: new IntIdMetaItemDescriptor(7, 2);
		foreach($descriptors as $descriptor){
			$out = new ByteBufferWriter();
			CommonTypes::putRecipeIngredient($out, new RecipeIngredient($descriptor, 3), $protocolId);
			$decoded = CommonTypes::getRecipeIngredient(new ByteBufferReader($out->getData()), $protocolId);
			self::assertSame(3, $decoded->getCount());
			if($descriptor === null){
				self::assertNull($decoded->getDescriptor());
			}else{
				self::assertInstanceOf($descriptor::class, $decoded->getDescriptor());
			}
		}
	}

	#[DataProvider('protocolVersions')]
	public function testCraftingDataRoundTrip(int $protocolId) : void{
		$descriptor = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40
			? new StringIdMetaItemDescriptor('minecraft:stick', 0)
			: new IntIdMetaItemDescriptor(7, 0);
		$ingredient = new RecipeIngredient($descriptor, 1);
		$output = new ItemStack(7, 0, 1, 0, '');
		$uuid = Uuid::fromString('00000000-0000-0000-0000-000000000001');
		$unlocking = new RecipeUnlockingRequirement(null);
		$shaped = new ShapedRecipe(CraftingDataPacket::ENTRY_SHAPED, 'shaped', [[$ingredient]], [$output], $uuid, 'crafting_table', 1, true, $unlocking, 2);
		$shapeless = new ShapelessRecipe(CraftingDataPacket::ENTRY_SHAPELESS, 'shapeless', [$ingredient], [$output], $uuid, 'crafting_table', 1, $unlocking, 3);
		$packet = CraftingDataPacket::create([$shapeless, $shaped], [], [], [], true);
		$decoded = new CraftingDataPacket();
		$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
		self::assertCount(2, $decoded->recipesWithTypeIds);
		self::assertTrue($decoded->cleanRecipes);
		self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? CraftingDataPacket::ENTRY_SHAPED : CraftingDataPacket::ENTRY_SHAPELESS, $decoded->recipesWithTypeIds[0]->getTypeId());
	}

	#[DataProvider('protocolVersions')]
	public function testEmptyCraftingDataExactWireFormat(int $protocolId) : void{
		$packet = CraftingDataPacket::create([], [], [], [], false);
		$encoded = self::encodePacket($packet, $protocolId);
		self::assertStringEndsWith($protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? str_repeat("\x00", 12) : str_repeat("\x00", 5), $encoded);
	}

	#[DataProvider('protocolVersions')]
	public function testAutomaticCraftingStackRequestRoundTrip(int $protocolId) : void{
		$descriptor = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40
			? new StringIdMetaItemDescriptor('minecraft:stick', 0)
			: new IntIdMetaItemDescriptor(7, 0);
		$action = new CraftRecipeAutoStackRequestAction(4, 2, 2, [new RecipeIngredient($descriptor, 3)]);
		$out = new ByteBufferWriter();
		$action->write($out, $protocolId);
		$decoded = CraftRecipeAutoStackRequestAction::read(new ByteBufferReader($out->getData()), $protocolId);
		self::assertSame(4, $decoded->getRecipeId());
		self::assertSame(2, $decoded->getRepetitions());
		self::assertSame(3, $decoded->getIngredients()[0]->getCount());
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

	#[DataProvider('protocolVersions')]
	public function testSubChunkRoundTrip(int $protocolId) : void{
		foreach([false, true] as $cacheEnabled){
			$base = new SubChunkPacketEntryCommon(new SubChunkPositionOffset(1, 0, -1), SubChunkRequestResult::SUCCESS, 'terrain', null, null);
			$entries = $cacheEnabled
				? new SubChunkPacketEntryWithCacheList([new SubChunkPacketEntryWithCache($base, 17)])
				: new SubChunkPacketEntryWithoutCacheList([new SubChunkPacketEntryWithoutCache($base)]);
			$packet = SubChunkPacket::create(0, new SubChunkPosition(3, 4, -2), $entries);
			$decoded = new SubChunkPacket();
			$decoded->decode(new ByteBufferReader(self::encodePacket($packet, $protocolId)), $protocolId);
			self::assertSame($cacheEnabled, $decoded->isCacheEnabled());
			self::assertSame(3, $decoded->getBaseSubChunkPosition()->getX());
			self::assertSame('terrain', $decoded->getEntries()->getEntries()[0]->getBase()->getTerrainData());
		}
	}
}
