<?php

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Authenticated encryption for the gateway payload (libsodium XChaCha20-Poly1305).
 *
 * The ciphertext hides the payload, and the authentication tag covers both the payload and the
 * "associated data" (version, key id, timestamp, nonce, idempotency key, reference, template),
 * so changing any of them makes opening fail. The receiver also checks the timestamp window and
 * remembers nonces, which is what stops replays; encryption alone would not.
 */
final class SecureEnvelope
{
    public const VERSION = 'v1';

    /** @return array{v: string, kid: string, ts: int, nonce: string, ct: string} */
    public static function seal(array $payload, array $context, string $keyId, string $keyB64, ?int $timestamp = null): array
    {
        $key = self::key($keyB64);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ts = $timestamp ?? time(); // real clock, not the --now demo clock
        $envelope = ['v' => self::VERSION, 'kid' => $keyId, 'ts' => $ts, 'nonce' => self::b64($nonce)];
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            json_encode($payload, JSON_THROW_ON_ERROR),
            self::associatedData($envelope, $context),
            $nonce,
            $key,
        );
        sodium_memzero($key);

        return $envelope + ['ct' => self::b64($ciphertext)];
    }

    /** Opens an envelope or throws. Used by tests; the mock gateway has its own copy of this logic. */
    public static function open(array $envelope, array $context, string $keyB64): array
    {
        $key = self::key($keyB64);
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            self::unb64((string) ($envelope['ct'] ?? '')),
            self::associatedData($envelope, $context),
            self::unb64((string) ($envelope['nonce'] ?? '')),
            $key,
        );
        sodium_memzero($key);
        if ($plain === false) {
            throw new RuntimeException('Envelope failed authentication');
        }

        return json_decode($plain, true, flags: JSON_THROW_ON_ERROR);
    }

    /** The exact bytes both sides authenticate. Order and separators must match the receiver. */
    public static function associatedData(array $envelope, array $context): string
    {
        return implode('|', [
            $envelope['v'] ?? '', $envelope['kid'] ?? '', $envelope['ts'] ?? '', $envelope['nonce'] ?? '',
            $context['idempotency_key'] ?? '', $context['reference'] ?? '', $context['template'] ?? '',
        ]);
    }

    private static function key(string $keyB64): string
    {
        $key = base64_decode($keyB64, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('Payload key must be 32 bytes, base64 encoded');
        }

        return $key;
    }

    private static function b64(string $bytes): string
    {
        return sodium_bin2base64($bytes, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private static function unb64(string $text): string
    {
        try {
            return sodium_base642bin($text, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            return '';
        }
    }
}
