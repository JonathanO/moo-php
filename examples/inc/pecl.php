<?php

function getClient($key, $secret, $endpoint)
{
    $client = new \MooPhp\Client\OAuthSigningClient($key, $secret, null, $endpoint);
    /*
    // If we want to do three legged we'd need to jump about a bit.
    // Since most of the API calls no longer need 3 legged, this block of code is not required.

    $request_token = $client->getRequestToken();

    // At this point if you were writing a web app you'd want to store the request token data.
    // Then redirect the user orf too (passing a useful callback, this example uses oob callbacks):
    print "Visit:\n" . $client->getAuthUrl() . "\n";
    print "Hit enter";
    fgets(STDIN);

    // So now the user would be redirected back. You'd need to call setToken() with the request token data.

    // Then get an access token and you're done:
    $client->getAccessToken();
    */
    return $client;
}

