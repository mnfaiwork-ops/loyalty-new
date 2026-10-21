<?php

namespace LoyaltyNew\Core\Settings;

/** File import setting ditolak; $errors berisi kalimat yang ditampilkan ke admin. */
final class InvalidSettingsFile extends \RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'File setting tidak valid.');
    }
}
