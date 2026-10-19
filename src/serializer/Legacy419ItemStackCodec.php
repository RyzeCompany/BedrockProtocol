<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\serializer;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\nbt\TreeRoot;
use pocketmine\nbt\NbtDataException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraData;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraDataShield;

/** 419 slot format; the native ItemStack model always retains modern extra-data encoding. */
final class Legacy419ItemStackCodec{
	private const SHIELD_ID = 355;

	public static function read(ByteBufferReader $in) : ItemStack{
		$id = VarInt::readSignedInt($in);
		if($id === 0){
			return ItemStack::null();
		}
		$aux = VarInt::readSignedInt($in);
		$nbtLength = LE::readUnsignedShort($in);
		$nbt = null;
		if($nbtLength === 0xffff){
			if(Byte::readUnsigned($in) !== 1){
				throw new PacketDecodeException("Unexpected legacy slot NBT version");
			}
			$offset = $in->getOffset();
			try{
				$nbt = (new NetworkNbtSerializer())->read($in->getData(), $offset, 512)->mustGetCompoundTag();
			}catch(NbtDataException $e){
				throw PacketDecodeException::wrap($e, "Invalid legacy item NBT");
			}
			$in->setOffset($offset);
		}elseif($nbtLength !== 0){
			throw new PacketDecodeException("Unexpected legacy slot NBT length $nbtLength");
		}
		$lists = [];
		for($list = 0; $list < 2; ++$list){
			$count = VarInt::readSignedInt($in);
			if($count < 0 || $count > 4096){
				throw new PacketDecodeException("Invalid legacy slot restriction count $count");
			}
			$entries = [];
			for($i = 0; $i < $count; ++$i){
				$entries[] = CommonTypes::getString($in);
			}
			$lists[] = $entries;
		}
		$extra = $id === self::SHIELD_ID ? new ItemStackExtraDataShield($nbt, $lists[0], $lists[1], VarInt::readSignedLong($in)) : new ItemStackExtraData($nbt, $lists[0], $lists[1]);
		$out = new ByteBufferWriter();
		$extra->write($out);
		return new ItemStack($id, $aux >> 8, $aux & 0xff, 0, $out->getData());
	}

	public static function write(ByteBufferWriter $out, ItemStack $item) : void{
		VarInt::writeSignedInt($out, $item->getId());
		if($item->getId() === 0){
			return;
		}
		VarInt::writeSignedInt($out, (($item->getMeta() & 0x7fff) << 8) | $item->getCount());
		$in = new ByteBufferReader($item->getRawExtraData());
		$extra = $item->getId() === self::SHIELD_ID ? ItemStackExtraDataShield::read($in) : ItemStackExtraData::read($in);
		if(($nbt = $extra->getNbt()) !== null){
			LE::writeUnsignedShort($out, 0xffff);
			Byte::writeUnsigned($out, 1);
			$out->writeByteArray((new NetworkNbtSerializer())->write(new TreeRoot($nbt)));
		}else{
			LE::writeUnsignedShort($out, 0);
		}
		foreach([$extra->getCanPlaceOn(), $extra->getCanDestroy()] as $entries){
			VarInt::writeSignedInt($out, count($entries));
			foreach($entries as $entry){
				CommonTypes::putString($out, $entry);
			}
		}
		if($extra instanceof ItemStackExtraDataShield){
			VarInt::writeSignedLong($out, $extra->getBlockingTick());
		}
	}
}
