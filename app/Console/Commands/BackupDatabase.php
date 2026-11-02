<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\BackupController;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:run {--uploads : Also zip public/uploads} {--no-uploads : Skip public/uploads even if enabled in settings}';

    protected $description = 'Dump the database to storage/app/backups (gzipped), optionally zip public/uploads, and prune old backups';

    public function handle(): int
    {
        $uploads = $this->option('uploads') ? true : ($this->option('no-uploads') ? false : null);
        $result = BackupController::run($uploads);

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
