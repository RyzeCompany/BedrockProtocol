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

namespace pocketmine\network\mcpe\protocol\types\inventory;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function count;

abstract class TransactionData{
	/** @var NetworkInventoryAction[] */
	protected array $actions = [];

	/**
	 * @return NetworkInventoryAction[]
	 */
	final public function getActions() : array{
		return $this->actions;
	}

	abstract public function getTypeId() : int;

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	final public function decodeTransaction(ByteBufferReader $in, int $protocolId, bool $legacyHasItemStackIds = false) : void{
		$actionCount = VarInt::readUnsignedInt($in);
		$this->actions = [];
		for($i = 0; $i < $actionCount; ++$i){
			$action = (new NetworkInventoryAction())->readTransaction($in, $protocolId);
			if($legacyHasItemStackIds && $protocolId === ProtocolInfo::PROTOCOL_1_16_100){
				$action->newItem = new ItemStackWrapper(VarInt::readSignedInt($in), $action->newItem->getItemStack());
			}
			$this->actions[] = $action;
		}
		$this->decodeData($in, $protocolId);
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	final public function decodeAuthInput(ByteBufferReader $in, int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : void{
		$actionCount = VarInt::readUnsignedInt($in);
		$this->actions = [];
		for($i = 0; $i < $actionCount; ++$i){
			$this->actions[] = (new NetworkInventoryAction())->readAuthInput($in, $protocolId);
		}
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	abstract protected function decodeData(ByteBufferReader $in, int $protocolId) : void;

	final public function encodeTransaction(ByteBufferWriter $out, int $protocolId, bool $legacyHasItemStackIds = false) : void{
		VarInt::writeUnsignedInt($out, count($this->actions));
		foreach($this->actions as $action){
			$action->writeTransaction($out, $protocolId);
			if($legacyHasItemStackIds && $protocolId === ProtocolInfo::PROTOCOL_1_16_100){
				VarInt::writeSignedInt($out, $action->newItem->getStackId());
			}
		}
		$this->encodeData($out, $protocolId);
	}

	final public function encodeAuthInput(ByteBufferWriter $out, int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : void{
		VarInt::writeUnsignedInt($out, count($this->actions));
		foreach($this->actions as $action){
			$action->writeAuthInput($out, $protocolId);
		}
	}

	abstract protected function encodeData(ByteBufferWriter $out, int $protocolId) : void;
}
