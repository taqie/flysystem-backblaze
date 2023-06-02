<?php

namespace Taqie\Flysystem;

use BackblazeB2\Client;
use GuzzleHttp\Psr7;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

class BackblazeAdapter implements FilesystemAdapter
{
    public function __construct(protected Client $client, protected string $bucketName, protected string|null $bucketId = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $contents,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $file = $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $resource,
        ]);

//        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        $file = $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $contents,
        ]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        $file = $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $resource,
        ]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $path): string
    {
        $file = $this->getClient()->getFile([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
        ]);
        $fileContent = $this->getClient()->download([
            'FileId' => $file->getId(),
        ]);

        return ['contents' => $fileContent];
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $stream = Psr7\stream_for();
        $download = $this->getClient()->download([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'SaveAs'     => $stream,
        ]);
        $stream->seek(0);

        try {
            $resource = Psr7\StreamWrapper::getResource($stream);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return $download === true ? ['stream' => $resource] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $newPath,
            'Body'       => @file_get_contents($path),
        ]);


    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): void
    {
        $this->getClient()->deleteFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($path)
    {
        return $this->getClient()->deleteFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($path, Config $config)
    {
        return $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        $file = $this->getClient()->getFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        $file = $this->getClient()->getFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $fileObjects = $this->getClient()->listFiles([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
        ]);
        if ($recursive === true && $directory === '') {
            $regex = '/^.*$/';
        } elseif ($recursive === true && $directory !== '') {
            $regex = '/^'.preg_quote($directory).'\/.*$/';
        } elseif ($recursive === false && $directory === '') {
            $regex = '/^(?!.*\\/).*$/';
        } elseif ($recursive === false && $directory !== '') {
            $regex = '/^'.preg_quote($directory).'\/(?!.*\\/).*$/';
        } else {
            throw new \InvalidArgumentException();
        }
        $fileObjects = array_filter($fileObjects, function ($fileObject) use ($regex) {
            return 1 === preg_match($regex, $fileObject->getName());
        });
        $normalized = array_map(function ($fileObject) {
            return $this->getFileInfo($fileObject);
        }, $fileObjects);

        return array_values($normalized);
    }

    /**
     * Get file info.
     *
     * @param $file
     *
     * @return array
     */
    protected function getFileInfo($file)
    {
        $normalized = [
            'type'      => 'file',
            'path'      => $file->getName(),
            'timestamp' => substr($file->getUploadTimestamp(), 0, -3),
            'size'      => $file->getSize(),
        ];

        return $normalized;
    }

    public function fileExists(string $path): bool
    {
        return $this->getClient()->fileExists(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);
    }

    public function directoryExists(string $path): bool
    {
        // TODO: Implement directoryExists() method.
    }

    public function deleteDirectory(string $path): void
    {
        // TODO: Implement deleteDirectory() method.
    }

    public function createDirectory(string $path, Config $config): void
    {
        // TODO: Implement createDirectory() method.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // TODO: Implement setVisibility() method.
    }

    public function visibility(string $path): FileAttributes
    {
        // TODO: Implement visibility() method.
    }

    public function mimeType(string $path): FileAttributes
    {
        // TODO: Implement mimeType() method.
    }

    public function lastModified(string $path): FileAttributes
    {
        // TODO: Implement lastModified() method.
    }

    public function fileSize(string $path): FileAttributes
    {
        // TODO: Implement fileSize() method.
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // TODO: Implement move() method.
    }
}
