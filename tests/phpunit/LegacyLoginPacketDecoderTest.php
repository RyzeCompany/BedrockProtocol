<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferWriter;
use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\serializer\LegacyLoginPacketDecoder;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;

final class LegacyLoginPacketDecoderTest extends TestCase{
	// Independently specified wire bytes: packet 1, big-endian protocol 419,
	// 11-byte request: little-endian JSON length, {}, JWT length, x.
	private const LOGIN = "\x01\x00\x00\x01\xa3\x0b\x02\x00\x00\x00{}\x01\x00\x00\x00x";

	private static function batch(array $packets) : string{
		$out = new ByteBufferWriter();
		PacketBatch::encodeRaw($out, $packets);
		return $out->getData();
	}

	public function testDecodesHistoricalLoginLayout() : void{
		$login = LegacyLoginPacketDecoder::decodeBatch(self::batch([self::LOGIN]));
		self::assertSame(419, $login->protocol);
		self::assertSame('{}', $login->authInfoJson);
		self::assertSame('x', $login->clientDataJwt);
		$out = new ByteBufferWriter();
		$login->encode($out, 419);
		self::assertSame(self::LOGIN, $out->getData());
	}

	public function testRejectsSecondLoginInInitialBatch() : void{
		$this->expectException(PacketDecodeException::class);
		LegacyLoginPacketDecoder::decodeBatch(self::batch([self::LOGIN, self::LOGIN]));
	}

	public function testRejectsTrailingBytesInsideLogin() : void{
		$this->expectException(PacketDecodeException::class);
		LegacyLoginPacketDecoder::decodeBatch(self::batch([self::LOGIN . "\x00"]));
	}

	public function testRejectsTruncatedLogin() : void{
		$this->expectException(PacketDecodeException::class);
		LegacyLoginPacketDecoder::decodeBatch(self::batch([substr(self::LOGIN, 0, -1)]));
	}

	public function testRejectsBytesHiddenInsideConnectionRequest() : void{
		$this->expectException(PacketDecodeException::class);
		$wire = self::LOGIN . "\x00";
		$wire[5] = "\x0c";
		LegacyLoginPacketDecoder::decodeBatch(self::batch([$wire]));
	}

	public function testRejectsOtherProtocols() : void{
		$this->expectException(PacketDecodeException::class);
		$wire = self::LOGIN;
		$wire[4] = "\xa4";
		LegacyLoginPacketDecoder::decodeBatch(self::batch([$wire]));
	}

	public function testRejectsEmptyBatch() : void{
		$this->expectException(PacketDecodeException::class);
		LegacyLoginPacketDecoder::decodeBatch('');
	}
}
