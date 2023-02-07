<?php declare(strict_types = 1);

namespace FastyBird\Connector\Modbus\Tests\Cases\Unit\API;

use FastyBird\Connector\Modbus\API;
use FastyBird\Connector\Modbus\Exceptions;
use FastyBird\Connector\Modbus\Tests\Cases\Unit\BaseTestCase;
use FastyBird\Connector\Modbus\Types\ByteOrder;
use Nette;
use function floatval;
use function pack;
use function round;
use function unpack;

final class TransformerTest extends BaseTestCase
{

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testBytesToUnsignedIntBigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = unpack('C*', "\x00\x01");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x00\x01");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_LOW_WORD_FIRST)),
		);
		$bytes = unpack('C*', "\x00\x01");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_SWAP)),
		);
		$bytes = unpack('C*', "\x7F\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_767,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x80\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_768,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\xFF\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			65_535,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testBytesToUnsignedIntLittleEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = unpack('C*', "\x01\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\x01\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE_LOW_WORD_FIRST)),
		);
		$bytes = unpack('C*', "\x01\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE_SWAP)),
		);
		$bytes = unpack('C*', "\xFF\x7F");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_767,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\x00\x80");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_768,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\xFF\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			65_535,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testBytesToSignedIntBigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = unpack('C*', "\x00\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			0,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x00\x01");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x00\x01");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_LOW_WORD_FIRST)),
		);
		$bytes = unpack('C*', "\x00\x01");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_SWAP)),
		);
		$bytes = unpack('C*', "\x7F\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_767,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\xFF\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			-1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x80\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			-32_768,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x7F\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_767,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testBytesToSignedIntLittleEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = unpack('C*', "\x00\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			0,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\x01\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\x01\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE_LOW_WORD_FIRST)),
		);
		$bytes = unpack('C*', "\x01\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE_SWAP)),
		);
		$bytes = unpack('C*', "\xFF\x7F");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_767,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\xFF\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			-1,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\x00\x80");
		self::assertIsArray($bytes);
		self::assertEquals(
			-32_768,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
		$bytes = unpack('C*', "\xFF\x7F");
		self::assertIsArray($bytes);
		self::assertEquals(
			32_767,
			$transformer->unpackSignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE)),
		);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testBytesToUnsignedInt32BigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = unpack('C*', "\x00\x00\x00\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			0,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x00\x00\x00\x01");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x00\x01\x00\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_LOW_WORD_FIRST)),
		);
		$bytes = unpack('C*', "\x00\x00\x01\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			1,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_SWAP)),
		);
		$bytes = unpack('C*', "\x7F\xFF\xFF\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			2_147_483_647,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\x80\x00\x00\x00");
		self::assertIsArray($bytes);
		self::assertEquals(
			2_147_483_648,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
		$bytes = unpack('C*', "\xFF\xFF\xFF\xFF");
		self::assertIsArray($bytes);
		self::assertEquals(
			4_294_967_295,
			$transformer->unpackUnsignedInt($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG)),
		);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testBytesToFloatBigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = unpack('C*', "\x3f\xec\xcc\xcd");
		self::assertIsArray($bytes);
		self::assertEquals(
			1.85,
			round(floatval($transformer->unpackFloat($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG))), 2),
		);
		$bytes = unpack('C*', "\x3f\x2a\xaa\xab");
		self::assertIsArray($bytes);
		self::assertEquals(
			0.666_666_686_53,
			round(floatval($transformer->unpackFloat($bytes, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG))), 11),
		);
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testUnsignedIntToBytesBigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x01", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_LOW_WORD_FIRST));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x01", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_SWAP));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x01", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(32_767, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x7F\xFF", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(32_768, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x80\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(65_535, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\xFF\xFF", pack('C*', ...$bytes));
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testUnsignedIntToBytesLittleEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE));
		self::assertIsArray($bytes);
		self::assertEquals("\x01\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE_LOW_WORD_FIRST));
		self::assertIsArray($bytes);
		self::assertEquals("\x01\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE_SWAP));
		self::assertIsArray($bytes);
		self::assertEquals("\x01\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(32_767, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE));
		self::assertIsArray($bytes);
		self::assertEquals("\xFF\x7F", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(32_768, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x80", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(65_535, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_LITTLE));
		self::assertIsArray($bytes);
		self::assertEquals("\xFF\xFF", pack('C*', ...$bytes));
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testSignedIntToBytesBigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = $transformer->packUnsignedInt(0, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x01", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_LOW_WORD_FIRST));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x01", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_SWAP));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x01", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(32_767, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x7F\xFF", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(-1, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\xFF\xFF", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(-32_768, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x80\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(32_767, 2, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x7F\xFF", pack('C*', ...$bytes));
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testSignedInt32ToBytesBigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = $transformer->packUnsignedInt(0, 4, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x00\x00\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 4, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x00\x00\x01", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 4, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_LOW_WORD_FIRST));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x01\x00\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(1, 4, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG_SWAP));
		self::assertIsArray($bytes);
		self::assertEquals("\x00\x00\x01\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(2_147_483_647, 4, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x7F\xFF\xFF\xFF", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(2_147_483_648, 4, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x80\x00\x00\x00", pack('C*', ...$bytes));

		$bytes = $transformer->packUnsignedInt(4_294_967_295, 4, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\xFF\xFF\xFF\xFF", pack('C*', ...$bytes));
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 */
	public function testFloatToBytesBigEndian(): void
	{
		$container = $this->createContainer();

		$transformer = $container->getByType(API\Transformer::class, false);
		self::assertTrue($transformer instanceof API\Transformer);

		$bytes = $transformer->packFloat(1.85, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x3f\xec\xcc\xcd", pack('C*', ...$bytes));

		$bytes = $transformer->packFloat(0.666_666_686_53, ByteOrder::get(ByteOrder::BYTE_ORDER_BIG));
		self::assertIsArray($bytes);
		self::assertEquals("\x3f\x2a\xaa\xab", pack('C*', ...$bytes));
	}

}
