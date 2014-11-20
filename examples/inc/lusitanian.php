<?php
function getClient($key, $secret, $endpoint)
{
    return new \MooPhp\Client\Lusitanian\LusitanianDirectOauthClient($key, $secret, null, $endpoint);
}
