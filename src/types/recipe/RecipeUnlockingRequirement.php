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

namespace pocketmine\network\mcpe\protocol\types\recipe;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function count;

final class RecipeUnlockingRequirement{

	/**
	 * @param RecipeIngredient[]|null $unlockingIngredients
	 * @phpstan-param list<RecipeIngredient>|null $unlockingIngredients
	 */
	public function __construct(
		private ?array $unlockingIngredients
	){}

	/**
	 * @return RecipeIngredient[]|null
	 * @phpstan-return list<RecipeIngredient>|null
	 */
	public function getUnlockingIngredients() : ?array{ return $this->unlockingIngredients; }

	public static function read(ByteBufferReader $in, int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : self{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			$context = VarInt::readSignedInt($in);
			if($context < 0 || $context > 3){
				throw new \InvalidArgumentException("Unknown recipe unlocking context $context");
			}
			$ingredients = CommonTypes::readOptional($in, static function(ByteBufferReader $in) use ($protocolId) : array{
				$result = [];
				for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; ++$i){
					$result[] = CommonTypes::getRecipeIngredient($in, $protocolId);
				}
				return $result;
			});
			if($context !== 0 && $ingredients !== null){
				throw new \InvalidArgumentException("Unlocking ingredients are only allowed for context NONE");
			}
			return new self($ingredients);
		}

		//I don't know what the point of this structure is. It could easily have been a list<RecipeIngredient> instead.
		//It's basically just an optional list, which could have been done by an empty list wherever it's not needed.
		$unlockingContext = CommonTypes::getBool($in);
		$unlockingIngredients = null;
		if(!$unlockingContext){
			$unlockingIngredients = [];
			for($i = 0, $count = VarInt::readUnsignedInt($in); $i < $count; $i++){
				$unlockingIngredients[] = CommonTypes::getRecipeIngredient($in);
			}
		}

		return new self($unlockingIngredients);
	}

	public function write(ByteBufferWriter $out, int $protocolId = ProtocolInfo::CURRENT_PROTOCOL) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			VarInt::writeSignedInt($out, $this->unlockingIngredients === null ? 1 : 0);
			CommonTypes::writeOptional($out, $this->unlockingIngredients, static function(ByteBufferWriter $out, array $ingredients) use ($protocolId) : void{
				VarInt::writeUnsignedInt($out, count($ingredients));
				foreach($ingredients as $ingredient){
					CommonTypes::putRecipeIngredient($out, $ingredient, $protocolId);
				}
			});
			return;
		}

		CommonTypes::putBool($out, $this->unlockingIngredients === null);
		if($this->unlockingIngredients !== null){
			VarInt::writeUnsignedInt($out, count($this->unlockingIngredients));
			foreach($this->unlockingIngredients as $ingredient){
				CommonTypes::putRecipeIngredient($out, $ingredient);
			}
		}
	}
}
