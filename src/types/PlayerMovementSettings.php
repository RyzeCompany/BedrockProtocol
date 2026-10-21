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

namespace pocketmine\network\mcpe\protocol\types;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class PlayerMovementSettings{
	public function __construct(
		private ServerAuthMovementMode $movementType,
		private int $rewindHistorySize,
		private bool $serverAuthoritativeBlockBreaking
	){}

	public function getMovementType() : ServerAuthMovementMode{ return $this->movementType; }

	public function getRewindHistorySize() : int{ return $this->rewindHistorySize; }

	public function isServerAuthoritativeBlockBreaking() : bool{ return $this->serverAuthoritativeBlockBreaking; }

	public static function read(ByteBufferReader $in, int $protocolId) : self{
		if($protocolId === ProtocolInfo::PROTOCOL_1_16_100){
			$mode = VarInt::readSignedInt($in);
			if($mode !== 0 && $mode !== 1){
				throw new \pocketmine\network\mcpe\protocol\PacketDecodeException("Invalid 419 movement mode $mode");
			}
			return new self(ServerAuthMovementMode::fromPacket($mode), 0, false);
		}
		if($protocolId <= ProtocolInfo::PROTOCOL_1_21_80){
			$movementType = ServerAuthMovementMode::fromPacket(VarInt::readSignedInt($in));
		}
		$rewindHistorySize = VarInt::readSignedInt($in);
		$serverAuthBlockBreaking = CommonTypes::getBool($in);
		return new self($movementType ?? ServerAuthMovementMode::SERVER_AUTHORITATIVE_V3, $rewindHistorySize, $serverAuthBlockBreaking);
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId === ProtocolInfo::PROTOCOL_1_16_100){
			// 419 predates the rewind settings and V3. Do not mutate the shared model.
			VarInt::writeSignedInt($out, $this->movementType === ServerAuthMovementMode::LEGACY ? 0 : 1);
			return;
		}
		if($protocolId <= ProtocolInfo::PROTOCOL_1_21_80){
			VarInt::writeSignedInt($out, $this->movementType->value);
		}elseif($this->movementType !== ServerAuthMovementMode::SERVER_AUTHORITATIVE_V3){
			throw new \InvalidArgumentException("Unsupported movement type for protocol version {$protocolId}: {$this->movementType->name}");
		}
		VarInt::writeSignedInt($out, $this->rewindHistorySize);
		CommonTypes::putBool($out, $this->serverAuthoritativeBlockBreaking);
	}
}
