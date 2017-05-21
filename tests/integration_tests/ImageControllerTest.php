<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImageControllerTest extends TestCase
{

    /** @var Database */
    public $pdo;
    private $userId;

    public function setUp()
    {
        $this->pdo = $pdo = new PDO(
            'mysql:host=' . getenv("DB_HOST") . ';dbname=' . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );

        $this->removeTestUserFromDatabase('test@testunit.com');
        $this->addNewUserToDatabase(1);
    }

    public function tearDown()
    {
        $this->removeTestUserFromDatabase('test@testunit.com');
    }

    public function addNewUserToDatabase(int $active = 0) {

        $stmt = $this->pdo->prepare("
            insert into users (name, password, email, activ, activ_code, request_limit, created_at, updated_at)  
            values (:name, :password, :email, :activ, :activ_code, :request_limit, :created_at, :updated_at)
        ");

        $name = 'testname';
        $password = password_hash('testPassword',PASSWORD_DEFAULT);
        $email = 'test@testunit.com';
        $activCode = 12345;
        $requestLimit = 2;
        $created_at = '2017-04-29 15:26:44';
        $updated_at = '2017-04-29 15:29:20';

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':activ', $active);
        $stmt->bindParam(':activ_code', $activCode);
        $stmt->bindParam(':request_limit', $requestLimit);
        $stmt->bindParam(':created_at', $created_at);
        $stmt->bindParam(':updated_at', $updated_at);

        $stmt->execute();

        $this->userId = $this->pdo->lastInsertId();
    }

    public function removeTestUserFromDatabase(string $email = '') {
        if ($email != '') {
            $stmt = $this->pdo->prepare("
              DELETE FROM users WHERE email = :email  
            ");
            $stmt->bindParam(':email', $email);
        } else {
            $stmt = $this->pdo->prepare("
              DELETE FROM users WHERE id = :id  
            ");
            $stmt->bindParam(':id', $this->userId);
        }
        $stmt->execute();
    }

    public function removeTestUserRequestHistoryFromDatabase()
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM hostory WHERE userid = :id  
        ");

        $stmt->bindParam(':id', $this->userId);
        $stmt->execute();
    }

    public function getImagePathFromUrl($url) {
        $folderToRemove = explode('/', $url)[4];
        return __DIR__ . '/../../public/tmp/' . $folderToRemove;
    }

    public function removeTestImages($json) {
        $url = json_decode($json, true)['path_to_file'];
        $this->deleteDir($this->getImagePathFromUrl($url));
    }

    public static function deleteDir($dirPath) {
        if (! is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    public function getRequestHistoryFromDatabase(string $directoryName) {
        $stmt = $this->pdo->prepare("
              SELECT * FROM history WHERE directory = :directory
            ");
        $stmt->bindParam(':directory', $directoryName);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * @expectedException GuzzleHttp\Exception\ClientException
     */

    public function testAuthenticationFailed() {
        $client = new \GuzzleHttp\Client();

        $res = $client->request(
            'GET',
            'http://imageoptimizerapi.dev/api'
        );

        //$this->assertEquals(401, $res->getStatusCode());
        //$this->assertEquals('application/json', $res->getHeaderLine('content-type'));
        //$this->assertEquals('{"status":"error","message":"Authentication failed"}',  $res->getBody());
    }


    public function testImageDontFound() {
        $client = new \GuzzleHttp\Client();

        $credentials = base64_encode('testname:testPassword');

        $res = $client->post(
            'http://imageoptimizerapi.dev/api',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'http_errors' => false
            ]
        );

        $this->assertEquals('{"message":"Image don\'t found."}', (string) $res->getBody());
        $this->assertEquals(400, $res->getStatusCode());
        $this->assertEquals('application/json;charset=utf-8', $res->getHeaderLine('content-type'));

    }

    public function testErrorWhenUserAccountDontActive() {
        $this->removeTestUserFromDatabase();
        $this->addNewUserToDatabase(0);

        $client = new \GuzzleHttp\Client();

        $credentials = base64_encode('testname:testPassword');

        $imageFile = fopen(__DIR__ . '/test.png', 'r');

        $res = $client->post(
            'http://imageoptimizerapi.dev/api',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => $imageFile
                    ]
                ],
                'http_errors' => false
            ]
        );

        $this->assertEquals('{"message":"Account email is not confirmed."}', (string) $res->getBody());
        $this->assertEquals(401, $res->getStatusCode());
        $this->assertEquals('application/json;charset=utf-8', $res->getHeaderLine('content-type'));

        $this->removeTestUserFromDatabase();
        $this->addNewUserToDatabase(1);

    }

    public function testErrorWhenUploadedFileHatFalseFormat() {
        $client = new \GuzzleHttp\Client();

        $credentials = base64_encode('testname:testPassword');

        $imageFile = fopen(__DIR__ . '/testBadFormatFile.txt', 'r');

        $res = $client->post(
            'http://imageoptimizerapi.dev/api',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => $imageFile
                    ]
                ],
                'http_errors' => false
            ]
        );

        $this->assertEquals('{"message":"Bad image format."}', (string) $res->getBody());
        $this->assertEquals(400, $res->getStatusCode());
        $this->assertEquals('application/json;charset=utf-8', $res->getHeaderLine('content-type'));
    }

    public function testFileNameIfIsTooLong() {
        $client = new \GuzzleHttp\Client();

        $credentials = base64_encode('testname:testPassword');

        // 251 chars filename
        $imageFile = fopen(__DIR__ . '/12345678901234567890123456789012345678901234567890' .
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890' .
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567.png',
            'r');

        $res = $client->post(
            'http://imageoptimizerapi.dev/api',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => $imageFile
                    ]
                ],
                'http_errors' => false
            ]
        );

        $this->assertEquals('{"message":"File name is too long. Max 250 chars."}', (string) $res->getBody());
        $this->assertEquals(400, $res->getStatusCode());
        $this->assertEquals('application/json;charset=utf-8', $res->getHeaderLine('content-type'));
    }

    public function testPngConvertedSuccessful() {
        $client = new \GuzzleHttp\Client();

        $credentials = base64_encode('testname:testPassword');

        // 250 chars filename
        $fileName = '12345678901234567890123456789012345678901234567890' .
            '1234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890' .
            '123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456.png';

        $filePath = __DIR__ . '/' . $fileName;

        $imageFile = fopen($filePath,'r');

        $res = $client->post(
            'http://imageoptimizerapi.dev/api',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => $imageFile
                    ]
                ],
                'http_errors' => false
            ]
        );

        $this->assertContains(
            '{"message":"Image compresed successful.","path_to_file":"',
            (string) $res->getBody()
        );
        $this->assertEquals(200, $res->getStatusCode());
        $this->assertEquals('application/json;charset=utf-8', $res->getHeaderLine('content-type'));

        $originalImageSize = filesize($filePath);
        $convertedImageSize = filesize(
            $this->getImagePathFromUrl(json_decode((string) $res->getBody(), true)['path_to_file'])
        );

        $this->assertLessThan($originalImageSize, $convertedImageSize);

        $directoryName = explode(
            '/',
            json_decode((string) $res->getBody(), true)['path_to_file']
        )[4];

        $requestFromHistory = $this->getRequestHistoryFromDatabase($directoryName);

        $this->assertEquals($fileName, $requestFromHistory['filename']);
        $this->assertEquals($this->userId, $requestFromHistory['userid']);

        $this->assertEquals(
            '{"message":"Image compresed successful.","path_to_file":"' .
            json_decode((string) $res->getBody(), true)['path_to_file'].'"}',
            (string) $res->getBody()
        );

        $this->removeTestImages((string) $res->getBody());
    }

    public function testErrorWhenUserSendToManyRequest() {
        $this->removeTestUserRequestHistoryFromDatabase();

        //@todo convert simulate (sql query)
        $this->testPngConvertedSuccessful();
        $this->testPngConvertedSuccessful();
        $this->testPngConvertedSuccessful();

        $client = new \GuzzleHttp\Client();

        $credentials = base64_encode('testname:testPassword');

        // 250 chars filename
        $fileName = 'test.png';

        $filePath = __DIR__ . '/' . $fileName;

        $imageFile = fopen($filePath,'r');

        $res = $client->post(
            'http://imageoptimizerapi.dev/api',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => $imageFile
                    ]
                ],
                'http_errors' => false
            ]
        );

        $this->assertEquals(
            '{"message":"Too many requests. Monthly limit exceeded."}',
            (string) $res->getBody()
        );

        $this->assertEquals(400, $res->getStatusCode());
        $this->assertEquals('application/json;charset=utf-8', $res->getHeaderLine('content-type'));
    }

    /*
    public function testErrorWhenUploadedFileIsTooBig() {
        $client = new \GuzzleHttp\Client();

        $credentials = base64_encode('testname:testPassword');

        $imageFile = fopen(__DIR__ . '/tooBig.png', 'r');

        $res = $client->post(
            'http://imageoptimizerapi.dev/api',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'multipart' => [
                    [
                        'name'     => 'image',
                        'contents' => $imageFile
                    ]
                ],
                'http_errors' => false
            ]
        );

        $this->assertEquals('{"message":"Image is too big."}', (string) $res->getBody());
        $this->assertEquals(400, $res->getStatusCode());
        $this->assertEquals('application/json;charset=utf-8', $res->getHeaderLine('content-type'));
    }
    */

}
