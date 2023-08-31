<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Console\Command;

class AdHocComputeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ad-hoc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!file_exists("/opt/instructions.yaml")) {
            $this->error("Could not find instructions file /opt/instructions.yaml");
            return SELF::FAILURE;
        }


        $instructions = Yaml::parseFile("/opt/instructions.yaml");

        // draw the rest of the owl
        foreach ($instructions['steps'] as $step) {
            if ($this->isAnAction($step)) {
                // Clone repo and run script within it
                $this->downloadAndRunAction($step);
            } else {
                // Run action directly
                $this->runAction($step);
            }
        }

        return SELF::SUCCESS;
    }

    protected function isAnAction(array $step): bool
    {
        return (! empty($step['uses']));
    }

    protected function runAction(array $step, $pwd="."): void
    {
        // TODO: Support env vars, etc
        Process::path($pwd)->run($step['run']);
    }

    protected function cloneAndRunAction($step): void
    {
        Process::run('git clone https://github.com/'.$step['uses'].'.git');

        $this->runAction($step, "./${step['uses']}");
    }
}
