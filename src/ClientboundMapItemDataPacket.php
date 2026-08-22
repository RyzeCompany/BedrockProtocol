<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\color\Color;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\MapDecoration;
use pocketmine\network\mcpe\protocol\types\MapImage;
use pocketmine\network\mcpe\protocol\types\MapTrackedObject;
use pocketmine\utils\Binary;
use function count;

class ClientboundMapItemDataPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_MAP_ITEM_DATA_PACKET;

	public const BITFLAG_TEXTURE_UPDATE = 0x02;
	public const BITFLAG_DECORATION_UPDATE = 0x04;
	public const BITFLAG_MAP_CREATION = 0x08;

	public int $mapId;
	public int $type;
	public int $dimensionId = DimensionIds::OVERWORLD;
	public bool $isLocked = false;
	public BlockPosition $origin;

	/** @var int[] */
	public array $parentMapIds = [];
	public int $scale;

	/** @var MapTrackedObject[] */
	public array $trackedEntities = [];
	/** @var MapDecoration[] */
	public array $decorations = [];

	public int $xOffset = 0;
	public int $yOffset = 0;
	public ?MapImage $colors = null;

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->mapId = CommonTypes::getActorUniqueId($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$this->decodeModernPayload($in);
			return;
		}
		$this->type = VarInt::readUnsignedInt($in);
		$this->dimensionId = Byte::readUnsigned($in);
		$this->isLocked = CommonTypes::getBool($in);
		$this->origin = CommonTypes::getBlockPosition($in);

		if(($this->type & self::BITFLAG_MAP_CREATION) !== 0){
			$count = VarInt::readUnsignedInt($in);
			for($i = 0; $i < $count; ++$i){
				$this->parentMapIds[] = CommonTypes::getActorUniqueId($in);
			}
		}

		if(($this->type & (self::BITFLAG_MAP_CREATION | self::BITFLAG_DECORATION_UPDATE | self::BITFLAG_TEXTURE_UPDATE)) !== 0){ //Decoration bitflag or colour bitflag
			$this->scale = Byte::readUnsigned($in);
		}

		if(($this->type & self::BITFLAG_DECORATION_UPDATE) !== 0){
			for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
				$object = new MapTrackedObject();
				$object->type = LE::readUnsignedInt($in);
				if($object->type === MapTrackedObject::TYPE_BLOCK){
					$object->blockPosition = CommonTypes::getBlockPosition($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
				}elseif($object->type === MapTrackedObject::TYPE_ENTITY){
					$object->actorUniqueId = CommonTypes::getActorUniqueId($in);
				}else{
					throw new PacketDecodeException("Unknown map object type $object->type");
				}
				$this->trackedEntities[] = $object;
			}

			for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
				$icon = Byte::readUnsigned($in);
				$rotation = Byte::readUnsigned($in);
				$xOffset = Byte::readUnsigned($in);
				$yOffset = Byte::readUnsigned($in);
				$label = CommonTypes::getString($in);
				$color = Color::fromRGBA(Binary::flipIntEndianness(VarInt::readUnsignedInt($in)));
				$this->decorations[] = new MapDecoration($icon, $rotation, $xOffset, $yOffset, $label, $color);
			}
		}

		if(($this->type & self::BITFLAG_TEXTURE_UPDATE) !== 0){
			$width = VarInt::readSignedInt($in);
			$height = VarInt::readSignedInt($in);
			$this->xOffset = VarInt::readSignedInt($in);
			$this->yOffset = VarInt::readSignedInt($in);

			$count = VarInt::readUnsignedInt($in);
			if($count !== $width * $height){
				throw new PacketDecodeException("Expected colour count of " . ($height * $width) . " (height $height * width $width), got $count");
			}

			$this->colors = MapImage::decode($in, $height, $width);
		}
	}

	private function decodeModernPayload(ByteBufferReader $in) : void{
		$this->type = 0;
		$this->dimensionId = Byte::readUnsigned($in);
		$this->isLocked = CommonTypes::getBool($in);
		$this->origin = CommonTypes::getBlockPosition($in);

		$parentMapIds = CommonTypes::readOptional($in, static fn(ByteBufferReader $in) : array => CommonTypes::readList($in, CommonTypes::getActorUniqueId(...)));
		$this->parentMapIds = $parentMapIds ?? [];
		if($parentMapIds !== null){
			$this->type |= self::BITFLAG_MAP_CREATION;
		}
		$this->scale = CommonTypes::readOptional($in, Byte::readUnsigned(...)) ?? 0;

		$trackedEntities = CommonTypes::readOptional($in, static fn(ByteBufferReader $in) : array => CommonTypes::readList($in, static function(ByteBufferReader $in) : MapTrackedObject{
			$object = new MapTrackedObject();
			$object->type = LE::readUnsignedInt($in);
			if($object->type === MapTrackedObject::TYPE_BLOCK){
				$object->blockPosition = CommonTypes::getBlockPosition($in);
			}elseif($object->type === MapTrackedObject::TYPE_ENTITY){
				$object->actorUniqueId = CommonTypes::getActorUniqueId($in);
			}else{
				throw new PacketDecodeException("Unknown map object type $object->type");
			}
			return $object;
		}));
		$this->trackedEntities = $trackedEntities ?? [];

		$decorations = CommonTypes::readOptional($in, static fn(ByteBufferReader $in) : array => CommonTypes::readList($in, static function(ByteBufferReader $in) : MapDecoration{
			$icon = Byte::readUnsigned($in);
			$rotation = Byte::readUnsigned($in);
			$xOffset = Byte::readUnsigned($in);
			$yOffset = Byte::readUnsigned($in);
			$label = CommonTypes::getString($in);
			return new MapDecoration($icon, $rotation, $xOffset, $yOffset, $label, CommonTypes::readColor($in));
		}));
		$this->decorations = $decorations ?? [];
		if($trackedEntities !== null || $decorations !== null){
			$this->type |= self::BITFLAG_DECORATION_UPDATE;
		}

		$width = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
		$height = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
		$xOffset = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
		$yOffset = CommonTypes::readOptional($in, VarInt::readSignedInt(...));
		$this->xOffset = $xOffset ?? 0;
		$this->yOffset = $yOffset ?? 0;
		$this->colors = CommonTypes::readOptional($in, static function(ByteBufferReader $in) use ($width, $height) : MapImage{
			if($width === null || $height === null){
				throw new PacketDecodeException("Expected both width and height to be present if colors are present");
			}
			$count = VarInt::readUnsignedInt($in);
			if($count !== $width * $height){
				throw new PacketDecodeException("Expected colour count of " . ($height * $width) . " (height $height * width $width), got $count");
			}
			return MapImage::decode($in, $height, $width);
		});
		if($this->colors !== null){
			$this->type |= self::BITFLAG_TEXTURE_UPDATE;
		}elseif($xOffset !== null || $yOffset !== null){
			throw new PacketDecodeException("Expected both xOffset and yOffset to be null if colors are null");
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putActorUniqueId($out, $this->mapId);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$this->encodeModernPayload($out);
			return;
		}

		$type = 0;
		if(($parentMapIdsCount = count($this->parentMapIds)) > 0){
			$type |= self::BITFLAG_MAP_CREATION;
		}
		if(($decorationCount = count($this->decorations)) > 0){
			$type |= self::BITFLAG_DECORATION_UPDATE;
		}
		if($this->colors !== null){
			$type |= self::BITFLAG_TEXTURE_UPDATE;
		}

		VarInt::writeUnsignedInt($out, $type);
		Byte::writeUnsigned($out, $this->dimensionId);
		CommonTypes::putBool($out, $this->isLocked);
		CommonTypes::putBlockPosition($out, $this->origin);

		if(($type & self::BITFLAG_MAP_CREATION) !== 0){
			VarInt::writeUnsignedInt($out, $parentMapIdsCount);
			foreach($this->parentMapIds as $parentMapId){
				CommonTypes::putActorUniqueId($out, $parentMapId);
			}
		}

		if(($type & (self::BITFLAG_MAP_CREATION | self::BITFLAG_TEXTURE_UPDATE | self::BITFLAG_DECORATION_UPDATE)) !== 0){
			Byte::writeUnsigned($out, $this->scale);
		}

		if(($type & self::BITFLAG_DECORATION_UPDATE) !== 0){
			VarInt::writeUnsignedInt($out, count($this->trackedEntities));
			foreach($this->trackedEntities as $object){
				LE::writeUnsignedInt($out, $object->type);
				if($object->type === MapTrackedObject::TYPE_BLOCK){
					CommonTypes::putBlockPosition($out, $object->blockPosition, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
				}elseif($object->type === MapTrackedObject::TYPE_ENTITY){
					CommonTypes::putActorUniqueId($out, $object->actorUniqueId);
				}else{
					throw new \InvalidArgumentException("Unknown map object type $object->type");
				}
			}

			VarInt::writeUnsignedInt($out, $decorationCount);
			foreach($this->decorations as $decoration){
				Byte::writeUnsigned($out, $decoration->getIcon());
				Byte::writeUnsigned($out, $decoration->getRotation());
				Byte::writeUnsigned($out, $decoration->getXOffset());
				Byte::writeUnsigned($out, $decoration->getYOffset());
				CommonTypes::putString($out, $decoration->getLabel());
				VarInt::writeUnsignedInt($out, Binary::flipIntEndianness($decoration->getColor()->toRGBA()));
			}
		}

		if($this->colors !== null){
			VarInt::writeSignedInt($out, $this->colors->getWidth());
			VarInt::writeSignedInt($out, $this->colors->getHeight());
			VarInt::writeSignedInt($out, $this->xOffset);
			VarInt::writeSignedInt($out, $this->yOffset);

			VarInt::writeUnsignedInt($out, $this->colors->getWidth() * $this->colors->getHeight()); //list count, but we handle it as a 2D array... thanks for the confusion mojang

			$this->colors->encode($out);
		}
	}

	private function encodeModernPayload(ByteBufferWriter $out) : void{
		Byte::writeUnsigned($out, $this->dimensionId);
		CommonTypes::putBool($out, $this->isLocked);
		CommonTypes::putBlockPosition($out, $this->origin);

		$parentMapIds = $this->parentMapIds !== [] ? $this->parentMapIds : null;
		$decorations = $this->decorations !== [] ? $this->decorations : null;
		$trackedEntities = $decorations !== null ? $this->trackedEntities : null;
		$hasScale = $parentMapIds !== null || $decorations !== null || $this->colors !== null;
		CommonTypes::writeOptional($out, $parentMapIds, static fn(ByteBufferWriter $out, array $ids) => CommonTypes::writeList($out, $ids, CommonTypes::putActorUniqueId(...)));
		CommonTypes::writeOptional($out, $hasScale ? $this->scale : null, Byte::writeUnsigned(...));
		CommonTypes::writeOptional($out, $trackedEntities, static fn(ByteBufferWriter $out, array $entities) => CommonTypes::writeList($out, $entities, static function(ByteBufferWriter $out, MapTrackedObject $object) : void{
			LE::writeUnsignedInt($out, $object->type);
			if($object->type === MapTrackedObject::TYPE_BLOCK){
				CommonTypes::putBlockPosition($out, $object->blockPosition);
			}elseif($object->type === MapTrackedObject::TYPE_ENTITY){
				CommonTypes::putActorUniqueId($out, $object->actorUniqueId);
			}else{
				throw new \InvalidArgumentException("Unknown map object type $object->type");
			}
		}));
		CommonTypes::writeOptional($out, $decorations, static fn(ByteBufferWriter $out, array $entries) => CommonTypes::writeList($out, $entries, static function(ByteBufferWriter $out, MapDecoration $decoration) : void{
			Byte::writeUnsigned($out, $decoration->getIcon());
			Byte::writeUnsigned($out, $decoration->getRotation());
			Byte::writeUnsigned($out, $decoration->getXOffset());
			Byte::writeUnsigned($out, $decoration->getYOffset());
			CommonTypes::putString($out, $decoration->getLabel());
			CommonTypes::writeColor($out, $decoration->getColor());
		}));
		$colors = $this->colors;
		CommonTypes::writeOptional($out, $colors?->getWidth(), VarInt::writeSignedInt(...));
		CommonTypes::writeOptional($out, $colors?->getHeight(), VarInt::writeSignedInt(...));
		CommonTypes::writeOptional($out, $colors !== null ? $this->xOffset : null, VarInt::writeSignedInt(...));
		CommonTypes::writeOptional($out, $colors !== null ? $this->yOffset : null, VarInt::writeSignedInt(...));
		CommonTypes::writeOptional($out, $colors, static function(ByteBufferWriter $out, MapImage $image) : void{
			VarInt::writeUnsignedInt($out, $image->getWidth() * $image->getHeight());
			$image->encode($out);
		});
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleClientboundMapItemData($this);
	}
}
