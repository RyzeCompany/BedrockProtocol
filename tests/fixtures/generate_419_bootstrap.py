"""Wire fixtures from the historical 419 field order, independent of PHP codecs.

Reference: pmmp/BedrockProtocol@fbd701bae86eded8a9953118c425e1777220a53b.
These are protocol-layout fixtures, not authenticated client captures.
"""
import json
import struct
from pathlib import Path


def u(value):
    out = bytearray()
    while value > 127:
        out.append((value & 127) | 128)
        value >>= 7
    out.append(value)
    return bytes(out)


def z(value):
    return u(value * 2 if value >= 0 else -value * 2 - 1)


def s(value):
    data = value.encode()
    return u(len(data)) + data


def f(value):
    return struct.pack('<f', value)


def rules():
    # Rule names, type IDs and values; no modern editable flag in 419.
    return (u(3) + s('dodaylightcycle') + u(1) + b'\x00'
            + s('randomtickspeed') + u(2) + u(3)
            + s('testfloat') + u(3) + f(-0.75))


def experiments():
    return struct.pack('<I', 1) + s('data_driven_items') + b'\x01\x00'


def pack(pack_id, version, size, key, sub, content, scripts):
    return (s(pack_id) + s(version) + struct.pack('<Q', size)
            + s(key) + s(sub) + s(content) + bytes([scripts]))


def generate():
    seed = z(-123456789)
    player = z(-9) + u(42) + z(0) + f(1.5) + f(65) + f(-2.25) + f(10) + f(-30)
    level = (
        seed + struct.pack('<H', 0) + s('plains') + z(2)
        + z(1) + z(1) + z(2) + z(-12) + u(70) + z(34)
        + b'\x01' + z(6000) + z(0) + b'\x00' + s('')
        + f(0.25) + f(0.5) + b'\x00\x01\x01' + z(3) + z(3)
        + b'\x01\x01' + rules() + experiments() + b'\x01\x00'
        + z(1) + struct.pack('<i', 4) + b'\x00\x01\x00\x00\x00\x01\x00'
        + s('1.16.100') + struct.pack('<ii', 128, 256) + b'\x01\x01\x00'
    )
    tail = (s('test-level') + s('HCF Legacy') + s('') + b'\x00' + z(1)
            + struct.pack('<Q', 12345) + z(-7)
            + u(1) + s('minecraft:stone') + b'\x0a\x00\x00'
            + u(2) + s('minecraft:shield') + struct.pack('<h', 355) + b'\x00'
            + s('minecraft:wither_rose') + struct.pack('<h', -216) + b'\x01'
            + s('corr-419') + b'\x01')
    resource_id = '11111111-2222-3333-4444-555555555555'
    behavior = pack('behavior', '1.0.0', 21, 'key-b', 'sub-b', 'cid-b', False)
    resource = pack(resource_id, '2.3.4', 0x100000002, 'key-r', 'sub-r', 'cid-r', True)
    fixtures = {
        'source_commit': 'fbd701bae86eded8a9953118c425e1777220a53b',
        'protocol': 419,
        'start_game': (u(11) + player + level + tail).hex(),
        'seed_offset': len(u(11) + player),
        'seed_size': len(seed),
        'resource_packs_info': (u(6) + b'\x01\x01' + struct.pack('<H', 1)
                                + behavior + struct.pack('<H', 1) + resource).hex(),
        'resource_pack_stack': (u(7) + b'\x01' + u(1) + s('behavior') + s('1.0.0') + s('sub-b')
                                + u(1) + s(resource_id) + s('2.3.4') + s('sub-r')
                                + s('1.16.100') + experiments()).hex(),
        'request_chunk_radius': (u(69) + z(8)).hex(),
        'chunk_publisher': (u(121) + z(-12) + z(70) + z(34) + u(128)).hex(),
        'level_chunk': (u(58) + z(-3) + z(5) + u(16) + b'\x01' + u(2)
                        + struct.pack('<QQ', 0x0102030405060708, 0x1234) + s('XYZ')).hex(),
        'game_rules_changed': (u(72) + rules()).hex(),
    }
    target = Path(__file__).resolve().parents[1] / 'phpunit/fixtures/bootstrap_419.json'
    target.write_text(json.dumps(fixtures, indent=4) + '\n')


if __name__ == '__main__':
    generate()
