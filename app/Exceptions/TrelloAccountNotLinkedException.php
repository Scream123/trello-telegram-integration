<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TrelloAccountNotLinkedException extends Exception
{
    protected $message = 'Trello account not linked.';
}
