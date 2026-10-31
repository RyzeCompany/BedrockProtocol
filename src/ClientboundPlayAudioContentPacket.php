<?php
declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

/**
 * Signed audio playback content for Bedrock 2225 (ID 359).
 *
 * Its final field is PlaySoundPacketPayload. Reuse the existing serializer
 * rather than independently duplicating all the conditional sound fields.
 */
class ClientboundPlayAudioContentPacket extends PlaySoundPacket{
	public const NETWORK_ID = ProtocolInfo::CLIENTBOUND_PLAY_AUDIO_CONTENT_PACKET;

	private string $sharedMetadataJwt;
	private string $playbackContentJwt;
	private string $playbackType;

	public static function createAudioContent(string $sharedMetadataJwt, string $playbackContentJwt, string $playbackType, PlaySoundPacket $sound) : self{
		if($playbackType !== "Music" && $playbackType !== "Sound"){
			throw new \InvalidArgumentException("Unsupported audio playback type: $playbackType");
		}
		$result = new self;
		$result->sharedMetadataJwt = $sharedMetadataJwt;
		$result->playbackContentJwt = $playbackContentJwt;
		$result->playbackType = $playbackType;
		$result->soundName = $sound->soundName;
		$result->x = $sound->x;
		$result->y = $sound->y;
		$result->z = $sound->z;
		$result->volume = $sound->volume;
		$result->pitch = $sound->pitch;
		$result->loopCount = $sound->loopCount;
		$result->serverSoundHandle = $sound->serverSoundHandle;
		$result->bypassListenerRangeCheck = $sound->bypassListenerRangeCheck;
		$result->playbackPositionSeconds = $sound->playbackPositionSeconds;
		return $result;
	}

	public function getSharedMetadataJwt() : string{ return $this->sharedMetadataJwt; }
	public function getPlaybackContentJwt() : string{ return $this->playbackContentJwt; }
	public function getPlaybackType() : string{ return $this->playbackType; }

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->sharedMetadataJwt = CommonTypes::getString($in);
		$this->playbackContentJwt = CommonTypes::getString($in);
		$this->playbackType = CommonTypes::getString($in);
		if($this->playbackType !== "Music" && $this->playbackType !== "Sound"){
			throw new PacketDecodeException("Unknown audio playback type " . $this->playbackType);
		}
		parent::decodePayload($in, $protocolId);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putString($out, $this->sharedMetadataJwt);
		CommonTypes::putString($out, $this->playbackContentJwt);
		CommonTypes::putString($out, $this->playbackType);
		parent::encodePayload($out, $protocolId);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleClientboundPlayAudioContent($this);
	}
}
