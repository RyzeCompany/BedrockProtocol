# BedrockProtocol 1.0.0-beta.2

Minecraft Bedrock 1.26.60 (protocol 2225) is now the current advertised version. Protocol 2193 and the existing multiversion list remain accepted. Existing packet changes for 2225, including the new packet IDs, network settings, level chunks, animation, actors and level settings, are included.

Current version metadata is reproducible with `php tools/generate-protocol-info.php tools/current-version.json --version-only`. That mode preserves legacy aliases, packet IDs, pools and handlers.

Validated by the complete protocol test suite and the RyzeMP native NetherNet/PHAR integration. The operator has confirmed authenticated login and world entry with these codecs. Further gameplay and reconnect checks on the actual host remain part of beta validation.

Composer: `ryzemcbe/bedrock-protocol:1.0.0-beta.2`. Package name, PHP namespaces and PocketMine plugin compatibility are preserved.
