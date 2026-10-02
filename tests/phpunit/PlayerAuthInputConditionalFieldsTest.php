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
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\ItemInteractionData;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputVehicleInfo;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\PredictedResult;
use pocketmine\network\mcpe\protocol\types\inventory\TriggerType;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;

final class PlayerAuthInputConditionalFieldsTest extends TestCase{
	public static function cases() : iterable{
		foreach([1001, 2168, 2169, 2193] as $protocol){
			for($mask = 0; $mask < 16; ++$mask){
				yield "$protocol/$mask" => [$protocol, $mask];
			}
		}
	}

	#[DataProvider('cases')]
	public function testConditionalPayloadsDoNotConsumeNeighbouringFields(int $protocol, int $mask) : void{
		$interaction = ($mask & 1) === 0 ? null : new ItemInteractionData(0, [], UseItemTransactionData::new(
			[], UseItemTransactionData::ACTION_CLICK_BLOCK, TriggerType::PLAYER_INPUT,
			new BlockPosition(7, 64, -3), 1, 0, ItemStackWrapper::legacy(ItemStack::null()),
			new Vector3(1, 64, 2), new Vector3(0, 0, 0), 0, PredictedResult::SUCCESS, 0
		));
		$request = ($mask & 2) === 0 ? null : new ItemStackRequest(17, [], [], 0);
		$blocks = ($mask & 4) === 0 ? null : [new PlayerBlockActionWithBlockInfo(PlayerAction::START_BREAK, new BlockPosition(7, 64, -3), 1)];
		$vehicle = ($mask & 8) === 0 ? null : new PlayerAuthInputVehicleInfo(12, 34, 56);
		$flags = new BitSet(PlayerAuthInputFlags::NUMBER_OF_FLAGS);
		// These neighbouring flags must never imply interaction or vehicle payloads.
		$flags->set(PlayerAuthInputFlags::STOP_GLIDING, true);
		$flags->set(PlayerAuthInputFlags::ACK_ACTOR_DATA, true);
		$packet = PlayerAuthInputPacket::create(
			new Vector3(1, 64, 2), 1, 2, 3, 0.25, 0.5,
			$flags, 1, 0, 1, null, new Vector2(0, 0), 10,
			new Vector3(0, 0, 0), $interaction, $request, $blocks, $vehicle,
			0.25, 0.5, new Vector3(1, 2, 3), new Vector2(4, 5)
		);
		$writer = new ByteBufferWriter();
		$packet->encode($writer, $protocol);
		$reader = new ByteBufferReader($writer->getData());
		$decoded = new PlayerAuthInputPacket();
		$decoded->decode($reader, $protocol);
		self::assertSame(0, $reader->getUnreadLength());
		self::assertSame($interaction?->getRequestId(), $decoded->getItemInteractionData()?->getRequestId());
		self::assertEquals($interaction?->getTransactionData()->getActions(), $decoded->getItemInteractionData()?->getTransactionData()->getActions());
		self::assertEquals($request, $decoded->getItemStackRequest());
		self::assertEquals($blocks, $decoded->getBlockActions());
		self::assertEquals($vehicle, $decoded->getVehicleInfo());
		$again = new ByteBufferWriter();
		$decoded->encode($again, $protocol);
		self::assertSame($writer->getData(), $again->getData());
	}
}
