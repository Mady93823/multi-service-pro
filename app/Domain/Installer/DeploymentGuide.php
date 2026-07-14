<?php

namespace App\Domain\Installer;

/**
 * The three processes this app needs and the exact text to make them run.
 *
 * The installer finishes with a green tick, and then the site is *quietly* half
 * dead: no cron means unpaid bookings never expire and payouts never release; no
 * queue worker means notifications are written and never sent; no Reverb means
 * the live map never moves. None of the three announces itself — every page
 * still loads — which is precisely why "did you set up the worker?" is the
 * support ticket this screen exists to prevent (M15/M24 lesson, finished here).
 *
 * The blocks are generated with *this* install's paths, so they are copy-paste,
 * not a template to fill in. `/admin/system` shows the same text again later,
 * for the operator who closed the wizard before reading it.
 */
class DeploymentGuide
{
    /**
     * @return array{cron: string, supervisor: string, systemd_queue: string, systemd_reverb: string, php: string, root: string}
     */
    public function handle(): array
    {
        $php = $this->phpBinary();
        $root = base_path();

        return [
            'php' => $php,
            'root' => $root,
            'cron' => "* * * * * cd {$root} && {$php} artisan schedule:run >> /dev/null 2>&1",
            'supervisor' => $this->supervisor($php, $root),
            'systemd_queue' => $this->systemd('UrbanServe queue worker', "{$php} artisan queue:work --sleep=3 --tries=3 --max-time=3600", $root),
            'systemd_reverb' => $this->systemd('UrbanServe Reverb WebSocket server', "{$php} artisan reverb:start", $root),
        ];
    }

    private function phpBinary(): string
    {
        // A PHP-FPM worker reports *its own* binary, and `php-fpm artisan` is not
        // a command — fall back to whatever `php` resolves to on the box.
        return str_contains(PHP_BINARY, 'fpm') ? 'php' : PHP_BINARY;
    }

    private function supervisor(string $php, string $root): string
    {
        return <<<CONF
        [program:urbanserve-queue]
        process_name=%(program_name)s_%(process_num)02d
        command={$php} {$root}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
        directory={$root}
        autostart=true
        autorestart=true
        stopwaitsecs=3600
        numprocs=1
        user=www-data
        redirect_stderr=true
        stdout_logfile={$root}/storage/logs/queue.log

        [program:urbanserve-reverb]
        command={$php} {$root}/artisan reverb:start
        directory={$root}
        autostart=true
        autorestart=true
        numprocs=1
        user=www-data
        redirect_stderr=true
        stdout_logfile={$root}/storage/logs/reverb.log
        CONF;
    }

    private function systemd(string $description, string $command, string $root): string
    {
        return <<<CONF
        [Unit]
        Description={$description}
        After=network.target

        [Service]
        User=www-data
        Restart=always
        WorkingDirectory={$root}
        ExecStart={$command}

        [Install]
        WantedBy=multi-user.target
        CONF;
    }
}
