<?php

namespace Agentoom\Core\Ai\Support;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

final class CostCapturingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;
    private string $buffer = '';
    private ?float $lastReportedCost = null;

    public function __construct(StreamInterface $stream, private readonly CostCaptureStore $store)
    {
        $this->stream = $stream;
    }

    public function read($length): string
    {
        $data = $this->stream->read($length);
        if ($data !== '') {
            $this->consume($data);
        }

        return $data;
    }

    public function getContents(): string
    {
        $contents = $this->stream->getContents();
        if ($contents !== '') {
            $this->consume($contents, true);
        }

        return $contents;
    }

    private function consume(string $data, bool $complete = false): void
    {
        $this->buffer .= $data;
        if (strlen($this->buffer) > 65536) {
            $this->buffer = substr($this->buffer, -65536);
        }

        $lines = preg_split('/\r?\n/', $this->buffer);
        if ($lines === false) {
            return;
        }
        if (! $complete && ! str_ends_with($this->buffer, "\n")) {
            $this->buffer = (string) array_pop($lines);
        } else {
            $this->buffer = '';
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'data:')) {
                $this->parseJson(substr($line, 5));
            }
        }

        $json = json_decode($data, true);
        if (is_array($json)) {
            $this->parsePayload($json);
        }
    }

    private function parseJson(string $json): void
    {
        $payload = json_decode(trim($json), true);
        if (is_array($payload)) {
            $this->parsePayload($payload);
        }
    }

    private function parsePayload(array $payload): void
    {
        $usage = $payload['usage'] ?? null;
        if (! is_array($usage) || ! array_key_exists('cost', $usage)) {
            return;
        }

        $raw = $usage['cost'];
        if (! is_int($raw) && ! is_float($raw) && ! (is_string($raw) && is_numeric($raw))) {
            return;
        }
        $cost = (float) $raw;
        if (! is_finite($cost) || $cost < 0) {
            return;
        }

        if ($this->lastReportedCost !== null && $cost <= $this->lastReportedCost) {
            return;
        }

        $this->store->add($this->lastReportedCost === null ? $cost : $cost - $this->lastReportedCost);
        $this->lastReportedCost = $cost;
    }
}
