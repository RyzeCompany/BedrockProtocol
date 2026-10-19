<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\serializer;

use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties as Properties;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags as Flags;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\MetadataProperty;

/** Explicit wire-ID projection for the 419 metadata schema. Unknown newer fields are omitted. */
final class Legacy419EntityMetadata{
 private const PROPERTIES = [
		Properties::HEALTH => 1,
		Properties::VARIANT => 2,
		Properties::NAMETAG => 4,
		Properties::OWNER_EID => 5,
		Properties::TARGET_EID => 6,
		Properties::AIR => 7,
		Properties::POTION_COLOR => 8,
		Properties::POTION_AMBIENT => 9,
		Properties::HURT_TIME => 11,
		Properties::HURT_DIRECTION => 12,
		Properties::PADDLE_TIME_LEFT => 13,
		Properties::PADDLE_TIME_RIGHT => 14,
		Properties::EXPERIENCE_VALUE => 15,
		Properties::MINECART_DISPLAY_BLOCK => 16,
		Properties::HORSE_FLAGS => 16,
		Properties::MINECART_DISPLAY_OFFSET => 17,
		Properties::SHOOTER_ID => 17,
		Properties::MINECART_HAS_DISPLAY => 18,
		Properties::HORSE_TYPE => 19,
		Properties::CHARGE_AMOUNT => 22,
		Properties::ENDERMAN_HELD_ITEM_ID => 23,
		Properties::ENTITY_AGE => 24,
		Properties::PLAYER_FLAGS => 26,
		Properties::PLAYER_INDEX => 27,
		Properties::PLAYER_BED_POSITION => 28,
		Properties::FIREBALL_POWER_X => 29,
		Properties::FIREBALL_POWER_Y => 30,
		Properties::FIREBALL_POWER_Z => 31,
		Properties::POTION_AUX_VALUE => 36,
		Properties::LEAD_HOLDER_EID => 37,
		Properties::SCALE => 38,
		Properties::HAS_NPC_COMPONENT => 39,
		Properties::NPC_SKIN_INDEX => 40,
		Properties::NPC_ACTIONS => 41,
		Properties::MAX_AIR => 42,
		Properties::MARK_VARIANT => 43,
		Properties::CONTAINER_TYPE => 44,
		Properties::CONTAINER_BASE_SIZE => 45,
		Properties::CONTAINER_EXTRA_SLOTS_PER_STRENGTH => 46,
		Properties::BLOCK_TARGET => 47,
		Properties::WITHER_INVULNERABLE_TICKS => 48,
		Properties::WITHER_TARGET_1 => 49,
		Properties::WITHER_TARGET_2 => 50,
		Properties::WITHER_TARGET_3 => 51,
		Properties::BOUNDING_BOX_WIDTH => 53,
		Properties::BOUNDING_BOX_HEIGHT => 54,
		Properties::FUSE_LENGTH => 55,
		Properties::RIDER_SEAT_POSITION => 56,
		Properties::RIDER_ROTATION_LOCKED => 57,
		Properties::RIDER_MAX_ROTATION => 58,
		Properties::RIDER_MIN_ROTATION => 59,
		Properties::AREA_EFFECT_CLOUD_RADIUS => 60,
		Properties::AREA_EFFECT_CLOUD_WAITING => 61,
		Properties::AREA_EFFECT_CLOUD_PARTICLE_ID => 62,
		Properties::SHULKER_ATTACH_FACE => 64,
		Properties::SHULKER_ATTACH_POS => 66,
		Properties::TRADING_PLAYER_EID => 67,
		Properties::COMMAND_BLOCK_COMMAND => 70,
		Properties::COMMAND_BLOCK_LAST_OUTPUT => 71,
		Properties::COMMAND_BLOCK_TRACK_OUTPUT => 72,
		Properties::CONTROLLING_RIDER_SEAT_NUMBER => 73,
		Properties::STRENGTH => 74,
		Properties::MAX_STRENGTH => 75,
		Properties::LIMITED_LIFE => 77,
		Properties::ARMOR_STAND_POSE_INDEX => 78,
		Properties::ENDER_CRYSTAL_TIME_OFFSET => 79,
		Properties::ALWAYS_SHOW_NAMETAG => 80,
		Properties::COLOR_2 => 81,
		Properties::SCORE_TAG => 83,
		Properties::BALLOON_ATTACHED_ENTITY => 84,
		Properties::PUFFERFISH_SIZE => 85,
		Properties::BOAT_BUBBLE_TIME => 86,
		Properties::PLAYER_AGENT_EID => 87,
		Properties::EAT_COUNTER => 90,
		Properties::AREA_EFFECT_CLOUD_DURATION => 94,
		Properties::AREA_EFFECT_CLOUD_SPAWN_TIME => 95,
		Properties::AREA_EFFECT_CLOUD_RADIUS_PER_TICK => 96,
		Properties::AREA_EFFECT_CLOUD_RADIUS_CHANGE_ON_PICKUP => 97,
		Properties::AREA_EFFECT_CLOUD_PICKUP_COUNT => 98,
		Properties::INTERACTIVE_TAG => 99,
		Properties::TRADE_TIER => 100,
		Properties::MAX_TRADE_TIER => 101,
		Properties::TRADE_XP => 102,
		Properties::SKIN_ID => 103,
		Properties::COMMAND_BLOCK_TICK_DELAY => 105,
		Properties::COMMAND_BLOCK_EXECUTE_ON_FIRST_TICK => 106,
		Properties::AMBIENT_SOUND_INTERVAL_MIN => 107,
		Properties::AMBIENT_SOUND_INTERVAL_RANGE => 108,
		Properties::AMBIENT_SOUND_EVENT => 109,
 ];
 private const FLAGS = [
		Flags::ONFIRE => 0,
		Flags::SNEAKING => 1,
		Flags::RIDING => 2,
		Flags::SPRINTING => 3,
		Flags::ACTION => 4,
		Flags::INVISIBLE => 5,
		Flags::TEMPTED => 6,
		Flags::INLOVE => 7,
		Flags::SADDLED => 8,
		Flags::POWERED => 9,
		Flags::IGNITED => 10,
		Flags::BABY => 11,
		Flags::CONVERTING => 12,
		Flags::CRITICAL => 13,
		Flags::CAN_SHOW_NAMETAG => 14,
		Flags::ALWAYS_SHOW_NAMETAG => 15,
		Flags::SILENT => 17,
		Flags::WALLCLIMBING => 18,
		Flags::CAN_CLIMB => 19,
		Flags::SWIMMER => 20,
		Flags::CAN_FLY => 21,
		Flags::WALKER => 22,
		Flags::RESTING => 23,
		Flags::SITTING => 24,
		Flags::ANGRY => 25,
		Flags::INTERESTED => 26,
		Flags::CHARGED => 27,
		Flags::TAMED => 28,
		Flags::ORPHANED => 29,
		Flags::LEASHED => 30,
		Flags::SHEARED => 31,
		Flags::GLIDING => 32,
		Flags::ELDER => 33,
		Flags::MOVING => 34,
		Flags::BREATHING => 35,
		Flags::CHESTED => 36,
		Flags::STACKABLE => 37,
		Flags::SHOWBASE => 38,
		Flags::REARING => 39,
		Flags::VIBRATING => 40,
		Flags::IDLING => 41,
		Flags::EVOKER_SPELL => 42,
		Flags::CHARGE_ATTACK => 43,
		Flags::WASD_CONTROLLED => 44,
		Flags::CAN_POWER_JUMP => 45,
		Flags::LINGER => 46,
		Flags::HAS_COLLISION => 47,
		Flags::AFFECTED_BY_GRAVITY => 48,
		Flags::FIRE_IMMUNE => 49,
		Flags::DANCING => 50,
		Flags::ENCHANTED => 51,
		Flags::SHOW_TRIDENT_ROPE => 52,
		Flags::CONTAINER_PRIVATE => 53,
		Flags::TRANSFORMING => 54,
		Flags::SPIN_ATTACK => 55,
		Flags::SWIMMING => 56,
		Flags::BRIBED => 57,
		Flags::PREGNANT => 58,
		Flags::LAYING_EGG => 59,
		Flags::RIDER_CAN_PICK => 60,
		Flags::TRANSITION_SITTING => 61,
		Flags::EATING => 62,
		Flags::LAYING_DOWN => 63,
		Flags::SNEEZING => 64,
		Flags::TRUSTING => 65,
		Flags::ROLLING => 66,
		Flags::SCARED => 67,
		Flags::IN_SCAFFOLDING => 68,
		Flags::OVER_SCAFFOLDING => 69,
		Flags::FALL_THROUGH_SCAFFOLDING => 70,
		Flags::BLOCKING => 71,
		Flags::TRANSITION_BLOCKING => 72,
		Flags::BLOCKED_USING_SHIELD => 73,
		Flags::BLOCKED_USING_DAMAGED_SHIELD => 74,
		Flags::SLEEPING => 75,
		Flags::WANTS_TO_WAKE => 76,
		Flags::TRADE_INTEREST => 77,
		Flags::DOOR_BREAKER => 78,
		Flags::BREAKING_OBSTRUCTION => 79,
		Flags::DOOR_OPENER => 80,
		Flags::ILLAGER_CAPTAIN => 81,
		Flags::STUNNED => 82,
		Flags::ROARING => 83,
		Flags::DELAYED_ATTACKING => 84,
		Flags::AVOIDING_MOBS => 85,
		Flags::AVOIDING_BLOCK => 86,
		Flags::FACING_TARGET_TO_RANGE_ATTACK => 87,
		Flags::HIDDEN_WHEN_INVISIBLE => 88,
		Flags::IS_IN_UI => 89,
		Flags::STALKING => 90,
		Flags::EMOTING => 91,
		Flags::CELEBRATING => 92,
		Flags::ADMIRING => 93,
		Flags::CELEBRATING_SPECIAL => 94,
 ];
 /** @param array<int, MetadataProperty> $metadata @return array<int, MetadataProperty> */
 public static function fromCurrent(array $metadata) : array{
  $result = [];
  foreach(self::PROPERTIES as $current => $legacy){
   if(isset($metadata[$current])){ $result[$legacy] = $metadata[$current]; }
  }
  if(isset($metadata[Properties::FLAGS]) || isset($metadata[Properties::FLAGS2])){
   $source = [$metadata[Properties::FLAGS] ?? new LongMetadataProperty(0), $metadata[Properties::FLAGS2] ?? new LongMetadataProperty(0)];
   $bits = [0, 0];
   foreach(self::FLAGS as $current => $legacy){
    if((($source[intdiv($current, 64)] ?? new LongMetadataProperty(0))->getValue() & (1 << ($current % 64))) !== 0){
     $bits[intdiv($legacy, 64)] |= 1 << ($legacy % 64);
    }
   }
   $result[0] = new LongMetadataProperty($bits[0]);
   $result[91] = new LongMetadataProperty($bits[1]);
  }
  return $result;
 }
 /** @param array<int, MetadataProperty> $metadata @return array<int, MetadataProperty> */
 public static function toCurrent(array $metadata) : array{
  $result = [];
  foreach(self::PROPERTIES as $current => $legacy){
   if(isset($metadata[$legacy])){ $result[$current] = $metadata[$legacy]; }
  }
  if(isset($metadata[0]) || isset($metadata[91])){
   $source = [$metadata[0] ?? new LongMetadataProperty(0), $metadata[91] ?? new LongMetadataProperty(0)];
   $bits = [0, 0];
   foreach(self::FLAGS as $current => $legacy){
    if(($source[intdiv($legacy, 64)]->getValue() & (1 << ($legacy % 64))) !== 0){
     $bits[intdiv($current, 64)] |= 1 << ($current % 64);
    }
   }
   $result[Properties::FLAGS] = new LongMetadataProperty($bits[0]);
   $result[Properties::FLAGS2] = new LongMetadataProperty($bits[1]);
  }
  return $result;
 }

}
