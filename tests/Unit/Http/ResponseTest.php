<?php

namespace CoinRex\Tests\Unit\Http;

use CoinRex\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CoinRex\Http\Response
 */
class ResponseTest extends TestCase
{
    public function testSuccessResponse(): void
    {
        $response = Response::success(['data' => ['id' => 1]]);
        $data = $response->getData();

        $this->assertTrue($data['success']);
        $this->assertEquals(['id' => 1], $data['data']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testErrorResponse(): void
    {
        $response = Response::error('Something went wrong', 422);
        $data = $response->getData();

        $this->assertFalse($data['success']);
        $this->assertEquals('Something went wrong', $data['message']);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testErrorResponseWithExtraData(): void
    {
        $response = Response::error('Validation failed', 422, ['errors' => ['field' => 'Required']]);
        $data = $response->getData();

        $this->assertFalse($data['success']);
        $this->assertEquals('Validation failed', $data['message']);
        $this->assertEquals(['field' => 'Required'], $data['errors']);
    }

    public function testCustomStatusCode(): void
    {
        $response = Response::success([], 201);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testConstructorSetsData(): void
    {
        $response = new Response(200, ['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $response->getData());
        $this->assertEquals(200, $response->getStatusCode());
    }
}
