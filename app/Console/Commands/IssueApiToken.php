<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:issue-token {email : Email of an existing user} {--name=api : Token name}')]
#[Description('Issue a Sanctum API token for an existing user')]
class IssueApiToken extends Command
{
    public function handle(): int
    {
        $user = User::firstWhere('email', $this->argument('email'));

        if (! $user) {
            $this->error("User [{$this->argument('email')}] not found.");

            return self::FAILURE;
        }

        $this->line($user->createToken($this->option('name'))->plainTextToken);

        return self::SUCCESS;
    }
}
