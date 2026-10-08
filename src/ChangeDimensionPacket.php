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
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

class ChangeDimensionPacket extends DataPacket implements ClientboundPacket{
	/** Mojang changed this field from a fixed uint32 to an optional uint32 in protocol 2207. */
	private const OPTIONAL_LOADING_SCREEN_PROTOCOL = 2207;
	public const NETWORK_ID = ProtocolInfo::CHANGE_DIMENSION_PACKET;

	public int $dimension;
	public Vector3 $position;
	public bool $respawn = false;
	private ?int $loadingScreenId = null;

	/**
	 * @generate-create-func
	 */
	public static function create(int $dimension, Vector3 $position, bool $respawn, ?int $loadingScreenId) : self{
		$result = new self;
		$result->dimension = $dimension;
		$result->position = $position;
		$result->respawn = $respawn;
		$result->loadingScreenId = $loadingScreenId;
		return $result;
	}

	public function getLoadingScreenId() : ?int{ return $this->loadingScreenId; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->dimension = VarInt::readSignedInt($in);
		$this->position = CommonTypes::getVector3($in);
		$this->respawn = CommonTypes::getBool($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_20){
			$this->loadingScreenId = $protocolId >= self::OPTIONAL_LOADING_SCREEN_PROTOCOL
				? CommonTypes::readOptional($in, LE::readUnsignedInt(...))
				: LE::readUnsignedInt($in);
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		VarInt::writeSignedInt($out, $this->dimension);
		CommonTypes::putVector3($out, $this->position);
		CommonTypes::putBool($out, $this->respawn);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_21_20){
			if($protocolId >= self::OPTIONAL_LOADING_SCREEN_PROTOCOL){
				CommonTypes::writeOptional($out, $this->loadingScreenId, LE::writeUnsignedInt(...));
			}else{
				LE::writeUnsignedInt($out, $this->loadingScreenId ?? 0);
			}
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleChangeDimension($this);
	}
}
