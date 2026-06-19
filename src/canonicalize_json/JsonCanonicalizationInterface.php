<?php declare(strict_types=1);
namespace Webkernel\StdOps\CanonicalJson;

interface JsonCanonicalizationInterface
{
    /** @param $data */
    public function canonicalize($data, bool $asHex = false): string;
}
