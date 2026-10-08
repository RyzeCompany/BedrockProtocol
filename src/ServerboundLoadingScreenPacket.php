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
use pocketmine\network\mcpe\protocol\types\hud\LoadingScreenType;

class ServerboundLoadingScreenPacket extends DataPacket implements ServerboundPacket{
	/** Protocol 2207 changed both the packet type values and the loading screen ID encoding. */
	private const OPTIONAL_LOADING_SCREEN_PROTOCOL = 2207;
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_LOADING_SCREEN_PACKET;

	private LoadingScreenType $loadingScreenType;
	private ?int $loadingScreenId = null;

	/**
	 * @generate-create-func
	 */
	public static function create(LoadingScreenType $loadingScreenType, ?int $loadingScreenId) : self{
		$result = new self;
		$result->loadingScreenType = $loadingScreenType;
		$result->loadingScreenId = $loadingScreenId;
		return $result;
	}

	public function getLoadingScreenType() : LoadingScreenType{ return $this->loadingScreenType; }

	public function getLoadingScreenId() : ?int{ return $this->loadingScreenId; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$type = VarInt::readSignedInt($in);
		if($protocolId >= self::OPTIONAL_LOADING_SCREEN_PROTOCOL){
			$this->loadingScreenType = LoadingScreenType::fromPacket($type);
			$this->loadingScreenId = CommonTypes::readOptional($in, LE::readUnsignedInt(...));
		}else{
			// Before 2207, 0=start and 1=stop (in newer protocols these are 1 and 2).
			$this->loadingScreenType = match($type){
				0 => LoadingScreenType::START_LOADING_SCREEN,
				1 => LoadingScreenType::STOP_LOADING_SCREEN,
				default => LoadingScreenType::UNKNOWN
			};
			$id = LE::readUnsignedInt($in);
			$this->loadingScreenId = $id === 0 ? null : $id;
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		if($protocolId >= self::OPTIONAL_LOADING_SCREEN_PROTOCOL){
			VarInt::writeSignedInt($out, $this->loadingScreenType->value);
			CommonTypes::writeOptional($out, $this->loadingScreenId, LE::writeUnsignedInt(...));
		}else{
			$type = match($this->loadingScreenType){
				LoadingScreenType::START_LOADING_SCREEN => 0,
				LoadingScreenType::STOP_LOADING_SCREEN => 1,
				default => -1
			};
			VarInt::writeSignedInt($out, $type);
			LE::writeUnsignedInt($out, $this->loadingScreenId ?? 0);
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleServerboundLoadingScreen($this);
	}
}
