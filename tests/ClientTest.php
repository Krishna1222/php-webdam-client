<?php

/**
 * @file
 * Tests cweagans\webdam\Client.
 */

namespace cweagans\webdam\tests;

use cweagans\webdam\Client;
use Drupal\media_webdam\Webdam;
use GuzzleHttp\Client as GClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase {

  /**
   * Tests bad username/password behavior.
   *
   * @expectedException \cweagans\webdam\Exception\InvalidCredentialsException
   * @expectedExceptionMessage Invalid username and password combination (invalid_grant).
   */
  public function testInvalidGrant() {
    $mock = new MockHandler([
      new Response(400, [], '{"error":"invalid_grant","error_description":"Invalid username and password combination"}')
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);

    $client = new Client($guzzleClient, '', '', '', '');

    $authstate = $client->getAuthState();
    $this->assertFalse($authstate['valid_token']);
    $client->checkAuth();
  }

  /**
   * Tests bad client id/secret behavior.
   *
   * @expectedException \cweagans\webdam\Exception\InvalidCredentialsException
   * @expectedExceptionMessage The client credentials are invalid (invalid_client).
   */
  public function testInvalidClient() {
    $mock = new MockHandler([
      new Response(400, [], '{"error":"invalid_client","error_description":"The client credentials are invalid"}')
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);

    $client = new Client($guzzleClient, '', '', '', '');

    $authstate = $client->getAuthState();
    $this->assertFalse($authstate['valid_token']);
    $client->checkAuth();
  }

  /**
   * Tests successful authentication.
   */
  public function testSuccessfulAuthentication() {
    $mock = new MockHandler([
      new Response(200, [], '{"access_token":"da39a3ee5e6b4b0d3255bfef95601890afd80709", "expires_in":3600, "token_type":"bearer", "refresh_token":"REFRESH_TOKEN"}'),
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);

    $client = new Client($guzzleClient, '', '', '', '');

    $authstate = $client->getAuthState();
    $this->assertFalse($authstate['valid_token']);
    $client->checkAuth();
    $expected_expiry = time() + 3600;

    $authstate = $client->getAuthState();
    $this->assertTrue($authstate['valid_token']);
    $this->assertEquals('da39a3ee5e6b4b0d3255bfef95601890afd80709', $authstate['access_token']);
    $this->assertEquals($expected_expiry, $authstate['access_token_expiry']);

    // Check re-auth behavior. If this tries to actually reauth, Guzzle will throw a queue empty exception.
    $client->checkAuth();
    $this->assertTrue($authstate['valid_token']);
    $this->assertEquals('da39a3ee5e6b4b0d3255bfef95601890afd80709', $authstate['access_token']);
    $this->assertEquals($expected_expiry, $authstate['access_token_expiry']);
  }

  /**
   * Test getAccountSubscriptionDetails().
   */
  public function testGetAccountSubscriptionDetails() {
    $mock = new MockHandler([
      new Response(200, [], '{"access_token":"ACCESS_TOKEN", "expires_in":3600, "token_type":"bearer", "refresh_token":"REFRESH_TOKEN"}'),
      new Response(200, [], '{"maxAdmins": "5","numAdmins": "4","maxContributors": "10","numContributors": 0,"maxEndUsers": "15","numEndUsers": 0,"maxUsers": 0,"url": "accounturl.webdamdb.com","username": "username","planDiskSpace": "10000 MB","currentDiskSpace": "45 MB","activeUsers": "4","inactiveUsers": 0}')
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);

    $client = new Client($guzzleClient, '', '', '', '');

    $account = $client->getAccountSubscriptionDetails();
    $this->assertTrue(is_object($account));

    $attributes = ['maxAdmins', 'numAdmins', 'maxContributors', 'numContributors', 'maxEndUsers', 'maxUsers', 'url', 'username', 'planDiskSpace', 'activeUsers', 'inactiveUsers'];
    foreach ($attributes as $attribute) {
      $this->assertObjectHasAttribute($attribute, $account);
    }
  }

  /**
   * Test getFolder().
   */
  public function testGetFolder() {
    $mock = new MockHandler([
      new Response(200, [], '{"access_token":"ACCESS_TOKEN", "expires_in":3600, "token_type":"bearer", "refresh_token":"REFRESH_TOKEN"}'),
      new Response(200, [], file_get_contents(__DIR__ . '/json/folder.json')),
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);

    $client = new Client($guzzleClient, '', '', '', '');

    $folder = $client->getFolder(12345);
    $this->assertTrue(is_object($folder));
    $this->assertInstanceOf('cweagans\webdam\Entity\Folder', $folder);
  }

  public function testGetTopLevelFolders() {
    $mock = new MockHandler([
      new Response(200, [], '{"access_token":"ACCESS_TOKEN", "expires_in":3600, "token_type":"bearer", "refresh_token":"REFRESH_TOKEN"}'),
      new Response(200, [], '[' . file_get_contents(__DIR__ . '/json/folder.json') . ']'),
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);

    $client = new Client($guzzleClient, '', '', '', '');

    $folder = $client->getTopLevelFolders();
    $this->assertTrue(is_array($folder));
    $this->assertInstanceOf('cweagans\webdam\Entity\Folder', $folder[0]);
  }

  /**
   * Tests getPresignUrl.
   */
  public function testGetPresignUrl() {
    $mock = new MockHandler([
      new Response(200, [], '{"access_token":"ACCESS_TOKEN", "expires_in":3600, "token_type":"bearer", "refresh_token":"REFRESH_TOKEN"}'),
      new Response(200, [], file_get_contents(__DIR__ . '/json/presign.json')),
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);
    $client = new Client($guzzleClient, '', '', '', '');

    $file_data = [
      "contenttype" => "image/png",
      "filename" => "not_for_real.png",
      "filesize" => "664",
      "file_uri" => "public://media_webdam/not_for_real.png",
      "folderid" => 112233,
    ];
    $presignUrl = $client->getPresignUrl($file_data);

    // Checks on the object retrieved. Structure and 'content'.
    self::assertTrue(is_object($presignUrl));
    self::assertObjectHasAttribute('presignedUrl', $presignUrl);
    self::assertObjectHasAttribute('processId', $presignUrl);
    self::assertTrue(is_string($presignUrl->processId));
    self::assertContains('AWSAccessKeyId', $presignUrl->presignedUrl);
    self::assertContains('Expires', $presignUrl->presignedUrl);
    self::assertContains('Signature', $presignUrl->presignedUrl);

  }

  /**
   * Tests uploadPresigned.
   */
  public function testUploadPresigned() {
    $mock = new MockHandler([
      new Response(200, [], '{"access_token":"ACCESS_TOKEN", "expires_in":3600, "token_type":"bearer", "refresh_token":"REFRESH_TOKEN"}'),
      new Response(200),
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);
    $client = new Client($guzzleClient, '', '', '', '');

    $presignUrl = file_get_contents(__DIR__ . '/json/presign.json');
    $file_type = 'image/png';
    // @TODO: It's probably not correct to have a real test file.
    $file_uri = __DIR__ . '/not_for_real.png';

    $uploadPresigned = $client->uploadPresigned($presignUrl, $file_uri, $file_type);
    // Check if status is 200.
    self::assertEquals($uploadPresigned, ['status' => 200]);

  }

  /**
   * Tests uploadPresigned.
   */
  public function testUploadConfirmed() {
    $mock = new MockHandler([
      new Response(200, [], '{"access_token":"ACCESS_TOKEN", "expires_in":3600, "token_type":"bearer", "refresh_token":"REFRESH_TOKEN"}'),
      new Response(200),
    ]);
    $handler = HandlerStack::create($mock);
    $guzzleClient = new GClient(['handler' => $handler]);
    $client = new Client($guzzleClient, '', '', '', '');

    $processId = "123456789";
    $uploadConfirmed = $client->uploadConfirmed($processId);
    // Check if new asset ID is a string.
    self::assertTrue(is_string($uploadConfirmed));
  }

}

class TestClassToTest extends Client
{
  public function getPresignUrl(array $file_data)
  {
    return parent::getPresignUrl($file_data);
  }
}

