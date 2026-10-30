<?php

namespace Oweb\License\Tests;

use Kirby\Exception\Exception;
use Oweb\License\Activation;
use Oweb\License\Storage;

class StorageTest extends TestCase
{
	protected function activation(string $key = self::KEY, string $domain = 'example.com'): Activation
	{
		return new Activation(
			key: $key,
			domain: $domain,
			instance: 'instance-1',
			store: 1,
			product: 100,
			activatedAt: 1700000000
		);
	}

	public function testMissingFile(): void
	{
		$storage = new Storage($this->file());

		$this->assertSame([], $storage->activations());
		$this->assertSame(['activations' => []], $storage->read());
	}

	public function testBrokenFile(): void
	{
		file_put_contents($this->file(), '{broken');

		$storage = new Storage($this->file());
		$this->assertSame([], $storage->activations());

		file_put_contents($this->file(), '"string"');
		$this->assertSame([], $storage->activations());

		file_put_contents($this->file(), '{"activations": "nope"}');
		$this->assertSame([], $storage->activations());
	}

	public function testSave(): void
	{
		$storage = new Storage($this->file());
		$storage->save($this->activation());

		$data = $this->stored();
		$this->assertSame(1, $data['version']);
		$this->assertCount(1, $data['activations']);
		$this->assertSame(static::KEY, $data['activations'][0]['key']);
		$this->assertSame('example.com', $data['activations'][0]['domain']);
		$this->assertSame(date(DATE_ATOM, 1700000000), $data['activations'][0]['activatedAt']);

		$activations = $storage->activations();
		$this->assertCount(1, $activations);
		$this->assertSame(1700000000, $activations[0]->activatedAt);
		$this->assertSame('instance-1', $activations[0]->instance);
		$this->assertSame(100, $activations[0]->product);

		// no temp files left
		$this->assertSame([Storage::FILENAME], array_values(array_diff(scandir(dirname($this->file())), ['.', '..'])));
	}

	public function testSaveReplacesSameKeyAndDomain(): void
	{
		$storage = new Storage($this->file());
		$storage->save($this->activation());
		$storage->save($this->activation(domain: 'other.com'));

		$activation = $this->activation();
		$activation->instance = 'instance-2';
		$storage->save($activation);

		$activations = $storage->activations();
		$this->assertCount(2, $activations);
		$this->assertSame('instance-2', $activations[0]->instance);
		$this->assertSame('other.com', $activations[1]->domain);
	}

	public function testRemove(): void
	{
		$storage = new Storage($this->file());
		$storage->save($this->activation());
		$storage->save($this->activation(domain: 'other.com'));
		$storage->remove($this->activation());

		$activations = $storage->activations();
		$this->assertCount(1, $activations);
		$this->assertSame('other.com', $activations[0]->domain);
	}

	public function testKeepsUnknownData(): void
	{
		file_put_contents($this->file(), json_encode([
			'version'     => 2,
			'future'      => ['a' => 1],
			'activations' => [
				[
					'key'      => static::KEY,
					'domain'   => 'example.com',
					'store'    => 1,
					'product'  => 100,
					'valid'    => true,
					'newField' => 'kept'
				],
				['broken' => true]
			]
		]));

		$storage    = new Storage($this->file());
		$activation = $storage->activations()[0];
		$activation->instance = 'instance-9';
		$storage->save($activation);

		$data = $this->stored();
		$this->assertSame(2, $data['version']);
		$this->assertSame(['a' => 1], $data['future']);
		$this->assertSame('kept', $data['activations'][0]['newField']);
		$this->assertSame('instance-9', $data['activations'][0]['instance']);
		$this->assertSame(['broken' => true], $data['activations'][1]);
	}

	public function testOffline(): void
	{
		$storage = new Storage($this->file());
		$this->assertNull($storage->offline());

		$storage->save($this->activation());
		$storage->markOffline(1700000000);

		$this->assertSame(1700000000, $storage->offline());
		$this->assertCount(1, $storage->activations());
		$this->assertSame(date(DATE_ATOM, 1700000000), $this->stored()['offlineAt']);
	}

	public function testWriteError(): void
	{
		$this->app();

		// a directory in place of the file can't be replaced
		mkdir($this->file());

		$storage = new Storage($this->file());

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('The license could not be saved in site/config/.owebstudio-licenses');

		try {
			$storage->save($this->activation());
		} finally {
			// the temp file is removed
			$this->assertSame([Storage::FILENAME], array_values(array_diff(scandir(dirname($this->file())), ['.', '..'])));
		}
	}

	public function testActivationIsDue(): void
	{
		$now        = 1700000000;
		$activation = $this->activation();

		$this->assertTrue($activation->isDue($now));

		$activation->validatedAt = $now - 3600;
		$this->assertFalse($activation->isDue($now));

		$activation->validatedAt = $now - Activation::LIFETIME - 1;
		$this->assertTrue($activation->isDue($now));

		$activation->checkedAt = $now - 3600;
		$this->assertFalse($activation->isDue($now));

		$activation->checkedAt = $now - Activation::RETRY - 1;
		$this->assertTrue($activation->isDue($now));
	}

	public function testActivationMaskedKey(): void
	{
		$this->assertSame('38b1…4d51', $this->activation()->maskedKey());
		$this->assertSame('•••••', $this->activation('short')->maskedKey());
	}

	public function testActivationFromInvalidData(): void
	{
		$this->assertNull(Activation::fromArray([]));
		$this->assertNull(Activation::fromArray(['key' => 1, 'domain' => 'example.com']));

		$activation = Activation::fromArray([
			'key'         => 'abc',
			'domain'      => 'example.com',
			'store'       => '1',
			'product'     => 'x',
			'valid'       => 'yes',
			'validatedAt' => 'not a date'
		]);

		$this->assertSame(1, $activation->store);
		$this->assertNull($activation->product);
		$this->assertFalse($activation->valid);
		$this->assertNull($activation->validatedAt);
	}
}
