<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Console\Command;

class RunAdHocComputeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ad-hoc:run';

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
        # Some YAML, simliar to GitHub Actions
        $rawYaml = '
name: "Test Run"

steps:
  - name: "Print JSON Payload"
    uses: hookflow/print-payload
    with:
      path: /opt/payload.json

  - name: "Print current directory"
    run: "ls -lah $(pwd)"

  - run: "echo foo"

  - uses: hookflow/s3
    with:
      src: "/opt/payload.json"
      bucket: "some-bucket"
      key: "payload.json"
      dry_run: true
    env:
      AWS_ACCESS_KEY_ID: "abckey"
      AWS_SECRET_ACCESS_KEY: "xyzkey"
      AWS_REGION: "us-east-2"
';

        $encodedYaml = base64_encode($rawYaml);

        # Some random JSON payload that our YAML
        # above references
        $somePayload = '
        {
            "data": {
                "event": "foo-happened",
                "customer": "cs_1234",
                "amount": 1234.56,
                "note": "we in it now!"
            },
            "pages": 1,
            "links": {"next_page": "https://next-page.com/foo", "prev_page": "https://next-page.com/actually-previous-page"}
        }
        ';

        $encodedPayload = base64_encode($somePayload);

        # Create the payload for our API call te Fly Machines API
        $appName = 'my-adhoc-puter';
        $requestPayload = json_decode(sprintf('{
            "region": "bos",
            "config": {
                "image": "registry.fly.io/%s:latest",
                "guest": {"cpus": 2, "memory_mb": 2048,"cpu_kind": "shared"},
                "auto_destroy": true,
                "processes": [
                    {"cmd": ["php", "/var/www/html/artisan", "ad-hoc"]}
                ],
                "files": [
                    {
                        "guest_path": "/opt/payload.json",
                        "raw_value": "%s"
                    },
                    {
                        "guest_path": "/opt/instructions.yaml",
                        "raw_value": "%s"
                    }
                ]
            }
        }
        ', $appName, $encodedPayload, $encodedYaml));

        // todo 🦉: create config/fly.php
        // and set token to ENV('FLY_TOKEN');
        $flyAuthToken = config('fly.token');

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($flyAuthToken)
            ->post(
                "https://api.machines.dev/v1/apps/${appName}/machines",
                $requestPayload
            );

        if (! $response->successful())
        {
            Log::error("Could not make request to Fly", [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }
    }
}
