<?php

namespace CoinRex\Tests\Unit\Http;

use CoinRex\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoinRex\Http\Request
 */
class RequestTest extends TestCase
{
    public function testQueryReturnsDefaultWhenKeyMissing(): void
    {
        $request = new Request([], [], [], []);
        $this->assertNull($request->query('nonexistent'));
        $this->assertEquals('default', $request->query('nonexistent', 'default'));
    }

    public function testQueryReturnsValue(): void
    {
        $request = new Request(['foo' => 'bar'], [], [], []);
        $this->assertEquals('bar', $request->query('foo'));
    }

    public function testInputReturnsDefaultWhenKeyMissing(): void
    {
        $request = new Request([], [], [], []);
        $this->assertNull($request->input('nonexistent'));
        $this->assertEquals('default', $request->input('nonexistent', 'default'));
    }

    public function testInputReturnsValue(): void
    {
        $request = new Request([], ['name' => 'test'], [], []);
        $this->assertEquals('test', $request->input('name'));
    }

    public function testAllReturnsAllBodyData(): void
    {
        $body = ['a' => 1, 'b' => 2];
        $request = new Request([], $body, [], []);
        $this->assertEquals($body, $request->all());
    }

    public function testMethodReturnsUppercase(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'post'], []);
        $this->assertEquals('POST', $request->method());
    }

    public function testMethodDefaultsToGet(): void
    {
        $request = new Request([], [], [], []);
        $this->assertEquals('GET', $request->method());
    }

    public function testIsMethod(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'POST'], []);
        $this->assertTrue($request->isMethod('post'));
        $this->assertTrue($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('GET'));
    }

    public function testPath(): void
    {
        $request = new Request([], [], ['REQUEST_URI' => '/api/users'], []);
        $this->assertEquals('/api/users', $request->path());
    }

    public function testPathDefaultsToRoot(): void
    {
        $request = new Request([], [], [], []);
        $this->assertEquals('/', $request->path());
    }

    public function testSanitize(): void
    {
        $this->assertEquals('hello &amp; goodbye', Request::sanitize('hello & goodbye'));
        $this->assertEquals('test', Request::sanitize('  test  '));
    }

    public function testValidateRequired(): void
    {
        $request = new Request([], ['name' => 'John', 'email' => ''], [], []);
        $errors = $request->validateRequired(['name', 'email', 'age']);

        $this->assertArrayNotHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
        $this->assertStringContainsString('email', $errors['email'][0]);
        $this->assertStringContainsString('age', $errors['age'][0]);
    }
}
