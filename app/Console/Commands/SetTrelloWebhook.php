<?php

declare(strict_types=1);

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;

class SetTrelloWebhook extends Command
{
    protected $signature = 'trello:webhook';
    protected $description = 'Set the webhook for the Trello board';
    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client();
    }

    public function handle()
    {
        $this->info('Checking existing webhooks...');

        $apiKey = config('trello.api_key');
        $token = config('trello.token');
        $boardId = config('trello.id_model');
        $webhookUrl = config('trello.webhook_url');

        try {
            $existingWebhooks = $this->client->get(
                "https://api.trello.com/1/tokens/{$token}/webhooks?key={$apiKey}"
            );

            $webhooks = json_decode($existingWebhooks->getBody()->getContents(), true);

            if (!empty($webhooks)) {
                $this->info('Existing ones found webhooks:');
                foreach ($webhooks as $webhook) {
                    $this->info("ID: {$webhook['id']}");
                    $this->info("URL: {$webhook['callbackURL']}");
                    $this->info("Model ID: {$webhook['idModel']}");
                    $this->line('-------------------');
                }

                if ($this->confirm('Want to delete existing webhooks before creating a new one?')) {
                    foreach ($webhooks as $webhook) {
                        $this->client->delete(
                            "https://api.trello.com/1/webhooks/{$webhook['id']}?key={$apiKey}&token={$token}"
                        );
                        $this->info("Webhook {$webhook['id']} removed.");
                    }
                } else {
                    $this->info('The operation has been cancelled.');
                    return;
                }
            }

            $url = "https://api.trello.com/1/webhooks?key={$apiKey}&token={$token}";
            $requestData = [
                'description' => 'Test Webhook',
                'callbackURL' => $webhookUrl,
                'idModel' => $boardId,
                'active' => true,
            ];

            $this->info('Creating a new one webhook...');
            $this->info('Request URL: ' . $url);
            $this->info('Request Data: ' . json_encode($requestData));

            $response = $this->client->post($url, ['json' => $requestData]);

            $this->info('Response Status Code: ' . $response->getStatusCode());
            $this->info('Response Body: ' . $response->getBody()->getContents());
            $this->info('Webhook successfully created!');

        } catch (RequestException $e) {
            $this->error('Error: ' . $e->getMessage());
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->error('Response Status Code: ' . $response->getStatusCode());
                $this->error('Response Body: ' . $response->getBody()->getContents());
            }
        }
    }
}
