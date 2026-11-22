<?php

namespace Whilesmart\Webhooks\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Whilesmart\Webhooks\Models\Webhook;

class EncryptSecrets extends Command
{
    protected $signature = 'webhooks:encrypt-secrets
        {--decrypt : Decrypt stored secrets back to plain text}';

    protected $description = 'Encrypt stored webhook secrets at rest, or decrypt them back with --decrypt';

    public function handle(): int
    {
        $decrypt = (bool) $this->option('decrypt');

        if ($decrypt === Webhook::encryptsSecrets()) {
            $this->error($decrypt
                ? 'Set webhooks.encrypt_secrets to false before decrypting stored secrets.'
                : 'Set webhooks.encrypt_secrets to true before encrypting stored secrets.');

            return self::FAILURE;
        }

        $encrypter = Webhook::currentEncrypter();
        $model = new Webhook();
        $keyName = $model->getKeyName();
        $changed = 0;
        $skipped = 0;

        Webhook::withTrashed()
            ->whereNotNull('secret')
            ->toBase()
            ->select([$keyName, 'secret'])
            ->chunkById(100, function ($rows) use ($encrypter, $decrypt, $keyName, &$changed, &$skipped) {
                foreach ($rows as $row) {
                    $value = $this->convert((string) $row->secret, $decrypt, $encrypter);

                    if ($value === null) {
                        $skipped++;

                        continue;
                    }

                    Webhook::withTrashed()->toBase()->where($keyName, $row->{$keyName})->update(['secret' => $value]);
                    $changed++;
                }
            }, $keyName);

        $this->info(sprintf('%s %d webhook secrets, skipped %d.', $decrypt ? 'Decrypted' : 'Encrypted', $changed, $skipped));

        return self::SUCCESS;
    }

    private function convert(string $value, bool $decrypt, Encrypter $encrypter): ?string
    {
        try {
            $plain = $encrypter->decrypt($value, false);
        } catch (DecryptException) {
            return $decrypt ? null : $encrypter->encrypt($value, false);
        }

        return $decrypt ? $plain : null;
    }
}
