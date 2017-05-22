<?php

namespace Amp\ByteStream;

use Amp\Promise;
use function Amp\call;

final class ZlibInputStream implements InputStream {
    private $source;
    private $encoding;
    private $options;
    private $resource;

    /**
     * ZlibInputStream constructor.
     *
     * @param InputStream $source
     * @param int         $encoding
     * @param array       $options
     *
     * @throws StreamException
     * @throws \Error
     *
     * @see http://php.net/manual/en/function.inflate-init.php
     */
    public function __construct(InputStream $source, int $encoding, array $options = []) {
        $this->source = $source;
        $this->encoding = $encoding;
        $this->options = $options;
        $this->resource = @\inflate_init($encoding, $options);

        if ($this->resource === false) {
            throw new StreamException("Failed initializing deflate context");
        }
    }

    public function read(): Promise {
        return call(function () {
            if ($this->resource === null) {
                return null;
            }

            $data = yield $this->source->read();

            // Needs a double guard, as stream might have been closed while reading
            if ($this->resource === null) {
                return null;
            }

            if ($data === null) {
                $decompressed = \inflate_add($this->resource, "", \ZLIB_FINISH);

                if ($decompressed === false) {
                    throw new StreamException("Failed adding data to deflate context");
                }

                $this->close();

                return $decompressed;
            }

            $decompressed = \inflate_add($this->resource, $data, \ZLIB_SYNC_FLUSH);

            if ($decompressed === false) {
                throw new StreamException("Failed adding data to deflate context");
            }

            return $decompressed;
        });
    }

    protected function close() {
        $this->resource = null;
        $this->source = null;
    }

    public function getEncoding(): int {
        return $this->encoding;
    }

    public function getOptions(): array {
        return $this->options;
    }
}
