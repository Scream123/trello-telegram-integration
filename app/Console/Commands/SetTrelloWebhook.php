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
        $this->info('Setting Trello webhook...');

        $apiKey = config('trello.api_key');
        $token = config('trello.token');
        $boardId = config('trello.id_model');
        $webhookUrl = config('trello.webhook_url');

        $url = "https://api.trello.com/1/webhooks?key={$apiKey}&token={$token}";


        $this->info('API Key: ' . $apiKey);
        $this->info('Token: ' . $token);
        $this->info('Board ID: ' . $boardId);
        $this->info('Webhook URL: ' . $webhookUrl);

        try {
            $response = $this->client->post($url, [
                'json' => [
                    'description' => 'Test Webhook',
                    'callbackURL' => $webhookUrl,
                    'idModel' => $boardId,
                    'active' => true,
                ]
            ]);

            $this->info('Response Status Code: ' . $response->getStatusCode());
            $this->info('Response Body: ' . $response->getBody());

        } catch (RequestException $e) {
            $this->error('Error: ' . $e->getMessage());
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->error('Response Status Code: ' . $response->getStatusCode());
                $this->error('Response Body: ' . $response->getBody());
                $this->error('Response Headers: ' . json_encode($response->getHeaders()));
            }
        }
        $this->info('Request URL: ' . $url);
        $this->info('Request Data: ' . json_encode([
                'description' => 'Test Webhook',
                'callbackURL' => $webhookUrl,
                'idModel' => $boardId,
                'active' => true,
            ]));
    }
}
