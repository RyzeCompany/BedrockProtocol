<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

/** Transport characteristics, independent of whether a protocol is playable. */
final class ProtocolTransportInfo{
	private function __construct(){}

	public static function requiresNetworkSettings(int $protocolId) : bool{
		self::checkKnownProtocol($protocolId);
		return $protocolId !== ProtocolInfo::PROTOCOL_1_16_100;
	}

	public static function getRakNetVersion(int $protocolId) : int{
		self::checkKnownProtocol($protocolId);
		return $protocolId === ProtocolInfo::PROTOCOL_1_16_100 ? 10 : 11;
	}

	/** Preserve RyzeMP's existing framing for all currently accepted protocols. */
	public static function usesCompressionHeader(int $protocolId) : bool{
		self::checkKnownProtocol($protocolId);
		return $protocolId >= ProtocolInfo::PROTOCOL_1_20_60;
	}

	public static function usesCfb8Encryption(int $protocolId) : bool{
		self::checkKnownProtocol($protocolId);
		return $protocolId === ProtocolInfo::PROTOCOL_1_16_100;
	}

	private static function checkKnownProtocol(int $protocolId) : void{
		if($protocolId !== ProtocolInfo::PROTOCOL_1_16_100 && !in_array($protocolId, ProtocolInfo::ACCEPTED_PROTOCOL, true)){
			throw new \InvalidArgumentException("Unknown transport protocol $protocolId");
		}
	}
}
