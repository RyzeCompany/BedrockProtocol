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

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntry;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryCommon;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketHeightMapInfo;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketHeightMapType;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithCache as EntryWithBlobHash;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithCacheList as ListWithBlobHashes;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithoutCache as EntryWithoutBlobHash;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntryWithoutCacheList as ListWithoutBlobHashes;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkRequestResult;
use function count;

class SubChunkPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::SUB_CHUNK_PACKET;

	private int $dimension;
	private SubChunkPosition $baseSubChunkPosition;
	private ListWithBlobHashes|ListWithoutBlobHashes $entries;

	/**
	 * @generate-create-func
	 */
	public static function create(int $dimension, SubChunkPosition $baseSubChunkPosition, ListWithBlobHashes|ListWithoutBlobHashes $entries) : self{
		$result = new self;
		$result->dimension = $dimension;
		$result->baseSubChunkPosition = $baseSubChunkPosition;
		$result->entries = $entries;
		return $result;
	}

	public function isCacheEnabled() : bool{ return $this->entries instanceof ListWithBlobHashes; }

	public function getDimension() : int{ return $this->dimension; }

	public function getBaseSubChunkPosition() : SubChunkPosition{ return $this->baseSubChunkPosition; }

	public function getEntries() : ListWithBlobHashes|ListWithoutBlobHashes{ return $this->entries; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$cacheEnabled = CommonTypes::getBool($in);
		$this->dimension = VarInt::readSignedInt($in);
		$this->baseSubChunkPosition = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? SubChunkPosition::readFixedInts($in) : SubChunkPosition::readVarInts($in);

		$count = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40 ? VarInt::readUnsignedInt($in) : LE::readUnsignedInt($in);
		if($cacheEnabled){
			$entries = [];
			for($i = 0; $i < $count; $i++){
				if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
					$entry = SubChunkPacketEntry::read($in);
					$hash = $entry->getUsedBlobHash();
					if($hash === null){
						throw new PacketDecodeException("Cached subchunk entry has no blob hash");
					}
					$entries[] = new EntryWithBlobHash(self::toLegacyEntry($entry), $hash);
				}else{
					$entries[] = EntryWithBlobHash::read($in, $protocolId);
				}
			}
			$this->entries = new ListWithBlobHashes($entries);
		}else{
			$entries = [];
			for($i = 0; $i < $count; $i++){
				$entries[] = $protocolId >= ProtocolInfo::PROTOCOL_1_26_40
					? new EntryWithoutBlobHash(self::toLegacyEntry(SubChunkPacketEntry::read($in)))
					: EntryWithoutBlobHash::read($in, $protocolId);
			}
			$this->entries = new ListWithoutBlobHashes($entries);
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putBool($out, $this->entries instanceof ListWithBlobHashes);
		VarInt::writeSignedInt($out, $this->dimension);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$this->baseSubChunkPosition->writeFixedInts($out);
			VarInt::writeUnsignedInt($out, count($this->entries->getEntries()));
		}else{
			$this->baseSubChunkPosition->writeVarInts($out);
			LE::writeUnsignedInt($out, count($this->entries->getEntries()));
		}

		foreach($this->entries->getEntries() as $entry){
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				$base = $entry->getBase();
				$heightMap = $base->getHeightMap();
				$renderHeightMap = $base->getRenderHeightMap();
				$cacheEnabled = $entry instanceof EntryWithBlobHash;
				$terrain = $cacheEnabled && $base->getRequestResult() === SubChunkRequestResult::SUCCESS_ALL_AIR ? null : $base->getTerrainData();
				(new SubChunkPacketEntry(
					$base->getOffset(),
					$base->getRequestResult(),
					$terrain,
					self::getHeightMapType($heightMap, false),
					self::getHeightMapPayload($heightMap),
					self::getHeightMapType($renderHeightMap, true),
					self::getHeightMapPayload($renderHeightMap),
					$cacheEnabled ? $entry->getUsedBlobHash() : null
				))->write($out);
			}else{
				$entry->write($out, $protocolId);
			}
		}
	}

	private static function getHeightMapType(?SubChunkPacketHeightMapInfo $map, bool $render) : int{
		if($map === null){
			return $render ? SubChunkPacketHeightMapType::ALL_COPIED : SubChunkPacketHeightMapType::NO_DATA;
		}
		if($map->isAllTooLow()){
			return SubChunkPacketHeightMapType::ALL_TOO_LOW;
		}
		if($map->isAllTooHigh()){
			return SubChunkPacketHeightMapType::ALL_TOO_HIGH;
		}
		return SubChunkPacketHeightMapType::DATA;
	}

	private static function getHeightMapPayload(?SubChunkPacketHeightMapInfo $map) : ?SubChunkPacketHeightMapInfo{
		return $map !== null && !$map->isAllTooLow() && !$map->isAllTooHigh() ? $map : null;
	}

	private static function toLegacyEntry(SubChunkPacketEntry $entry) : SubChunkPacketEntryCommon{
		$heightMap = match($entry->getHeightMapType()){
			SubChunkPacketHeightMapType::NO_DATA => null,
			SubChunkPacketHeightMapType::DATA => $entry->getHeightMapData(),
			SubChunkPacketHeightMapType::ALL_TOO_HIGH => SubChunkPacketHeightMapInfo::allTooHigh(),
			SubChunkPacketHeightMapType::ALL_TOO_LOW => SubChunkPacketHeightMapInfo::allTooLow(),
			default => throw new PacketDecodeException("Unknown heightmap data type " . $entry->getHeightMapType())
		};
		$renderHeightMap = match($entry->getRenderHeightMapType()){
			SubChunkPacketHeightMapType::NO_DATA, SubChunkPacketHeightMapType::ALL_COPIED => null,
			SubChunkPacketHeightMapType::DATA => $entry->getRenderHeightMapData(),
			SubChunkPacketHeightMapType::ALL_TOO_HIGH => SubChunkPacketHeightMapInfo::allTooHigh(),
			SubChunkPacketHeightMapType::ALL_TOO_LOW => SubChunkPacketHeightMapInfo::allTooLow(),
			default => throw new PacketDecodeException("Unknown render heightmap data type " . $entry->getRenderHeightMapType())
		};
		return new SubChunkPacketEntryCommon($entry->getOffset(), $entry->getRequestResult(), $entry->getTerrainData() ?? "", $heightMap, $renderHeightMap);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleSubChunk($this);
	}
}
