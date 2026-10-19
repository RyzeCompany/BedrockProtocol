<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\FloatGameRule;
use pocketmine\network\mcpe\protocol\types\IntGameRule;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\NetworkPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\ServerAuthMovementMode;
use pocketmine\network\mcpe\protocol\types\ServerTelemetryData;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use pocketmine\network\mcpe\protocol\types\resourcepacks\BehaviorPackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use Ramsey\Uuid\Uuid;

final class LegacyWorldBootstrapTest extends TestCase{
	private const PACK_ID = '11111111-2222-3333-4444-555555555555';

	private static function fixtures() : array{
		return json_decode(file_get_contents(__DIR__ . '/fixtures/bootstrap_419.json'), true, flags: JSON_THROW_ON_ERROR);
	}

	private static function encode(DataPacket $packet, int $protocol = 419) : string{
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocol);
		return $out->getData();
	}

	private static function rules() : array{
		return ['dodaylightcycle' => new BoolGameRule(false, true), 'randomtickspeed' => new IntGameRule(3, true), 'testfloat' => new FloatGameRule(-0.75, true)];
	}

	private static function experiments() : Experiments{
		return new Experiments(['data_driven_items' => true], false);
	}

	private static function startGame() : StartGamePacket{
		$level = new LevelSettings();
		$level->seed = -123456789;
		$level->spawnSettings = new SpawnSettings(0, 'plains', 2);
		$level->worldGamemode = 1;
		$level->difficulty = 2;
		$level->spawnPosition = new BlockPosition(-12, 70, 34);
		$level->time = 6000;
		$level->rainLevel = 0.25;
		$level->lightningLevel = 0.5;
		$level->commandsEnabled = true;
		$level->xboxLiveBroadcastMode = 3;
		$level->platformBroadcastMode = 3;
		$level->gameRules = self::rules();
		$level->experiments = self::experiments();
		$level->hasBonusChestEnabled = true;
		$level->hasLockedResourcePack = true;
		$level->isWorldTemplateOptionLocked = true;
		$level->limitedWorldWidth = 128;
		$level->limitedWorldLength = 256;
		$level->experimentalGameplayOverride = false;
		// Modern-only values must not leak to 419 or be mutated for another recipient.
		$level->vanillaVersion = '1.26.52';
		$level->disablePersona = true;
		$level->createdInEditorMode = true;
		$level->hardcore = true;
		return StartGamePacket::create(-9, 42, 0, new Vector3(1.5, 65, -2.25), 10, -30,
			new CacheableNbt(new CompoundTag()), $level, 'test-level', 'HCF Legacy', '', false,
			new PlayerMovementSettings(ServerAuthMovementMode::SERVER_AUTHORITATIVE_V3, 50, true), 12345, -7,
			'corr-419', true, 'RyzeMP', Uuid::fromString(self::PACK_ID), true, true, true,
			new NetworkPermissions(true), true, null, new ServerTelemetryData('server', 'scenario', 'world', 'owner'),
			[new BlockPaletteEntry('minecraft:stone', new CacheableNbt(new CompoundTag()))], 0,
			[new ItemTypeEntry('minecraft:shield', 355, false, 2, new CacheableNbt(new CompoundTag())),
			 new ItemTypeEntry('minecraft:wither_rose', -216, true, 2, new CacheableNbt(new CompoundTag()))]);
	}

	public static function packets() : array{
		return [
			'start_game' => ['start_game', self::startGame()],
			'resource_packs_info' => ['resource_packs_info', ResourcePacksInfoPacket::create(
				[new ResourcePackInfoEntry(Uuid::fromString(self::PACK_ID), '2.3.4', 0x100000002, 'key-r', 'sub-r', 'cid-r', true, true, true, 'https://example.invalid/pack')],
				[new BehaviorPackInfoEntry('behavior', '1.0.0', 21, 'key-b', 'sub-b', 'cid-b', false, true)],
				true, true, true, true, [], Uuid::fromString(self::PACK_ID), 'modern', true)],
			'resource_pack_stack' => ['resource_pack_stack', ResourcePackStackPacket::create(
				[new ResourcePackStackEntry(self::PACK_ID, '2.3.4', 'sub-r')],
				[new ResourcePackStackEntry('behavior', '1.0.0', 'sub-b')], true, '1.26.52', self::experiments(), true)],
			'request_chunk_radius' => ['request_chunk_radius', RequestChunkRadiusPacket::create(8, 32)],
			'chunk_publisher' => ['chunk_publisher', NetworkChunkPublisherUpdatePacket::create(new BlockPosition(-12, 70, 34), 128, [new ChunkPosition(1, 2)])],
			'level_chunk' => ['level_chunk', LevelChunkPacket::create(new ChunkPosition(-3, 5), 2, 16, false, [0x0102030405060708, 0x1234], 'XYZ')],
			'game_rules_changed' => ['game_rules_changed', GameRulesChangedPacket::create(self::rules())],
		];
	}

	#[DataProvider('packets')]
	public function testHistoricalWireFixture(string $name, DataPacket $packet) : void{
		$expected = hex2bin(self::fixtures()[$name]);
		self::assertSame($expected, self::encode($packet));
		$decoded = new ($packet::class)();
		$reader = new ByteBufferReader($expected);
		$decoded->decode($reader, 419);
		self::assertSame(0, $reader->getUnreadLength());
		self::assertSame($expected, self::encode($decoded));
	}

	public function testStartGameUsesLegacyModelsWithoutModernTail() : void{
		$packet = new StartGamePacket();
		$packet->decode(new ByteBufferReader(hex2bin(self::fixtures()['start_game'])), 419);
		self::assertSame(-123456789, $packet->levelSettings->seed);
		self::assertSame('1.16.100', $packet->levelSettings->vanillaVersion);
		self::assertSame(ServerAuthMovementMode::SERVER_AUTHORITATIVE_V2, $packet->playerMovementSettings->getMovementType());
		self::assertSame(0, $packet->playerMovementSettings->getRewindHistorySize());
		self::assertFalse($packet->levelSettings->gameRules['dodaylightcycle']->isPlayerModifiable());
		self::assertSame(-216, $packet->itemTable[1]->getNumericId());
		self::assertSame('', $packet->serverSoftwareVersion);
		self::assertFalse($packet->networkPermissions->disableClientSounds());
	}

	public static function seeds() : array{
		return [[0, '00'], [2147483647, 'feffffff0f'], [-2147483648, 'ffffffff0f'], [0x11223344fedcba98, 'cf959a12'], [PHP_INT_MAX, '01'], [PHP_INT_MIN, '00']];
	}

	#[DataProvider('seeds')]
	public function testWorldSeedIsTruncatedOnlyOnTheWire(int $seed, string $expectedHex) : void{
		$packet = self::startGame();
		$packet->levelSettings->seed = $seed;
		$wire = self::encode($packet);
		$fixture = self::fixtures();
		$old = hex2bin($fixture['start_game']);
		$expected = substr($old, 0, $fixture['seed_offset']) . hex2bin($expectedHex) . substr($old, $fixture['seed_offset'] + $fixture['seed_size']);
		self::assertSame($expected, $wire);
		self::assertSame($seed, $packet->levelSettings->seed);
	}

	public function testEncoding419DoesNotAlterTheCurrentRecipientPacket() : void{
		foreach(ProtocolInfo::ACCEPTED_PROTOCOL as $protocol){
			foreach(self::packets() as [$name, $packet]){
				$before = self::encode($packet, $protocol);
				self::encode($packet, 419);
				self::assertSame($before, self::encode($packet, $protocol), "$name / $protocol");
			}
		}
	}

	public function test419RejectsModernSubchunkRequests() : void{
		$this->expectException(\InvalidArgumentException::class);
		self::encode(LevelChunkPacket::create(new ChunkPosition(0, 0), 0, 16, true, null, ''));
	}

	public function test419RejectsTallColumnsReceivedFromWire() : void{
		$this->expectException(PacketDecodeException::class);
		(new LevelChunkPacket())->decode(new ByteBufferReader("\x3a\x00\x00\x11\x00\x00"), 419);
	}

	public function test419MovementRejectsUnknownMode() : void{
		$this->expectException(PacketDecodeException::class);
		PlayerMovementSettings::read(new ByteBufferReader("\x04"), 419);
	}

	public function testLegacyClientAuthorityModeIsRepresentable() : void{
		$mode = PlayerMovementSettings::read(new ByteBufferReader("\x00"), 419);
		self::assertSame(ServerAuthMovementMode::LEGACY, $mode->getMovementType());
	}

	public function testTruncatedStartGameIsRejected() : void{
		$this->expectException(PacketDecodeException::class);
		(new StartGamePacket())->decode(new ByteBufferReader(substr(hex2bin(self::fixtures()['start_game']), 0, -1)), 419);
	}
}
