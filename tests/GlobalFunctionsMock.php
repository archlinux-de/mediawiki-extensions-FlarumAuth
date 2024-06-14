<?php

namespace MediaWiki\Auth;

use MediaWiki\Message\Message;
use MessageSpecifier;

/** @See ../vendor/mediawiki/core/includes/GlobalFunctions.php */
function wfMessage(string|array|MessageSpecifier $key, mixed ...$params): Message
{
    $message = new Message($key);

    if ($params) {
        $message->params(...$params);
    }

    return $message;
}
