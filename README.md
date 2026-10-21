# BedrockProtocol
[![CI](https://github.com/RyzeMCBE/BedrockProtocol/actions/workflows/ci.yml/badge.svg)](https://github.com/RyzeMCBE/BedrockProtocol/actions/workflows/ci.yml)

An implementation of the Minecraft: Bedrock Edition protocol in PHP

This library implements all of the packets in the Minecraft: Bedrock Edition protocol, as well as a few extra things needed to support them.
However, at the time of writing, it does _not_ include the following:
- Anything related to JWT handling/verification
- Anything related to encryption
- Anything related to compression

## Decoding packets
Assuming you've decrypted and decompressed a Minecraft packet successfully, you're next going to want to decode it.
With this library, that's currently done using `PacketBatch`, like so:

```php
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\PacketPool;
use pmmp\encoding\ByteBufferReader;

foreach(PacketBatch::decodePackets(new ByteBufferReader($payload), PacketPool::getInstance()) as $packetObject){
    var_dump($packetObject); //tada
}
```

## Encoding packets
This is easy:

```php
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pmmp\encoding\ByteBufferWriter;

/** @var Packet[] $packets */
$stream = new ByteBufferWriter();
PacketBatch::encodePackets($stream, $packets);
$batchPayload = $stream->getData();
```

## Footnotes
This library is a little rough around the edges, since it's only ever been intended for PocketMine-MP usage. It's only recently that this mess has been separated from the core to allow it to be used by other things.
This means that API changes might be in order, and your feedback would be nice to drive them.
If you want to improve BedrockProtocol, please open issues with suggestions, or better, make pull requests.


## RyzeMCBE project stewardship

This repository is maintained by [RyzeMCBE](https://github.com/RyzeMCBE) as a dependency of [RyzeMP](https://github.com/RyzeMCBE/RyzeMP).
The original upstream contributors, source licenses, and compatibility-oriented PHP namespaces remain acknowledged and unchanged.
## Preparación de Minecraft Bedrock 1.16.100

La constante `PROTOCOL_1_16_100` y `ProtocolTransportInfo` describen el transporte
419; `LegacyLoginPacketDecoder` valida su Login inicial ya descomprimido usando
los lectores nativos. Esta primera etapa no añade 419 a `ACCEPTED_PROTOCOL`.
La integración incluye los formatos de entrada, terreno, jugadores, skins,
inventarios, solicitudes/respuestas de stacks y recetas, con traducción de IDs
419 al modelo nativo sin modificar los objetos enviados a sesiones modernas.
Los fixtures de inventario y paquetes opcionales usan el adaptador `v419` del ZIP
MultiVersion como referencia. Las pruebas con clientes reales siguen siendo
necesarias antes de declarar 419 estable.
