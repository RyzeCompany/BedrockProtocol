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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

final class ItemStackRequestActionType{

	private function __construct(){
		//NOOP
	}

	public const TAKE = 0;
	public const PLACE = 1;
	public const SWAP = 2;
	public const DROP = 3;
	public const DESTROY = 4;
	public const CRAFTING_CONSUME_INPUT = 5;
	public const CRAFTING_CREATE_SPECIFIC_RESULT = 6;
	public const PLACE_INTO_BUNDLE = 7;
	public const TAKE_FROM_BUNDLE = 8;
	public const LAB_TABLE_COMBINE = 9;
	public const BEACON_PAYMENT = 10;
	public const MINE_BLOCK = 11;
	public const CRAFTING_RECIPE = 12;
	public const CRAFTING_RECIPE_AUTO = 13; //recipe book?
	public const CREATIVE_CREATE = 14;
	public const CRAFTING_RECIPE_OPTIONAL = 15; //anvil/cartography table rename
	public const CRAFTING_GRINDSTONE = 16;
	public const CRAFTING_LOOM = 17;
	public const CRAFTING_NON_IMPLEMENTED_DEPRECATED_ASK_TY_LAING = 18;
	public const CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING = 19; //no idea what this is for
	// Internal type used by the new 1.26.60 CraftReservedAction (wire type 18).
	public const CRAFTING_RESERVED = 20;

	public const INNER_TYPES_1_26_40 = [
		0 => self::TAKE,
		1 => self::PLACE,
		2 => self::SWAP,
		3 => self::DROP,
		4 => self::DESTROY,
		5 => self::CRAFTING_CONSUME_INPUT,
		6 => self::CRAFTING_CREATE_SPECIFIC_RESULT,
		7 => self::LAB_TABLE_COMBINE,
		8 => self::BEACON_PAYMENT,
		9 => self::MINE_BLOCK,
		10 => self::CRAFTING_RECIPE,
		11 => self::CRAFTING_RECIPE_AUTO,
		12 => self::CREATIVE_CREATE,
		13 => self::CRAFTING_RECIPE_OPTIONAL,
		14 => self::CRAFTING_GRINDSTONE,
		15 => self::CRAFTING_LOOM,
		16 => self::CRAFTING_NON_IMPLEMENTED_DEPRECATED_ASK_TY_LAING,
		17 => self::CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING
	];

	/**
	 * 1.26.60 adds a reserved crafting action and renumbers the wire action IDs.
	 * The values in this array are INTERNAL IDs used by the existing PHP action classes;
	 * their wire IDs are versioned independently below.
	 * Index = oneOf variant (outer type), value = internal action type.
	 */
	public const INTERNAL_TYPES_1_26_60 = [
		0 => self::TAKE,
		1 => self::PLACE,
		2 => self::SWAP,
		3 => self::DROP,
		4 => self::DESTROY,
		5 => self::CRAFTING_CONSUME_INPUT,
		6 => self::CRAFTING_CREATE_SPECIFIC_RESULT,
		7 => self::LAB_TABLE_COMBINE,
		8 => self::BEACON_PAYMENT,
		9 => self::MINE_BLOCK,
		10 => self::CRAFTING_RECIPE,
		11 => self::CRAFTING_RECIPE_AUTO,
		12 => self::CREATIVE_CREATE,
		13 => self::CRAFTING_RECIPE_OPTIONAL,
		14 => self::CRAFTING_GRINDSTONE,
		15 => self::CRAFTING_LOOM,
		16 => self::CRAFTING_RESERVED,
		17 => self::CRAFTING_NON_IMPLEMENTED_DEPRECATED_ASK_TY_LAING,
		18 => self::CRAFTING_RESULTS_DEPRECATED_ASK_TY_LAING
	];

	/**
	 * Official 2225 wire IDs, indexed by the oneOf variant index.
	 * 7 and 8 are absent because bundle actions are not present in the wire enum.
	 * @see https://mojang.github.io/bedrock-protocol-docs/1.26.60/types/item-stack-request-action-type/
	 */
	public const WIRE_TYPES_1_26_60 = [
		0 => 0,
		1 => 1,
		2 => 2,
		3 => 3,
		4 => 4,
		5 => 5,
		6 => 6,
		7 => 9,
		8 => 10,
		9 => 11,
		10 => 12,
		11 => 13,
		12 => 14,
		13 => 15,
		14 => 16,
		15 => 17,
		16 => 18,
		17 => 19,
		18 => 20
	];
}
