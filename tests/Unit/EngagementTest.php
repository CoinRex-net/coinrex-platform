<?php
namespace CoinRex\Tests\Unit;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/config.php';
final class EngagementTest extends TestCase {
 public function testXProfileDomains():void{$this->assertTrue(engagementValidProfileUrl('x','https://x.com/coinrex'));$this->assertTrue(engagementValidProfileUrl('x','https://twitter.com/coinrex'));$this->assertFalse(engagementValidProfileUrl('x','https://example.com/coinrex'));}
 public function testTelegramProfileDomains():void{$this->assertTrue(engagementValidProfileUrl('telegram','https://t.me/coinrex'));$this->assertFalse(engagementValidProfileUrl('telegram','https://x.com/coinrex'));}
 public function testInvalidProfileUrl():void{$this->assertFalse(engagementValidProfileUrl('x','not-a-url'));}
}
