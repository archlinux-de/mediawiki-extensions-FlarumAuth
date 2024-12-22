<?php

namespace MediaWiki\Auth;

use MediaWiki\Message\Message;
use Wikimedia\Message\MessageParam;
use Wikimedia\Message\MessageSpecifier;

/**
 * @See ../vendor/mediawiki/core/includes/GlobalFunctions.php
 * @param string|string[]|MessageSpecifier $key
 * @param MessageParam|MessageSpecifier|string|int|float|array ...$params
 */
function wfMessage(string|array|MessageSpecifier $key, mixed ...$params): Message
{
    $message = new Message($key);

    if ($params) {
        $message->params(...$params);
    }

    return $message;
}
