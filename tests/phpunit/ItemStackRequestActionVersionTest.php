<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftReservedStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingNonImplementedStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequestActionType;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\LabTableCombineStackRequestAction;

/**
 * Wire-format regression tests for versions before 1.26.40, 1.26.40-1.26.52,
 * and the changed action variants of 1.26.60 (2225).
 */
final class ItemStackRequestActionVersionTest extends TestCase{

	private function assertWireAction(
		int $protocol,
		object $action,
		?int $outerId,
		int $innerId
	) : void{
		$request = new ItemStackRequest(7, [$action], [], 0);
		$out = new ByteBufferWriter();
		$request->write($out, $protocol);
		$reader = new ByteBufferReader($out->getData());

		self::assertSame(7, CommonTypes::readItemStackRequestId($reader));
		self::assertSame(1, VarInt::readUnsignedInt($reader));
		if($outerId !== null){
			self::assertSame($outerId, VarInt::readUnsignedInt($reader));
		}
		self::assertSame($innerId, Byte::readUnsigned($reader));

		$decoded = ItemStackRequest::read(new ByteBufferReader($out->getData()), $protocol);
		self::assertCount(1, $decoded->getActions());
		self::assertInstanceOf($action::class, $decoded->getActions()[0]);

		$roundTrip = new ByteBufferWriter();
		$decoded->write($roundTrip, $protocol);
		self::assertSame($out->getData(), $roundTrip->getData());
	}

	public function testCraftResultsAndDeprecatedActionAcrossVersions() : void{
		foreach([
			// Before protocol 2168, only the inner ID was sent.
			[ProtocolInfo::PROTOCOL_1_26_30, null, 19, null, 18],
			// Protocols 2168/2169/2193 share the original outer/inner mapping.
			[ProtocolInfo::PROTOCOL_1_26_40, 17, 19, 16, 18],
			[ProtocolInfo::PROTOCOL_1_26_45, 17, 19, 16, 18],
			[ProtocolInfo::PROTOCOL_1_26_52, 17, 19, 16, 18],
			// 2225 adds the reserved action, shifting these variant and wire IDs.
			[ProtocolInfo::PROTOCOL_1_26_60, 18, 20, 17, 19],
		] as [$protocol, $resultsOuter, $resultsInner, $nonImplementedOuter, $nonImplementedInner]){
			$this->assertWireAction($protocol, new DeprecatedCraftingResultsStackRequestAction([], 1), $resultsOuter, $resultsInner);
			$this->assertWireAction($protocol, new DeprecatedCraftingNonImplementedStackRequestAction(), $nonImplementedOuter, $nonImplementedInner);
		}
	}

	public function testCommonActionRetainsVersionSpecificMapping() : void{
		$this->assertWireAction(ProtocolInfo::PROTOCOL_1_26_30, new LabTableCombineStackRequestAction(), null, 9);
		$this->assertWireAction(ProtocolInfo::PROTOCOL_1_26_40, new LabTableCombineStackRequestAction(), 7, 9);
		$this->assertWireAction(ProtocolInfo::PROTOCOL_1_26_52, new LabTableCombineStackRequestAction(), 7, 9);
		$this->assertWireAction(ProtocolInfo::PROTOCOL_1_26_60, new LabTableCombineStackRequestAction(), 7, 9);
	}

	public function testProtocol2225HasAll19OfficialWireVariants() : void{
		self::assertSame(
			[0, 1, 2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
			array_values(ItemStackRequestActionType::WIRE_TYPES_1_26_60)
		);
		self::assertCount(19, ItemStackRequestActionType::INTERNAL_TYPES_1_26_60);
		self::assertSame(
			ItemStackRequestActionType::CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING,
			ItemStackRequestActionType::INTERNAL_TYPES_1_26_60[18]
		);
		self::assertCount(18, ItemStackRequestActionType::INNER_TYPES_1_26_40);
	}

	public function testReservedActionOnlyExistsInProtocol2225() : void{
		$this->assertWireAction(ProtocolInfo::PROTOCOL_1_26_60, new CraftReservedStackRequestAction("minecraft:recipe", 2), 16, 18);

		$request = new ItemStackRequest(1, [new CraftReservedStackRequestAction("reserved", 1)], [], 0);
		$this->expectException(InvalidArgumentException::class);
		$request->write(new ByteBufferWriter(), ProtocolInfo::PROTOCOL_1_26_52);
	}

	public function testProtocol2225RejectsMismatchedOuterAndInner() : void{
		// 18/20 is valid for 2225; 18/19 is NOT valid.
		$out = new ByteBufferWriter();
		CommonTypes::writeItemStackRequestId($out, 7);
		VarInt::writeUnsignedInt($out, 1);
		VarInt::writeUnsignedInt($out, 18);
		Byte::writeUnsigned($out, 19);
		$this->expectException(PacketDecodeException::class);
		ItemStackRequest::read(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_26_60);
	}

	public function testProtocol2193RejectsProtocol2225ActionId() : void{
		$out = new ByteBufferWriter();
		CommonTypes::writeItemStackRequestId($out, 7);
		VarInt::writeUnsignedInt($out, 1);
		VarInt::writeUnsignedInt($out, 18);
		Byte::writeUnsigned($out, 20);
		$this->expectException(PacketDecodeException::class);
		ItemStackRequest::read(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_26_52);
	}
}
