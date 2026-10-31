<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

/** Registers signed audio content in Bedrock 2225 (packet ID 360). */
class ServerboundRegisterAudioContentPacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::SERVERBOUND_REGISTER_AUDIO_CONTENT_PACKET;
	private const MAX_REGISTRATIONS = 4096;

	/** @var list<array{id: string, shared: string, server: string, playback: string}> */
	private array $registrations = [];

	/** @param list<array{id: string, shared: string, server: string, playback: string}> $registrations */
	public static function create(array $registrations) : self{
		if(count($registrations) > self::MAX_REGISTRATIONS){
			throw new \InvalidArgumentException("Excessive audio registrations");
		}
		foreach($registrations as $entry){
			foreach(['id', 'shared', 'server', 'playback'] as $key){
				if(!isset($entry[$key]) || !is_string($entry[$key])){
					throw new \InvalidArgumentException("Invalid audio content registration");
				}
			}
		}
		$result = new self;
		$result->registrations = array_values($registrations);
		return $result;
	}

	/** @return list<array{id: string, shared: string, server: string, playback: string}> */
	public function getRegistrations() : array{ return $this->registrations; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$count = VarInt::readUnsignedInt($in);
		if($count > self::MAX_REGISTRATIONS){
			throw new PacketDecodeException("Too many audio content registrations: $count");
		}
		$this->registrations = [];
		for($i = 0; $i < $count; ++$i){
			$this->registrations[] = [
				'id' => CommonTypes::getString($in),
				'shared' => CommonTypes::getString($in),
				'server' => CommonTypes::getString($in),
				'playback' => CommonTypes::getString($in)
			];
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		VarInt::writeUnsignedInt($out, count($this->registrations));
		foreach($this->registrations as $entry){
			CommonTypes::putString($out, $entry['id']);
			CommonTypes::putString($out, $entry['shared']);
			CommonTypes::putString($out, $entry['server']);
			CommonTypes::putString($out, $entry['playback']);
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleServerboundRegisterAudioContent($this);
	}
}
