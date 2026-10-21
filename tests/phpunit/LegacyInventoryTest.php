<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerUIIds;
use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeAutoStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponseSlotInfo;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;

final class LegacyInventoryTest extends TestCase{

	public function testIndependent419MoveRequestHasNoFilterTailAndTranslatesContainers() : void{
		// request=-1, one take action, count=2, inventory(28):0 stack=5 -> cursor(58):0 empty.
		$wire = hex2bin('010100021c000a3a0000');
		$in = new ByteBufferReader($wire);
		$request = ItemStackRequest::read($in, 419);
		self::assertSame(0, $in->getUnreadLength());
		self::assertSame(-1, $request->getRequestId());
		$action = $request->getActions()[0];
		self::assertSame(ContainerUIIds::INVENTORY, $action->getSource()->getContainerName()->getContainerId());
		self::assertSame(ContainerUIIds::CURSOR, $action->getDestination()->getContainerName()->getContainerId());
		self::assertSame(5, $action->getSource()->getStackId());
		$out = new ByteBufferWriter();
		$request->write($out, 419);
		self::assertSame($wire, $out->getData());
	}

	public function testIndependent419AutoCraftRequestRemapsActionAndHasNoIngredients() : void{
		$wire = hex2bin('03010b0a02'); // request=-2, action=11(auto), signed recipe=5, repetitions=2
		$in = new ByteBufferReader($wire);
		$request = ItemStackRequest::read($in, 419);
		$action = $request->getActions()[0];
		self::assertInstanceOf(CraftRecipeAutoStackRequestAction::class, $action);
		self::assertSame(5, $action->getRecipeId());
		self::assertSame(2, $action->getRepetitions());
		self::assertSame([], $action->getIngredients());
		self::assertSame(0, $in->getUnreadLength());
		$out = new ByteBufferWriter();
		$request->write($out, 419);
		self::assertSame($wire, $out->getData());
	}

	public function test419ResponseSlotStopsAfterStackId() : void{
		$wire = hex2bin('0202040a');
		$in = new ByteBufferReader($wire);
		$slot = ItemStackResponseSlotInfo::read($in, 419);
		self::assertSame(5, $slot->getItemStackId());
		self::assertSame(0, $in->getUnreadLength());
		$out = new ByteBufferWriter();
		$slot->write($out, 419);
		self::assertSame($wire, $out->getData());
	}

	public function test419ContentAndSlotPrefixEvenEmptyItemsWithStackId() : void{
		$content = InventoryContentPacket::create(0, [new ItemStackWrapper(0, ItemStack::null())], new FullContainerName(0), 0, new ItemStackWrapper(0, ItemStack::null()));
		$slot = InventorySlotPacket::create(0, 2, new FullContainerName(0), 0, null, new ItemStackWrapper(0, ItemStack::null()));
		foreach([[$content, '3100010000'], [$slot, '3200020000']] as [$packet, $hex]){
			$out = new ByteBufferWriter();
			$packet->encode($out, 419);
			self::assertSame($hex, bin2hex($out->getData()));
			$in = new ByteBufferReader($out->getData());
			$copy = new ($packet::class)();
			$copy->decode($in, 419);
			self::assertSame(0, $in->getUnreadLength());
			$again = new ByteBufferWriter();
			$copy->encode($again, 419);
			self::assertSame($out->getData(), $again->getData());
		}
	}

	public function test419IngredientsUseNumericTriplesAndSingleByteEmptySlots() : void{
		$out = new ByteBufferWriter();
		CommonTypes::putRecipeIngredient($out, new RecipeIngredient(null, 0), 419);
		CommonTypes::putRecipeIngredient($out, new RecipeIngredient(new IntIdMetaItemDescriptor(1, -1), 2), 419);
		self::assertSame('0002feff0304', bin2hex($out->getData()));
		$in = new ByteBufferReader($out->getData());
		self::assertNull(CommonTypes::getRecipeIngredient($in, 419)->getDescriptor());
		$ingredient = CommonTypes::getRecipeIngredient($in, 419);
		self::assertSame(32767, $ingredient->getDescriptor()->getMeta());
		self::assertSame(2, $ingredient->getCount());
		self::assertSame(0, $in->getUnreadLength());
	}

	public function test419MappingDoesNotMutateObjectsSentToModernSessions() : void{
		$name = new FullContainerName(ContainerUIIds::INVENTORY);
		$old = new ByteBufferWriter();
		$name->write($old, 419);
		$new = new ByteBufferWriter();
		$name->write($new, ProtocolInfo::PROTOCOL_1_20_0);
		self::assertSame('1c', bin2hex($old->getData()));
		self::assertSame('1d', bin2hex($new->getData()));
		self::assertSame(ContainerUIIds::INVENTORY, $name->getContainerId());
	}
}
