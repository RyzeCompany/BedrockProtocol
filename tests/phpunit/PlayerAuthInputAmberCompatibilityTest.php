<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

final class PlayerAuthInputAmberCompatibilityTest extends TestCase{
	public static function packets() : iterable{
		// Independently encoded by Amber-PM/BedrockProtocol at
		// 3fc30afe680768c81cd3b748d1ff595142c7d9bc, using protocol 2193.
		// These are interoperability fixtures, not captured client packets.
		foreach(json_decode(file_get_contents(__DIR__ . '/fixtures/player_auth_input_2193_amber.json'), true, flags: JSON_THROW_ON_ERROR) as $name => $fixture){
			yield $name => [$name, $fixture['hex'], $fixture['actions']];
		}
	}

	#[DataProvider('packets')]
	public function testDecodeIndependentPacket(string $name, string $hex, ?array $expectedActions) : void{
		$bytes = hex2bin($hex);
		$reader = new ByteBufferReader($bytes);
		$packet = new PlayerAuthInputPacket();
		$packet->decode($reader, 2193);
		self::assertSame(0, $reader->getUnreadLength());
		self::assertEquals(new Vector3(-123, 68, 538), $packet->getPosition());
		self::assertSame(0.25, $packet->getAnalogMoveVecX());
		self::assertSame(0.5, $packet->getAnalogMoveVecZ());
		self::assertEquals(new Vector3(1, 2, 3), $packet->getCameraOrientation());
		self::assertEquals(new Vector2(4, 5), $packet->getRawMove());
		if($expectedActions === null){
			self::assertNull($packet->getBlockActions());
		}else{
			self::assertSame($expectedActions, array_map(static fn($action) => $action->getActionType(), $packet->getBlockActions()));
			foreach($packet->getBlockActions() as $action){
				self::assertEquals(new BlockPosition(-123, 68, 538), $action->getBlockPosition());
				self::assertSame(1, $action->getFace());
			}
		}
		self::assertSame($name === 'vehicle' ? 56 : null, $packet->getVehicleInfo()?->getPredictedVehicleActorUniqueId());
		self::assertSame($name === 'request' ? 17 : null, $packet->getItemStackRequest()?->getRequestId());
		$writer = new ByteBufferWriter();
		$packet->encode($writer, 2193);
		self::assertSame($bytes, $writer->getData());
	}
}
