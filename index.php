<?php
  require_once('env.php');
  /*
  * This function can be used to generate the URL an account owner would use to allow your app to access their account.
  * After visiting the URL, the account owner is prompted to log in and allow your app to access their account.
  * The account owner is then redirected to your redirect URL with the authorization code and state appended as query parameters. e.g.:
  * http://localhost:8888/?code={authorization_code}&state={encoded_string_value(s)}
  */

  /**
   * @param $redirectURI - URL Encoded Redirect URI
   * @param $clientId - API Key
   * @param $scope - URL encoded, plus sign delimited list of scopes that your application requires. The 'offline_access' scope needed to request a refresh token is added by default.
   * @param $state - Arbitrary string value(s) to verify response and preserve application state
   * @return string - Full Authorization URL
   */

  
 
  function getAuthorizationURL($clientId, $redirectURI, $scope, $state) {
      // Create authorization URL
      $baseURL = "https://authz.constantcontact.com/oauth2/default/v1/authorize";
      $authURL = $baseURL . "?client_id=" . $clientId . "&scope=" . $scope . "+offline_access&response_type=code&state=" . $state . "&redirect_uri=" . $redirectURI;

      return $authURL;
  }

  /**
   * @param $redirectURI - URL Encoded Redirect URI
   * @param $clientId - API Key
   * @param $clientSecret - API Secret
   * @param $code - Authorization Code
   * @return string - JSON String of results
   */

  function getAccessToken($redirectURI, $clientId, $clientSecret, $code) {
    // Use cURL to get access token and refresh token
    $ch = curl_init();

    // Define base URL
    $base = 'https://authz.constantcontact.com/oauth2/default/v1/token';

    // Create full request URL
    $url = $base . '?code=' . $code . '&redirect_uri=' . $redirectURI . '&grant_type=authorization_code';
    curl_setopt($ch, CURLOPT_URL, $url);

    // Set authorization header
    // Make string of "API_KEY:SECRET"
    $auth = $clientId . ':' . $clientSecret;
    // Base64 encode it
    $credentials = base64_encode($auth);
    // Create and set the Authorization header to use the encoded credentials, and set the Content-Type header
    $authorization = 'Authorization: Basic ' . $credentials;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/x-www-form-urlencoded'));

    // Set method and to expect response
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Make the call
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }

  /*
 * This function can be used to exchange a refresh token for a new access token and refresh token.
 * Make this call by passing in the refresh token returned with the access token.
 * The response will contain a new 'access_token' and 'refresh_token'
 */

  /**
   * @param $refreshToken - The refresh token provided with the previous access token
   * @param $clientId - API Key
   * @param $clientSecret - API Secret
   * @return string - JSON String of results
   */
  function refreshToken($refreshToken, $clientId, $clientSecret) {
    // Use cURL to get a new access token and refresh token
    $ch = curl_init();

    // Define base URL
    $base = 'https://authz.constantcontact.com/oauth2/default/v1/token';

    // Create full request URL
    $url = $base . '?refresh_token=' . $refreshToken . '&grant_type=refresh_token';
    curl_setopt($ch, CURLOPT_URL, $url);

    // Set authorization header
    // Make string of "API_KEY:SECRET"
    $auth = $clientId . ':' . $clientSecret;
    // Base64 encode it
    $credentials = base64_encode($auth);
    // Create and set the Authorization header to use the encoded credentials, and set the Content-Type header
    $authorization = 'Authorization: Basic ' . $credentials;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/x-www-form-urlencoded'));

    // Set method and to expect response
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Make the call
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }

  $authURL = getAuthorizationURL($ccClientId, $ccRedirectURI, $ccScope, $ccState);

  function handleSubmit($ccRedirectURI, $ccClientId, $ccClientSecret) {
    // get code from url query params
    $urlParamString = $_SERVER['QUERY_STRING'];
    $code = str_replace('&state=secretstate', '', $urlParamString);
    $ccAuthCode = str_replace('code=', '', $code);
    var_dump($ccAuthCode);
    
    if ($ccAuthCode) {
      // get code from authURL callback address
      $token = json_decode(getAccessToken($ccRedirectURI, $ccClientId, $ccClientSecret, $ccAuthCode), true);
      var_dump($token);
      
      // get new token if expired
      if ($token && $token['expires_in'] < 6000) {
        $refreshToken = $token['refesh_token'];
        $token = json_decode(refreshToken($refreshToken, $ccClientId, $ccClientSecret), true);
      }

      if ($token) {
        
        // set up request body & headers
        $email = $_POST['email_address'];
        $first_name = $_POST['first_name'] || '';
        $last_name = $_POST['last_name'] || '';
        $body = json_encode(array(
          'email_address' => array(
            'address' => $email,
            'permission_to_send' => 'implicit',
          ),
          'first_name' => $first_name,
          'last_name' => $last_name,
        ));
        $headers = array(
          'cache-control' => 'no-cache',
          'authorization' => 'Bearer ' . $token ,
          'content-type' => 'application/json',
          'accept' => 'application/json'
        );

        // init cURL
        $url = 'https://api.cc.email/v3/contacts';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //IMP if the url has https and you don't want to verify source certificate

        $curl_response = curl_exec($ch);
        $response = json_decode($curl_response);
        curl_close($ch);
        var_dump($response);
      }
    }
  }

?>

<!DOCTYPE html>
<html lang="en">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Contact form</title>
  </head>
  <body>
    <h1>Add a Contact to Constant Contact</h1>
    <a href="<?php echo $authURL; ?>" target="_blank">Authorize Constant Contact</a>
    <form style="display: flex; flex-direction: column; max-width: 600px; margin: 0 auto;" action="<?php handleSubmit($ccRedirectURI, $ccClientId, $ccClientSecret); ?>" method="POST">
      <label for="first_name" style="margin-top: 20px;">First Name</label>
      <input type="text" name="first_name" id="first_name"></input>
      <label for="last_name" style="margin-top: 20px;">Last Name</label>
      <input type="text" name="last_name" id="last_name"></input>
      <label for="email_address" style="margin-top: 20px;" required>Email</label>
      <input type="email" name="email_address" id="email_address"></input>
      <button type="submit" style="margin-top: 20px;">Add Contact</button>
    </form>
  </body>
</html>