"""Independent 419 wire fixtures from pmmp/BedrockProtocol fbd701b (not client captures)."""
import base64
import json
import struct
from pathlib import Path

def u(n):
    out = bytearray()
    while n > 127:
        out.append((n & 127) | 128)
        n >>= 7
    return bytes(out) + bytes([n])
def v(n): return u((n << 1) ^ (n >> 63))
def s(x):
    raw = x.encode()
    return u(len(raw)) + raw
def f(*n): return struct.pack('<' + 'f' * len(n), *n)
def packet(name, pid, body): return {'name': name, 'bytes': base64.b64encode(u(pid) + body).decode()}
adventure = u(96) + u(0) + u(31) + u(1) + u(0) + struct.pack('<q', 7)
image = struct.pack('<II', 64, 32) + s('\0' * 8192)
cape = struct.pack('<II', 0, 0) + s('')
skin = s('test') + s('{"geometry":{"default":"geometry.humanoid.custom"}}') + image + struct.pack('<I', 0) + cape + s('{}') + s('') + bytes([0, 0, 0]) + s('') + s('test-full') + s('wide') + s('#000000') + struct.pack('<II', 0, 0)
fixtures = [
 packet('PlayerAuthInputPacket', 0x90, f(10, 20, 1, 65.62, 2, 0, 1, 20) + u(1 << 25) + u(1) + u(0) + u(42) + f(0, 0, 0)),
 packet('TextPacket', 0x09, bytes([0, 0]) + s('419 test') + s('') + s('')),
 packet('CommandRequestPacket', 0x4d, s('/help') + u(0) + bytes(16) + s('') + bytes([0])),
 packet('PlayerActionPacket', 0x24, u(7) + v(0) + v(1) + u(64) + v(2) + v(1)),
 packet('AdventureSettingsPacket', 0x37, adventure),
 packet('CraftingDataPacket', 0x34, bytes([0, 0, 0, 1])),
 packet('UpdateAttributesPacket', 0x1d, u(7) + u(1) + f(0, 20, 18, 20) + s('minecraft:health') + u(0)),
 packet('SetActorDataPacket', 0x27, u(7) + u(1) + u(4) + u(4) + s('Legacy') + u(0)),
 packet('InventoryTransactionPacket', 0x1e, v(0) + u(0) + bytes([0]) + u(0)),
 packet('AddActorPacket', 0x0d, v(7) + u(7) + s('minecraft:pig') + f(1, 64, 2, 0, 0, 0, 0, 0, 0) + u(0) + u(0) + u(0)),
 packet('AddPlayerPacket', 0x0c, bytes(16) + s('Tester') + v(7) + u(7) + s('') + f(1, 65.62, 2, 0, 0, 0, 0, 0, 0) + v(0) + u(0) + adventure + u(0) + s('') + struct.pack('<i', 1)),
 packet('PlayerListPacket', 0x3f, bytes([0]) + u(1) + bytes(16) + v(7) + s('Tester') + s('') + s('') + struct.pack('<i', 1) + skin + bytes([0, 0, 1])),
 packet('ModalFormResponsePacket', 0x65, u(3) + s('null\n')),
]
Path(__file__).parents[1].joinpath('phpunit/fixtures/gameplay_419.json').write_text(json.dumps(fixtures, indent=2) + '\n')
