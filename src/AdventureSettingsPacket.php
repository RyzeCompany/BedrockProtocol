<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;



use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pmmp\encoding\LE;
use pocketmine\network\mcpe\protocol\types\AbilitiesData;
use pocketmine\network\mcpe\protocol\types\AbilitiesLayer;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;

class AdventureSettingsPacket extends DataPacket implements ClientboundPacket, ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::ADVENTURE_SETTINGS_PACKET;

	public const PERMISSION_NORMAL = 0;
	public const PERMISSION_OPERATOR = 1;
	public const PERMISSION_HOST = 2;
	public const PERMISSION_AUTOMATION = 3;
	public const PERMISSION_ADMIN = 4;

	/**
	 * This constant is used to identify flags that should be set on the second field. In a sensible world, these
	 * flags would all be set on the same packet field, but as of MCPE 1.2, the new abilities flags have for some
	 * reason been assigned a separate field.
	 */
	public const BITFLAG_SECOND_SET = 1 << 16;

	public const WORLD_IMMUTABLE = 0x01;
	public const NO_PVP = 0x02;

	public const AUTO_JUMP = 0x20;
	public const ALLOW_FLIGHT = 0x40;
	public const NO_CLIP = 0x80;
	public const WORLD_BUILDER = 0x100;
	public const FLYING = 0x200;
	public const MUTED = 0x400;

	public const BUILD_AND_MINE = 0x01 | self::BITFLAG_SECOND_SET;
	public const DOORS_AND_SWITCHES = 0x02 | self::BITFLAG_SECOND_SET;
	public const OPEN_CONTAINERS = 0x04 | self::BITFLAG_SECOND_SET;
	public const ATTACK_PLAYERS = 0x08 | self::BITFLAG_SECOND_SET;
	public const ATTACK_MOBS = 0x10 | self::BITFLAG_SECOND_SET;
	public const OPERATOR = 0x20 | self::BITFLAG_SECOND_SET;
	public const TELEPORT = 0x80 | self::BITFLAG_SECOND_SET;

	/** @var int */
	public $flags = 0;
	/** @var int */
	public $commandPermission = self::PERMISSION_NORMAL;
	/** @var int */
	public $flags2 = 0xff;
	/** @var int */
	public $playerPermission = PlayerPermissions::MEMBER;
	/** @var int */
	public $customFlags = 0; //...
	/** @var int */
	public $entityUniqueId; //This is a little-endian long, NOT a var-long. (WTF Mojang)

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->flags = VarInt::readUnsignedInt($in);
		$this->commandPermission = VarInt::readUnsignedInt($in);
		$this->flags2 = VarInt::readUnsignedInt($in);
		$this->playerPermission = VarInt::readUnsignedInt($in);
		$this->customFlags = VarInt::readUnsignedInt($in);
		$this->entityUniqueId = LE::readSignedLong($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		VarInt::writeUnsignedInt($out, $this->flags);
		VarInt::writeUnsignedInt($out, $this->commandPermission);
		VarInt::writeUnsignedInt($out, $this->flags2);
		VarInt::writeUnsignedInt($out, $this->playerPermission);
		VarInt::writeUnsignedInt($out, $this->customFlags);
		LE::writeSignedLong($out, $this->entityUniqueId);
	}

	public function getFlag(int $flag) : bool{
		if(($flag & self::BITFLAG_SECOND_SET) !== 0){
			return ($this->flags2 & ($flag & ~self::BITFLAG_SECOND_SET)) !== 0;
		}

		return ($this->flags & $flag) !== 0;
	}

	public function setFlag(int $flag, bool $value) : void{
		if(($flag & self::BITFLAG_SECOND_SET) !== 0){
			$flagSet =& $this->flags2;
			$flag &= ~self::BITFLAG_SECOND_SET;
		}else{
			$flagSet =& $this->flags;
		}

		if($value){
			$flagSet |= $flag;
		}else{
			$flagSet &= ~$flag;
		}
	}


	public static function fromAbilitiesData(AbilitiesData $data, bool $autoJump = true) : self{
		$result = new self();
		$result->commandPermission = $data->getCommandPermission();
		$result->playerPermission = $data->getPlayerPermission();
		$result->entityUniqueId = $data->getTargetActorUniqueId();
		$result->setFlag(self::AUTO_JUMP, $autoJump);
		$abilities = [];
		foreach($data->getAbilityLayers() as $layer){ $abilities = array_replace($abilities, $layer->getBoolAbilities()); }
		foreach([
			self::ALLOW_FLIGHT => AbilitiesLayer::ABILITY_ALLOW_FLIGHT,
			self::FLYING => AbilitiesLayer::ABILITY_FLYING,
			self::NO_CLIP => AbilitiesLayer::ABILITY_NO_CLIP,
			self::MUTED => AbilitiesLayer::ABILITY_MUTED,
			self::WORLD_BUILDER => AbilitiesLayer::ABILITY_WORLD_BUILDER,
			self::BUILD_AND_MINE => AbilitiesLayer::ABILITY_BUILD,
			self::DOORS_AND_SWITCHES => AbilitiesLayer::ABILITY_DOORS_AND_SWITCHES,
			self::OPEN_CONTAINERS => AbilitiesLayer::ABILITY_OPEN_CONTAINERS,
			self::ATTACK_PLAYERS => AbilitiesLayer::ABILITY_ATTACK_PLAYERS,
			self::ATTACK_MOBS => AbilitiesLayer::ABILITY_ATTACK_MOBS,
			self::OPERATOR => AbilitiesLayer::ABILITY_OPERATOR,
			self::TELEPORT => AbilitiesLayer::ABILITY_TELEPORT
		] as $flag => $ability){ $result->setFlag($flag, $abilities[$ability] ?? false); }
		return $result;
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleAdventureSettings($this);
	}
}

