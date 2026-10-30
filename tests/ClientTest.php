<?php

namespace Oweb\License\Tests;

use Oweb\License\Client;
use Oweb\License\ConnectionException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class ClientTest extends TestCase
{
	public function testActivate(): void
	{
		$this->respond($this->body('activated'));

		$response = $this->client()->activate(static::KEY, 'example.com');

		$this->assertTrue($response['activated']);
		$this->assertSame('https://api.lemonsqueezy.com/v1/licenses/activate', $this->requests[0]['url']);
		$this->assertSame([
			'license_key'   => static::KEY,
			'instance_name' => 'example.com'
		], $this->requests[0]['data']);
	}

	public function testDeactivate(): void
	{
		$this->respond($this->body('deactivated'));

		$this->client()->deactivate(static::KEY, 'instance-1');

		$this->assertSame('deactivate', $this->requests[0]['action']);
		$this->assertSame([
			'license_key' => static::KEY,
			'instance_id' => 'instance-1'
		], $this->requests[0]['data']);
	}

	public function testValidate(): void
	{
		$this->respond($this->body());
		$this->respond($this->body());

		$client = $this->client();
		$client->validate(static::KEY);
		$client->validate(static::KEY, 'instance-1');

		$this->assertSame('validate', $this->requests[0]['action']);
		$this->assertSame(['license_key' => static::KEY], $this->requests[0]['data']);
		$this->assertSame(['license_key' => static::KEY, 'instance_id' => 'instance-1'], $this->requests[1]['data']);
	}

	public function testAnswersWithClientErrors(): void
	{
		$this->respond(['valid' => false, 'error' => 'license_key not found.'], 404);
		$this->respond(['activated' => false, 'error' => 'This license key has reached the activation limit.'], 400);

		$client = $this->client();

		$this->assertSame('license_key not found.', $client->validate('nope')['error']);
		$this->assertFalse($client->activate('nope', 'example.com')['activated']);
	}

	public static function connectionErrorProvider(): array
	{
		return [
			'no response'  => [0, ''],
			'rate limit'   => [429, '{"error": "Too Many Attempts."}'],
			'server error' => [500, '{"error": "Server Error"}'],
			'bad gateway'  => [502, '<html>Bad Gateway</html>'],
			'html'         => [200, '<html></html>'],
			'empty'        => [200, ''],
		];
	}

	#[DataProvider('connectionErrorProvider')]
	public function testConnectionErrors(int $code, string $body): void
	{
		$this->respond($body, $code);

		$this->expectException(ConnectionException::class);
		$this->client()->validate(static::KEY);
	}

	public function testTransportException(): void
	{
		$this->respondWith(new RuntimeException('Could not resolve host'));

		$this->expectException(ConnectionException::class);
		$this->expectExceptionMessage('Could not resolve host');
		$this->client()->validate(static::KEY);
	}

	public function testRemoteOptions(): void
	{
		$client = new Client(timeout: 5);

		$this->assertSame([
			'method'  => 'POST',
			'data'    => ['license_key' => 'abc'],
			'headers' => ['Accept' => 'application/json'],
			'timeout' => 5
		], $client->options(['license_key' => 'abc']));
	}
}
