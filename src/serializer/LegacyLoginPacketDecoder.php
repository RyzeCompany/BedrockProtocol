<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\serializer;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

/** Strict preflight for an already decompressed 1.16.100 initial batch. */
final class LegacyLoginPacketDecoder{
	private function __construct(){}

	public static function decodeBatch(string $batch) : LoginPacket{
		$login = null;
		foreach(PacketBatch::decodeRaw(new ByteBufferReader($batch)) as $buffer){
			if($login !== null){
				throw new PacketDecodeException("Legacy initial batch must contain exactly one login");
			}
			try{
				$reader = new ByteBufferReader($buffer);
				$packet = new LoginPacket();
				$packet->decode($reader, ProtocolInfo::PROTOCOL_1_16_100);
				if($packet->protocol !== ProtocolInfo::PROTOCOL_1_16_100 || $packet->senderSubId !== 0 || $packet->recipientSubId !== 0 || $reader->getUnreadLength() !== 0){
					throw new PacketDecodeException("Invalid legacy login protocol, subclient or trailing bytes");
				}
				$login = $packet;
			}catch(DataDecodeException $e){
				throw PacketDecodeException::wrap($e, "Invalid legacy login");
			}
		}
		return $login ?? throw new PacketDecodeException("Empty legacy login batch");
	}
}
