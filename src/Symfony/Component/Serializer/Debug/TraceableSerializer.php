<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Collects some data about serialization.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
class TraceableSerializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface, EncoderInterface, DecoderInterface
{
    public const DEBUG_TRACE_ID = 'debug_trace_id';

    /**
     * @param SerializerInterface&NormalizerInterface&DenormalizerInterface&EncoderInterface&DecoderInterface $serializer
     */
    public function __construct(
        private SerializerInterface $serializer,
        private SerializerDataCollector $dataCollector,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(mixed $data, string $format, array $context = []): string
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->serialize($data, $format, $context);
        $time = microtime(true) - $startTime;

        $caller = $this->getCaller(__FUNCTION__, SerializerInterface::class);

        $this->dataCollector->collectSerialize($traceId, $data, $format, $context, $time, $caller);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->deserialize($data, $type, $format, $context);
        $time = microtime(true) - $startTime;

        $caller = $this->getCaller(__FUNCTION__, SerializerInterface::class);

        $this->dataCollector->collectDeserialize($traceId, $data, $type, $format, $context, $time, $caller);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->normalize($object, $format, $context);
        $time = microtime(true) - $startTime;

        $caller = $this->getCaller(__FUNCTION__, NormalizerInterface::class);

        $this->dataCollector->collectNormalize($traceId, $object, $format, $context, $time, $caller);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->denormalize($data, $type, $format, $context);
        $time = microtime(true) - $startTime;

        $caller = $this->getCaller(__FUNCTION__, DenormalizerInterface::class);

        $this->dataCollector->collectDenormalize($traceId, $data, $type, $format, $context, $time, $caller);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function encode(mixed $data, string $format, array $context = []): string
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->encode($data, $format, $context);
        $time = microtime(true) - $startTime;

        $caller = $this->getCaller(__FUNCTION__, EncoderInterface::class);

        $this->dataCollector->collectEncode($traceId, $data, $format, $context, $time, $caller);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function decode(string $data, string $format, array $context = []): mixed
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->decode($data, $format, $context);
        $time = microtime(true) - $startTime;

        $caller = $this->getCaller(__FUNCTION__, DecoderInterface::class);

        $this->dataCollector->collectDecode($traceId, $data, $format, $context, $time, $caller);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $this->serializer->supportsNormalization($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $this->serializer->supportsDenormalization($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding(string $format, array $context = []): bool
    {
        return $this->serializer->supportsEncoding($format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding(string $format, array $context = []): bool
    {
        return $this->serializer->supportsDecoding($format, $context);
    }

    /**
     * Proxies all method calls to the original serializer.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->serializer->{$method}(...$arguments);
    }

    private function getCaller(string $method, string $interface): array
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 8);

        $file = $trace[0]['file'];
        $line = $trace[0]['line'];

        for ($i = 1; $i < 8; ++$i) {
            if (isset($trace[$i]['class'], $trace[$i]['function'])
                && $method === $trace[$i]['function']
                && is_a($trace[$i]['class'], $interface, true)
            ) {
                $file = $trace[$i]['file'];
                $line = $trace[$i]['line'];

                break;
            }
        }

        $name = str_replace('\\', '/', $file);
        $name = substr($name, strrpos($name, '/') + 1);

        return compact('name', 'file', 'line');
    }
}
