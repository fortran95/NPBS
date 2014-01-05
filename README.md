Neoatlantis Packet Broadcasting System Version 1
================================================

This project implements a simple but robust system, namely the NPBS(Neoatlantis
Packet Broadcasting system), which is designed to broadcast small data packets
over the Internet.

The idea of this system is similar to APRS(Automatic Position Reporting System)
used in communications over radios. A single web page, written in PHP, Python
or Javascript, GETs a small packet from URL. The packet is designed to have a
limited length, which will not lead to problems to most servers.

The packet will then be broadcasted, by this web page and by visiting a series
of other URL adresses. By one broadcast, the TTL of this packet is reduced by
one.

The web page, who receives such broadcasting, is allowed to decide if forward
this packet, or drop it, or convert it into other formats and broadcast it
over other protocols(for example, over XMPP or Email).

## Implementations

This project provides implementations with different languages, as labeled by
directories. Each of them should run with same behaviour. 

The received NPBS Packets will be stored normally in text file.


## NPBS Packet

The NPBS Packet is a string, constructed in following way:

                +-----+-----+-------+----------+----------------------+
    Fields      | VER | TTL | LABEL | CHECKSUM |         DATA         |
                +-----+-----+-------+----------+----------------------+
    Length      |  5  |  2  |   8   |    40    |  4 <= LENGTH <=1600  | (bytes)
                +-----+-----+-------+----------+----------------------+
    Charset     | VER | HEX |  C+N  |    HEX   |         B64M         |
                +-----+-----+-------+----------+----------------------+

VER stands for version and is equal to ascii string `NPBS1`.

### Charset

HEX, stands for case-insensitive character series with `0-9` and `a-f`.

C+N, stands for case-insensitive character series with `0-9` and `a-z`.

B64M, stands for standard Base64 encoding, but use such replacements:
* `+` replaced by `_`.
* `/` replaced by `-`.
* `=` replaced by `*`.

### CHECKSUM 

The CHECKSUM fields records a checksum of the raw(not Base64-encoded) data.

In this version, the algorithm used for this checksum is **SHA-1**.

### TTL

The TTL field records how much times the packet is broadcasted. This number
begins with 255(0xFF). Each time when the packet is being repeated, this value
should be reduced by one. If a packet with TTL=0 is received, it MUST NOT be
repeated, but can be proceeded.

### LABEL

The LABEL field allows for the record of the sender. It is however possible,
that someone forges the LABEL. Therefore it is used only to classify this
packet, but not reliable enough to determine who really sended this packet.

The user who in real need of such certainty should use authentication in
packet data.


## Duplicated NPBS Packets

Duplicated NPBS Packets are defined as packets with same `CHECKSUM` fields.

Duplicated NPBS Packets should be dropped by a node immediately, with no
recordings. The actual checksum calculation basing on received `DATA` should in
this case not performed, out of following reasons:

1. It is not likely to have a transmitting error, that will result in the
change of CHECKSUM _frequently_. Because packets are transmitted via URL and
`CHECKSUM` are encoded in HEX. If there is one, we should use actual
calculation to find it out.

2. A normal application will not transmit different packets using same
`CHECKSUM`. A DOS(Denial-Of-Service) application is possible. Therefore we'll
just ignore it.


