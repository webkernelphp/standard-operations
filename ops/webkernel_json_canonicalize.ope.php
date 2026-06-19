<?php
declare(strict_types=1);

use Webkernel\StdOps\CanonicalJson;

if (!function_exists('webkernel_json_canonicalize')) {
    /**
     * Returns a fluent CanonicalJson object for the given value.
     *
     * Examples:
     *   webkernel_json_canonicalize($data)->toString()
     *   webkernel_json_canonicalize($data)->hash(strategy: 'sha256')
     *   webkernel_json_canonicalize($data)->hash(strategy: 'sha3-256')
     *   webkernel_json_canonicalize($data)->hash(strategy: 'blake2b512')
     *   (string) webkernel_json_canonicalize($data)
     *
     * @param  mixed  $data  Any JSON-serializable PHP value
     * @return CanonicalJson
     */
    function webkernel_json_canonicalize(mixed $data): CanonicalJson
    {
        return CanonicalJson::of($data);
    }
}
