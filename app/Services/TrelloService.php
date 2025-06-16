<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TrelloAccountNotLinkedException;
use App\Models\TelegramUser;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrelloService
{
    protected $client;
    protected $apiKey;
    protected $baseUrl = 'https://api.trello.com/1/';

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->apiKey = config('trello.api_key');
    }

    public function getUserBoards(string $userId): array
    {
        // Check if the user has a Trello link
        $user = $this->getUserWithTrelloToken($userId);

        // Cache the request to the Trello API for 5 minutes
        return Cache::remember("trello_boards_{$userId}", 300, function () use ($user) {
            return $this->fetchTrelloBoards($user->trello_token);
        });
    }

    protected function getUserWithTrelloToken(string $userId)
    {
        $user = TelegramUser::where('user_id', $userId)->first();

        if (!$user || !$user->trello_token) {
            throw new TrelloAccountNotLinkedException("Trello account not linked for user $userId.");
        }

        return $user;
    }

    protected function fetchTrelloBoards(string $trelloToken)
    {
        $response = $this->client->get('https://api.trello.com/1/members/me/boards', [
            'query' => [
                'key' => config('trello.api_key'),
                'token' => $trelloToken
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getUserTasks(string $userId): array
    {
        // Cache the request to the Trello API for 5 minutes
        return Cache::remember("trello_tasks_{$userId}", 300, function () use ($userId) {
            try {
                $user = TelegramUser::where('user_id', $userId)->firstOrFail();

                if (!$user->trello_token) {
                    throw new \Exception('Trello token missing for user ' . $userId);
                }

                $response = $this->client->get('https://api.trello.com/1/members/me/cards', [
                    'query' => [
                        'key' => config('trello.api_key'),
                        'token' => $user->trello_token,
                        'filter' => 'open'
                    ]
                ]);

                return json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $e) {
                Log::error('Error fetching Trello tasks for user ' . $userId . ': ' . $e->getMessage());
                return null;
            }
        });
    }
}
