<?php
declare(strict_types=1);
namespace ParagonIE\CipherSweet;

use ParagonIE\CipherSweet\Contract\BackendInterface;
use ParagonIE\CipherSweet\Exception\CipherSweetException;
use ParagonIE\CipherSweet\Exception\CryptoOperationException;
use ParagonIE\CipherSweet\Exception\FilesystemException;
use ParagonIE\ConstantTime\Binary;

/**
 * Class EncryptedFile
 * @package ParagonIE\CipherSweet
 */
class EncryptedFile
{
    /** @var int $chunkSize */
    protected int $chunkSize;

    /** @var CipherSweet $engine */
    protected CipherSweet $engine;

    /**
     * EncryptedFile constructor.
     * @param CipherSweet $engine
     * @param int $chunkSize      Affects how much memory is read at once.
     */
    public function __construct(CipherSweet $engine, int $chunkSize = 8192)
    {
        $this->engine = $engine;
        $this->chunkSize = $chunkSize;
    }

    /**
     * @return BackendInterface
     */
    public function getBackend(): BackendInterface
    {
        return $this->engine->getBackend();
    }

    /**
     * @return string
     */
    public function getBackendPrefix(): string
    {
        return $this->engine->getBackend()->getPrefix();
    }

    /**
     * @return CipherSweet
     */
    public function getEngine(): CipherSweet
    {
        return $this->engine;
    }

    /**
     * Decrypts a file. Uses the KeyProvider.
     *
     * @param string $inputFile
     * @param string $outputFile
     * @return bool
     *
     * @throws CryptoOperationException
     * @throws FilesystemException
     */
    public function decryptFile(string $inputFile, string $outputFile): bool
    {
        if (\realpath($inputFile) === \realpath($outputFile)) {
            $inputRealStream = $this->getStreamForFile($inputFile, 'rb');
            $inputStream = $this->copyStreamToTemp($inputRealStream);
            \fclose($inputRealStream);
        } else {
            $inputStream = $this->getStreamForFile($inputFile, 'rb');
        }
        $outputStream = $this->getStreamForFile($outputFile);
        try {
            return $this->decryptStream($inputStream, $outputStream);
        } finally {
            \fclose($inputStream);
            \fclose($outputStream);
        }
    }

    /**
     * Decrypts a file. Uses the given password, NOT the KeyProvider.
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param string $password
     * @return bool
     *
     * @throws FilesystemException
     */
    public function decryptFileWithPassword(
        string $inputFile,
        string $outputFile,
        string $password
    ): bool {
        if (\realpath($inputFile) === \realpath($outputFile)) {
            $inputRealStream = $this->getStreamForFile($inputFile, 'rb');
            $inputStream = $this->copyStreamToTemp($inputRealStream);
            \fclose($inputRealStream);
        } else {
            $inputStream = $this->getStreamForFile($inputFile, 'rb');
        }
        $outputStream = $this->getStreamForFile($outputFile);
        try {
            return $this->decryptStreamWithPassword(
                $inputStream,
                $outputStream,
                $password
            );
        } finally {
            \fclose($inputStream);
            \fclose($outputStream);
        }
    }

    /**
     * Decrypts a stream. Uses the KeyProvider.
     *
     * @param resource $inputFP
     * @param resource $outputFP
     * @return bool
     *
     * @throws CryptoOperationException
     * @throws CipherSweetException
     * @throws \SodiumException
     */
    public function decryptStream($inputFP, $outputFP): bool
    {
        $key = $this->engine->getFieldSymmetricKey(
            Constants::FILE_TABLE,
            Constants::FILE_COLUMN
        );
        return $this->engine->getBackend()->doStreamDecrypt(
            $inputFP,
            $outputFP,
            $key,
            $this->chunkSize
        );
    }

    /**
     * Decrypts a stream. Uses the given password, NOT the KeyProvider.
     *
     * @param resource $inputFP
     * @param resource $outputFP
     * @param string $password
     * @return bool
     *
     * @throws CryptoOperationException
     * @throws \SodiumException
     */
    public function decryptStreamWithPassword(
        $inputFP,
        $outputFP,
        string $password
    ): bool {
        $backend = $this->engine->getBackend();
        $salt = $this->getSaltFromStream($inputFP);
        $key = $backend->deriveKeyFromPassword($password, $salt);
        return $backend->doStreamDecrypt(
            $inputFP,
            $outputFP,
            $key,
            $this->chunkSize
        );
    }

    /**
     * Encrypts a file. Uses the KeyProvider.
     *
     * @param string $inputFile
     * @param string $outputFile
     * @return bool
     *
     * @throws CryptoOperationException
     * @throws FilesystemException
     */
    public function encryptFile(string $inputFile, string $outputFile): bool
    {
        if (\realpath($inputFile) === \realpath($outputFile)) {
            $inputRealStream = $this->getStreamForFile($inputFile, 'rb');
            $inputStream = $this->copyStreamToTemp($inputRealStream);
            \fclose($inputRealStream);
        } else {
            $inputStream = $this->getStreamForFile($inputFile, 'rb');
        }
        $outputStream = $this->getStreamForFile($outputFile);
        try {
            return $this->encryptStream($inputStream, $outputStream);
        } finally {
            \fclose($inputStream);
            \fclose($outputStream);
        }
    }

