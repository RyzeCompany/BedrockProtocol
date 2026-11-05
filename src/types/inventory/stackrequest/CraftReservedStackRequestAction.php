<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\GetTypeIdFromConstTrait;

/**
 * CraftReservedAction was introduced by protocol 2225 (Bedrock 1.26.60).
 *
 * The server may reject an unsupported reserved crafting transaction at
 * the inventory layer; its payload must still be decoded without desync.
 */
final class CraftReservedStackRequestAction extends ItemStackRequestAction{
	use GetTypeIdFromConstTrait;

	public const ID = ItemStackRequestActionType::CRAFTING_RESERVED;

	public function __construct(
		private string $reservedId,
		private int $numCrafts
	){}

	public function getReservedId() : string{ return $this->reservedId; }

	public function getNumCrafts() : int{ return $this->numCrafts; }

	public static function read(ByteBufferReader $in, int $protocolId) : self{
		return new self(CommonTypes::getString($in), Byte::readUnsigned($in));
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putString($out, $this->reservedId);
		Byte::writeUnsigned($out, $this->numCrafts);
	}
}
