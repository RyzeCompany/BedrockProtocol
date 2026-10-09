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
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\PlayerAction;


class PlayerActionPacket extends DataPacket implements ClientboundPacket, ServerboundPacket{
	/**
	 * PlayerActionType was renumbered in 2207. The internal constants follow the
	 * newer numbering; protocols up to 2193 use the legacy wire IDs.
	 * Source: Mojang Bedrock protocol docs, PlayerActionPacket 2193/2207.
	 *
	 * @var array<int, int> legacy wire ID => internal action ID
	 */
	private const LEGACY_ACTIONS = [
		0 => -1, // Unknown
		1 => PlayerAction::START_BREAK,
		2 => PlayerAction::ABORT_BREAK,
		3 => PlayerAction::STOP_BREAK,
		4 => PlayerAction::START_SLEEPING,
		5 => PlayerAction::STOP_SLEEPING,
		6 => PlayerAction::RESPAWN,
		7 => PlayerAction::JUMP,
		8 => PlayerAction::START_SPRINT,
		9 => PlayerAction::STOP_SPRINT,
		10 => PlayerAction::START_SNEAK,
		11 => PlayerAction::STOP_SNEAK,
		12 => PlayerAction::CREATIVE_PLAYER_DESTROY_BLOCK,
		13 => PlayerAction::DIMENSION_CHANGE_ACK,
		14 => PlayerAction::START_GLIDE,
		15 => PlayerAction::STOP_GLIDE,
		16 => PlayerAction::BUILD_DENIED,
		17 => PlayerAction::CRACK_BLOCK,
		18 => PlayerAction::START_SWIMMING,
		19 => PlayerAction::STOP_SWIMMING,
		20 => PlayerAction::START_SPIN_ATTACK,
		21 => PlayerAction::STOP_SPIN_ATTACK,
		22 => PlayerAction::PREDICT_DESTROY_BLOCK,
		23 => PlayerAction::CONTINUE_DESTROY_BLOCK,
		24 => PlayerAction::START_ITEM_USE_ON,
		25 => PlayerAction::STOP_ITEM_USE_ON,
		26 => PlayerAction::HANDLED_TELEPORT,
		27 => PlayerAction::MISSED_SWING,
		28 => PlayerAction::START_CRAWLING,
		29 => PlayerAction::STOP_CRAWLING,
		30 => PlayerAction::START_FLYING,
		31 => PlayerAction::STOP_FLYING,
		32 => PlayerAction::START_USING_ITEM,
		33 => 38, // InternalUpdate
		34 => 39 // Count
	];
	public const NETWORK_ID = ProtocolInfo::PLAYER_ACTION_PACKET;

	public int $actorRuntimeId;
	/** @see PlayerAction */
	public int $action;
	public BlockPosition $blockPosition;
	public BlockPosition $resultPosition;
	public int $face;

	/**
	 * @generate-create-func
	 */
	public static function create(int $actorRuntimeId, int $action, BlockPosition $blockPosition, BlockPosition $resultPosition, int $face) : self{
		$result = new self;
		$result->actorRuntimeId = $actorRuntimeId;
		$result->action = $action;
		$result->blockPosition = $blockPosition;
		$result->resultPosition = $resultPosition;
		$result->face = $face;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->actorRuntimeId = CommonTypes::getActorRuntimeId($in);
		$action = VarInt::readSignedInt($in);
		$this->action = $protocolId < 2207 ? (self::LEGACY_ACTIONS[$action] ?? $action) : $action;
		$this->blockPosition = CommonTypes::getBlockPosition($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		$this->resultPosition = CommonTypes::getBlockPosition($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		$this->face = VarInt::readSignedInt($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putActorRuntimeId($out, $this->actorRuntimeId);
		$action = $this->action;
		if($protocolId < 2207){
			$legacyAction = array_search($action, self::LEGACY_ACTIONS, true);
			if($legacyAction !== false){
				$action = $legacyAction;
			}
		}
		VarInt::writeSignedInt($out, $action);
		CommonTypes::putBlockPosition($out, $this->blockPosition, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		CommonTypes::putBlockPosition($out, $this->resultPosition, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		VarInt::writeSignedInt($out, $this->face);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handlePlayerAction($this);
	}
}
