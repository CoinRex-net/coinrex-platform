<?php
namespace CoinRex\Tests\Unit;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/config.php';
final class EngagementTest extends TestCase {
 public function testXProfileDomains():void{$this->assertTrue(engagementValidProfileUrl('x','https://x.com/coinrex'));$this->assertTrue(engagementValidProfileUrl('x','https://twitter.com/coinrex'));$this->assertFalse(engagementValidProfileUrl('x','https://example.com/coinrex'));}
 public function testTelegramProfileDomains():void{$this->assertTrue(engagementValidProfileUrl('telegram','https://t.me/coinrex'));$this->assertFalse(engagementValidProfileUrl('telegram','https://x.com/coinrex'));}
 public function testTelegramUrlAllowsUnderscoreAndNormalizesPastedFormatting():void{$url='https://t.me/CoinRex_Officials';$this->assertSame($url,engagementNormalizeUrl("\u{FEFF}\"https\\:\/\/t.me\/CoinRex\\_Officials\""));$this->assertSame($url,engagementNormalizeUrl("\u{00A0}".$url."\u{202F}"));$this->assertTrue(engagementValidHttpsUrl($url));$this->assertTrue(engagementValidProfileUrl('telegram',$url));$this->assertTrue(engagementValidProfileUrl('telegram',"\u{200B}".$url));}
 public function testSocialUrlsRequireHttps():void{$this->assertFalse(engagementValidHttpsUrl('http://t.me/CoinRex_Officials'));$this->assertFalse(engagementValidProfileUrl('telegram','http://t.me/CoinRex_Officials'));}
 public function testInvalidProfileUrl():void{$this->assertFalse(engagementValidProfileUrl('x','not-a-url'));}
 public function testAdminPageGuardsMissingSchemaInsteadOfStoppingAfterHeader():void{$root=dirname(__DIR__,2);$page=file_get_contents($root.'/admin/engagement.php');$functions=file_get_contents($root.'/includes/functions/engagement.php');$this->assertStringContainsString('ensureEngagementSchema($db)',$page);$this->assertStringContainsString('if ($schema_ready)',$page);$this->assertStringContainsString('engagementSchemaAvailable($db,true)',$functions);}
}
