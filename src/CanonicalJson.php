<?php
declare(strict_types=1);
namespace Webkernel\StdOps;
use Webkernel\StdOps\CanonicalJson\Canonicalizator;

/**
 * Fluent wrapper around Canonicalizator.
 *
 * Usage:
 *   webkernel_json_canonicalize($data)->toString()
 *   webkernel_json_canonicalize($data)->hash(strategy: 'sha256')
 *   webkernel_json_canonicalize($data)->hash(strategy: 'sha3-256')
 *   webkernel_json_canonicalize($data)->hash(strategy: 'blake2b512')
 *
 * Supported strategies (no external dependency):
 *   sha256, sha384, sha512, sha3-224, sha3-256, sha3-384, sha3-512  → hash()
 *   blake2b512, md5                                                  → openssl_digest() / hash()
 */
final class CanonicalJson implements \Stringable
{
    /**
     * Singleton Canonicalizator — instantiated once per process.
     */
    private static ?Canonicalizator $engine = null;

    /**
     * Lazily computed canonical string.
     */
    private ?string $canonical = null;

    /**
     * @param mixed $data  Any PHP value (scalar, array, object, null)
     */
    private function __construct(private readonly mixed $data) {}

    /**
     * Factory — called by webkernel_json_canonicalize().
     * Accepts any PHP value OR a raw JSON string (auto-decoded).
     */
    public static function of(mixed $data): self
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }
        return new self($data);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the RFC 8785 / JCS canonical JSON string.
     */
    public function toString(): string
    {
        return $this->resolve();
    }

    public function __toString(): string
    {
        return $this->resolve();
    }

    /**
     * Returns a hex digest of the canonical JSON string.
     *
     * @param  non-empty-string  $strategy  One of: sha256, sha384, sha512,
     *                                       sha3-224, sha3-256, sha3-384, sha3-512,
     *                                       blake2b512, md5
     * @param  bool              $raw        Return raw binary instead of hex
     * @throws \InvalidArgumentException     On unknown algorithm
     */
    public function hash(string $strategy = 'sha256', bool $raw = false): string
    {
        $canonical = $this->resolve();

        // blake2b512 is only in OpenSSL, not in PHP's hash() list on some builds
        if ($strategy === 'blake2b512') {
            if (!extension_loaded('openssl')) {
                throw new \RuntimeException('ext-openssl is required for blake2b512.');
            }
            $digest = openssl_digest($canonical, 'blake2b512', $raw);
            if ($digest === false) {
                throw new \RuntimeException('openssl_digest failed for blake2b512.');
            }
            return $digest;
        }

        if (!in_array($strategy, hash_algos(), true)) {
            throw new \InvalidArgumentException(
                sprintf('Unknown hash strategy "%s". Available: %s.', $strategy, implode(', ', self::supportedStrategies()))
            );
        }

        return hash($strategy, $canonical, $raw);
    }

    /**
     * Returns the list of guaranteed-available strategies.
     *
     * @return list<string>
     */
    public static function supportedStrategies(): array
    {
        $native = array_intersect(
            ['md5', 'sha256', 'sha384', 'sha512', 'sha3-224', 'sha3-256', 'sha3-384', 'sha3-512'],
            hash_algos()
        );

        $extras = [];
        if (extension_loaded('openssl') && in_array('blake2b512', openssl_get_md_methods(), true)) {
            $extras[] = 'blake2b512';
        }

        return array_values(array_merge(array_values($native), $extras));
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function resolve(): string
    {
        if ($this->canonical === null) {
            $data = $this->data;
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }
            $this->canonical = $this->getEngine()->canonicalize($data);
        }
        return $this->canonical;
    }

    private function getEngine(): Canonicalizator
    {
        if (!self::$engine instanceof \Webkernel\StdOps\CanonicalJson\Canonicalizator) {
            self::$engine = new Canonicalizator();
        }
        return self::$engine;
    }
}
