<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanAuthData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:clean
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all login tokens, sessions, users, email verifications, and related auth data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will DELETE all users, tokens, sessions, and auth data. Are you sure?')) {
            $this->info('Cancelled.');
            return Command::SUCCESS;
        }

        try {
            DB::beginTransaction();

            // 1. Clear login tokens (API tokens from Sanctum)
            $tokens = DB::table('personal_access_tokens')->count();
            DB::table('personal_access_tokens')->delete();
            $this->info("Cleared {$tokens} login token(s).");

            // 2. Clear sessions
            $sessions = DB::table('sessions')->count();
            DB::table('sessions')->delete();
            $this->info("Cleared {$sessions} session(s).");

            // 3. Clear ticket comments (references users)
            $comments = DB::table('ticket_comments')->count();
            DB::table('ticket_comments')->delete();
            $this->info("Cleared {$comments} ticket comment(s).");

            // 4. Clear tickets (references users)
            $tickets = DB::table('tickets')->count();
            DB::table('tickets')->delete();
            $this->info("Cleared {$tickets} ticket(s).");

            // 5. Clear Facebook account links
            $fbAccounts = DB::table('facebook_accounts')->count();
            DB::table('facebook_accounts')->delete();
            $this->info("Cleared {$fbAccounts} Facebook account link(s).");

            // 6. Clear users
            $users = DB::table('users')->count();
            DB::table('users')->delete();
            $this->info("Cleared {$users} user(s).");

            // 7. Clear email verifications (registration codes)
            $verifications = DB::table('email_verifications')->count();
            DB::table('email_verifications')->delete();
            $this->info("Cleared {$verifications} email verification(s).");

            // 8. Clear password reset tokens
            $resets = DB::table('password_reset_tokens')->count();
            DB::table('password_reset_tokens')->delete();
            $this->info("Cleared {$resets} password reset token(s).");

            DB::commit();
            $this->newLine();
            $this->info('All auth data has been cleared successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to clean auth data: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
