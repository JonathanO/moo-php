<?php

function getClient($key, $secret, $endpoint = null)
{
    $guzzle = new \Guzzle\Http\Client();
    $oauthPlugin = new \MooPhp\Client\Guzzle\ReconfigurableOauthPlugin(
        array(
            "consumer_key" => $key,
            "consumer_secret" => $secret
        )
    );
    $guzzle->addSubscriber($oauthPlugin);

    /*
    // If we want to do three legged we'd need to jump about a bit.
    // Since most of the API calls no longer need 3 legged, this block of code is not required.
    $oauthHelper = new \MooPhp\Client\Guzzle\GuzzleOAuthHelper($guzzle);

    $tokenData = $oauthHelper->getRequestToken();

    // At this point if you were writing a web app you'd want to store the request token data.
    // Then redirect the user orf too (passing a useful callback, this example uses oob callbacks):
    print "Visit:\n" . $oauthHelper->getAuthUrl($tokenData["token"]) . "\n";
    print "Hit enter";
    fgets(STDIN);

    $oauthPlugin->setTokenData($tokenData);
    $tokenData = $oauthHelper->getAccessToken();

    $oauthPlugin->setTokenData($tokenData);
    */

    return new \MooPhp\Client\GuzzleClient($guzzle, null, $endpoint);
}

