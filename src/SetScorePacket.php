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
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use function count;
use function version_compare;

class SetScorePacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::SET_SCORE_PACKET;

	public const TYPE_CHANGE = 0;
	public const TYPE_REMOVE = 1;

	private const ACTION_TYPES = [
		0 => "remove",
		1 => "changeplayer",
		2 => "changeentity",
		3 => "changefakeplayer"
	];

	public int $type;
	/** @var ScorePacketEntry[] */
	public array $entries = [];

	/**
	 * @generate-create-func
	 * @param ScorePacketEntry[] $entries
	 */
	public static function create(int $type, array $entries) : self{
		$result = new self;
		$result->type = $type;
		$result->entries = $entries;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$isHotfix44 = $this->usesHotfix44Scoreboard($protocolId);
			$this->entries = [];
			$this->type = self::TYPE_CHANGE;
			foreach(CommonTypes::readList($in, static function(ByteBufferReader $in) use ($isHotfix44) : array{
				$action = VarInt::readUnsignedInt($in);
				$innerType = CommonTypes::getString($in);
				if((self::ACTION_TYPES[$action] ?? null) !== $innerType){
					throw new PacketDecodeException("Unexpected scoreboard inner type $innerType for action $action");
				}
				$entry = new ScorePacketEntry();
				$entry->scoreboardId = VarInt::readSignedLong($in);
				if($action !== 0){
					$entry->objectiveName = CommonTypes::getString($in);
					$entry->type = $action;
					$entry->score = LE::readSignedInt($in);
					if($action === ScorePacketEntry::TYPE_PLAYER || $action === ScorePacketEntry::TYPE_ENTITY){
						$entry->actorUniqueId = CommonTypes::getActorUniqueId($in);
					}else{
						$entry->customName = CommonTypes::getString($in);
					}
				}else{
					$entry->objectiveName = $isHotfix44
						? CommonTypes::readOptional($in, static fn(ByteBufferReader $in) : ?string => CommonTypes::readOptional($in, CommonTypes::getString(...))) ?? ""
						: CommonTypes::readOptional($in, CommonTypes::getString(...)) ?? "";
					$entry->score = 0;
				}
				return [$action, $entry];
			}) as [$action, $entry]){
				$entryType = $action === 0 ? self::TYPE_REMOVE : self::TYPE_CHANGE;
				if($this->entries !== [] && $this->type !== $entryType){
					throw new PacketDecodeException("Mixed scoreboard change and removal actions are unsupported");
				}
				$this->type = $entryType;
				$this->entries[] = $entry;
			}
			return;
		}

		$this->type = Byte::readUnsigned($in);
		for($i = 0, $i2 = VarInt::readUnsignedInt($in); $i < $i2; ++$i){
			$entry = new ScorePacketEntry();
			$entry->scoreboardId = VarInt::readSignedLong($in);
			$entry->objectiveName = CommonTypes::getString($in);
			$entry->score = LE::readSignedInt($in);
			if($this->type !== self::TYPE_REMOVE){
				$entry->type = Byte::readUnsigned($in);
				switch($entry->type){
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						$entry->actorUniqueId = CommonTypes::getActorUniqueId($in);
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						$entry->customName = CommonTypes::getString($in);
						break;
					default:
						throw new PacketDecodeException("Unknown entry type $entry->type");
				}
			}
			$this->entries[] = $entry;
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$isHotfix44 = $this->usesHotfix44Scoreboard($protocolId);
			CommonTypes::writeList($out, $this->entries, function(ByteBufferWriter $out, ScorePacketEntry $entry) use ($isHotfix44) : void{
				$action = $this->type === self::TYPE_REMOVE ? 0 : $entry->type;
				$innerType = self::ACTION_TYPES[$action] ?? null;
				if($innerType === null){
					throw new \InvalidArgumentException("Unknown scoreboard action type $action");
				}
				VarInt::writeUnsignedInt($out, $action);
				CommonTypes::putString($out, $innerType);
				VarInt::writeSignedLong($out, $entry->scoreboardId);
				if($action === 0){
					$objectiveName = $entry->objectiveName !== "" ? $entry->objectiveName : null;
					if($isHotfix44){
						CommonTypes::writeOptional($out, $objectiveName, static function(ByteBufferWriter $out, string $objectiveName) : void{
							CommonTypes::writeOptional($out, $objectiveName, CommonTypes::putString(...));
						});
					}else{
						CommonTypes::writeOptional($out, $objectiveName, CommonTypes::putString(...));
					}
				}elseif($action === ScorePacketEntry::TYPE_PLAYER || $action === ScorePacketEntry::TYPE_ENTITY){
					CommonTypes::putString($out, $entry->objectiveName);
					LE::writeSignedInt($out, $entry->score);
					CommonTypes::putActorUniqueId($out, $entry->actorUniqueId ?? throw new \InvalidArgumentException("Actor unique ID must be set for player and entity scores"));
				}elseif($action === ScorePacketEntry::TYPE_FAKE_PLAYER){
					CommonTypes::putString($out, $entry->objectiveName);
					LE::writeSignedInt($out, $entry->score);
					CommonTypes::putString($out, $entry->customName ?? throw new \InvalidArgumentException("Custom name must be set for fake player scores"));
				}
			});
			return;
		}

		Byte::writeUnsigned($out, $this->type);
		VarInt::writeUnsignedInt($out, count($this->entries));
		foreach($this->entries as $entry){
			VarInt::writeSignedLong($out, $entry->scoreboardId);
			CommonTypes::putString($out, $entry->objectiveName);
			LE::writeSignedInt($out, $entry->score);
			if($this->type !== self::TYPE_REMOVE){
				Byte::writeUnsigned($out, $entry->type);
				switch($entry->type){
					case ScorePacketEntry::TYPE_PLAYER:
					case ScorePacketEntry::TYPE_ENTITY:
						CommonTypes::putActorUniqueId($out, $entry->actorUniqueId);
						break;
					case ScorePacketEntry::TYPE_FAKE_PLAYER:
						CommonTypes::putString($out, $entry->customName);
						break;
					default:
						throw new \InvalidArgumentException("Unknown entry type $entry->type");
				}
			}
		}
	}

	private function usesHotfix44Scoreboard(int $protocolId) : bool{
		$gameVersion = $this->getGameVersion();
		return $protocolId === ProtocolInfo::PROTOCOL_1_26_40 &&
			$gameVersion !== null &&
			version_compare($gameVersion, "1.26.44", ">=") &&
			version_compare($gameVersion, "1.26.45", "<");
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleSetScore($this);
	}
}
