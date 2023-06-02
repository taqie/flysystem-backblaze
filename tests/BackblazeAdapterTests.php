<?php

namespace Taqie\Flysystem\Tests;

use BackblazeB2\Client;
use BackblazeB2\File;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamFile;
use Taqie\Flysystem\BackblazeAdapter;

class BackblazeAdapterTests extends MockeryTestCase
{
    /**
     * @var vfsStreamDirectory
     */
    private $fs_mock;

    /**
     * @var vfsStreamFile
     */
    private $file_mock;

    private function fileSetUp()
    {
        $this->fs_mock = vfsStream::setup();
        $this->file_mock = new vfsStreamFile('filename.ext');
        $this->fs_mock->addChild($this->file_mock);
    }

    public static function backblazeProvider(): array
    {
        $mock = Mockery::mock(Client::class);

        return [
            [new BackblazeAdapter($mock, 'my_bucket'), $mock],
        ];
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testFileExists(FilesystemAdapter $adapter, MockInterface $mock): void
    {
        $mock->shouldReceive("fileExists")->with(['BucketId' => null, 'BucketName' => 'my_bucket', 'FileName' => 'something'])->andReturnTrue();
        $result = $adapter->fileExists('something');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testWrite(FilesystemAdapter $adapter, MockInterface $mock): void
    {
        $mock->shouldReceive("upload")
            ->once()
            ->with(['BucketId' => null, 'BucketName' => 'my_bucket', 'FileName' => 'something', 'Body' => 'contents'])
            ->andReturn(new File('something', '', '', '', ''));

        $adapter->write('something', 'contents', new Config());
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testWriteStream($adapter, $mock)
    {
        $mock->upload(['BucketId' => null, 'BucketName' => 'my_bucket', 'FileName' => 'something', 'Body' => 'contents'])->willReturn(new File('something', '', '', '', ''), false);
        $result = $adapter->writeStream('something', 'contents', new Config());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testUpdate($adapter, $mock)
    {
        $mock->upload(['BucketId' => null, 'BucketName' => 'my_bucket', 'FileName' => 'something', 'Body' => 'contents'])->willReturn(new File('something', '', '', '', ''), false);
        $result = $adapter->update('something', 'contents', new Config());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testUpdateStream($adapter, $mock)
    {
        $mock->upload(['BucketId' => null, 'BucketName' => 'my_bucket', 'FileName' => 'something', 'Body' => 'contents'])->willReturn(new File('something', '', '', '', ''), false);
        $result = $adapter->updateStream('something', 'contents', new Config());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('file', $result['type']);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testRead($adapter, $mock)
    {
        $file = new File('something', 'something4', '', '', '', '', 'my_bucket');
        $mock->getFile(['BucketId' => null, 'BucketName' => 'my_bucket', 'FileName' => 'something'])->willReturn($file, false);
        $mock->download(['FileId' => 'something'])->willReturn($file, false);
        $result = $adapter->read('something');
        $this->assertEquals(['contents' => $file], $result);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testReadStream($adapter, $mock)
    {
        //$mock->fileExists(["BucketName" => "my_bucket", "FileName" => "something"])->willReturn(true);
        $result = $adapter->readStream('something');
        $this->assertFalse($result);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testRename($adapter, $mock)
    {
        //$mock->fileExists(["BucketName" => "my_bucket", "FileName" => "something"])->willReturn(true);
        $result = $adapter->rename('something', 'something_new');
        $this->assertFalse($result);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testGetMetaData($adapter, $mock)
    {
        //$mock->fileExists(["BucketName" => "my_bucket", "FileName" => "something"])->willReturn(true);
        $result = $adapter->getMetadata('something');
        $this->assertFalse($result);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testGetMimetype($adapter, $mock)
    {
        //$mock->fileExists(["BucketName" => "my_bucket", "FileName" => "something"])->willReturn(true);
        $result = $adapter->getMimetype('something');
        $this->assertFalse($result);
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testCopy($adapter, $mock)
    {
        $this->fileSetUp();
        $mock->upload(['BucketId' => null, 'BucketName' => 'my_bucket', 'FileName' => 'something_new', 'Body' => ''])->willReturn(new File('something_new', '', '', '', ''), false);
        $result = $adapter->copy($this->file_mock->url(), 'something_new');
        $this->assertObjectHasAttribute('id', $result, 'something_new');
    }

    /**
     * @dataProvider  backblazeProvider
     */
    public function testListContents($adapter, $mock)
    {
        $mock->listFiles(['BucketId' => null, 'BucketName' => 'my_bucket'])->willReturn([new File('random_id', 'file1.txt'), new File('random_id', 'some_folder/file2.txt'), new File('random_id', 'some_folder/another_folder/file3.txt')]);
        $normalized_files = [
            [
                'type'      => 'file',
                'path'      => 'file1.txt',
                'timestamp' => false,
                'size'      => null,
            ],
            [
                'type'      => 'file',
                'path'      => 'some_folder/file2.txt',
                'timestamp' => false,
                'size'      => null,
            ],
            [
                'type'      => 'file',
                'path'      => 'some_folder/another_folder/file3.txt',
                'timestamp' => false,
                'size'      => null,
            ],
        ];
        $result1 = $adapter->listContents('', false);
        $this->assertEquals([$normalized_files[0]], $result1);
        $result2 = $adapter->listContents('some_folder', false);
        $this->assertEquals([$normalized_files[1]], $result2);
        $result3 = $adapter->listContents('', true);
        $this->assertEquals($normalized_files, $result3);
        $result3 = $adapter->listContents('some_folder', true);
        $this->assertEquals([$normalized_files[1], $normalized_files[2]], $result3);

        $this->expectException(InvalidArgumentException::class);
        $adapter->listContents(false, false);
        $adapter->listContents(false, 'haha');
        $adapter->listContents('', 'haha');
    }
}