    /**
     * Encrypts a file. Uses the given password, NOT the KeyProvider.
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param string $password
     *
     * @throws CryptoOperationException
     * @throws FilesystemException
     * @return bool
     */
    public function encryptFileWithPassword(
        string $inputFile,
        string $outputFile,
        string $password
    ): bool {
        if (\realpath($inputFile) === \realpath($outputFile)) {
            $inputRealStream = $this->getStreamForFile($inputFile, 'rb');
            $inputStream = $this->copyStreamToTemp($inputRealStream);
            \fclose($inputRealStream);
        } else {
            $inputStream = $this->getStreamForFile($inputFile, 'rb');
        }
        $outputStream = $this->getStreamForFile($outputFile);
        try {
            return $this->encryptStreamWithPassword(
                $inputStream,
                $outputStream,
                $password
            );
        } finally {
            \fclose($inputStream);
            \fclose($outputStream);
        }
    }

    /**
     * Encrypts a stream. Uses the KeyProvider.
     *
     * @param resource $inputFP
     * @param resource $outputFP
     * @return bool
     *
     * @throws CipherSweetException
     * @throws CryptoOperationException
     * @throws \SodiumException
     */
    public function encryptStream($inputFP, $outputFP): bool
    {
        $key = $this->engine->getFieldSymmetricKey(
            Constants::FILE_TABLE,
            Constants::FILE_COLUMN
        );
        return $this->engine->getBackend()->doStreamEncrypt(
            $inputFP,
            $outputFP,
            $key,
            $this->chunkSize
        );
    }

    /**
     * Encrypts a stream. Uses the given password, NOT the KeyProvider.
     *
     * @param resource $inputFP
     * @param resource $outputFP
     * @param string $password
     * @return bool
     *
     * @throws CryptoOperationException
     * @throws \SodiumException
     */
    public function encryptStreamWithPassword(
        $inputFP,
        $outputFP,
        string $password
    ): bool {
        try {
            // Do not generate a dummy salt!
            do {
                $salt = \random_bytes(16);
            } while (Util::hashEquals(Constants::DUMMY_SALT, $salt));
        } catch (\Exception $ex) {
            throw new CryptoOperationException('RNG failure');
        }

        $backend = $this->engine->getBackend();
        $key = $backend->deriveKeyFromPassword($password, $salt);
        return $backend->doStreamEncrypt(
            $inputFP,
            $outputFP,
            $key,
            $this->chunkSize,
            $salt
        );
    }

    /**
     * Read the salt from the encrypted file.
     *
     * @param resource $inputFP
     * @return string
     */
    public function getSaltFromStream($inputFP): string
    {
        $backend = $this->getBackend();
        \fseek($inputFP, $backend->getFileEncryptionSaltOffset(), SEEK_SET);

        /** @var string $salt */
        $salt = \fread($inputFP, 16);
        \fseek($inputFP, 0, SEEK_SET);
        return $salt;
    }

    /**
     * @param string $filename
     * @return bool
     *
     * @throws FilesystemException
     * @throws \SodiumException
     */
    public function isFileEncrypted(string $filename): bool
    {
        return $this->isStreamEncrypted($this->getStreamForFile($filename, 'rb'));
    }

    /**
     * @param resource $inputFile
     * @return bool
     *
     * @throws \SodiumException
     */
    public function isStreamEncrypted($inputFile): bool
    {
        $pos = \ftell($inputFile);
        \fseek($inputFile, 0, SEEK_SET);
        $header = \fread($inputFile, 5);

        // Can we get a valid header?
        if (Binary::safeStrlen($header) < 5) {
            return false;
        }

        // Compare the stored header with the backend:
        $expect = $this->getBackendPrefix();

        $return = Util::hashEquals($expect, $header);

        \fseek($inputFile, $pos, SEEK_SET);
        return $return;
    }

    /**
     * @param string $fileName
     * @param string $mode
     * @return resource
     * @throws FilesystemException
     */
    public function getStreamForFile(string $fileName = 'php://temp', string $mode = 'wb')
    {
        $fp = \fopen($fileName, $mode);
        if (!\is_resource($fp)) {
            throw new FilesystemException('Could not create stream');
        }
        if ($this->chunkSize !== 8192) {
            // Improve performance slightly:
            \stream_set_chunk_size($fp, $this->chunkSize);
        }
        return $fp;
    }

    /**
     * @param resource $realStream
     * @return resource
     *
     * @throws FilesystemException
     */
    protected function copyStreamToTemp($realStream)
    {
        $inputStream = $this->getStreamForFile();
        \fseek($inputStream, 0, SEEK_SET);
        \fseek($realStream, 0, SEEK_SET);
        \stream_copy_to_stream($realStream, $inputStream);
        return $inputStream;
    }
}
