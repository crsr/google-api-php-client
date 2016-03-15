<?php

function getService()
{
  // Creates and returns the Analytics service object.

  // Load the Google API PHP Client Library.
  require_once 'src/Google/autoload.php';

  //retrieve auth data from json file. (auth.json it's ignored by git)

  /*
   * {
   *     "service_account_email": "email_from_google_services",
   *     "key_file_location": "filename.p12"
   *   }
   */
  $authObj = json_decode(file_get_contents('auth.json'));

  // Use the developers console and replace the values with youry
  // service account email, and relative location of your key file.
  $service_account_email = $authObj->service_account_email;
  $key_file_location = $authObj->key_file_location;//notasecret

  // Create and configure a new client object.
  $client = new Google_Client();
  //$client->setUseObjects(true);
  $client->setApplicationName("AnalyticsReportsSNG");

  $analytics = new Google_Service_Analytics($client);

  // Read the generated client_secrets.p12 key.
  $key = file_get_contents($key_file_location);
  $cred = new Google_Auth_AssertionCredentials(
      $service_account_email,
      array(Google_Service_Analytics::ANALYTICS_READONLY),
      $key
  );
  $client->setAssertionCredentials($cred);
  if($client->getAuth()->isAccessTokenExpired()) {
    $client->getAuth()->refreshTokenWithAssertion($cred);
  }

  return $analytics;
}

function getFirstprofileId(&$analytics) {
  // Get the user's first view (profile) ID.
  // Get the list of accounts for the authorized user.
  $accounts = $analytics->management_accounts->listManagementAccounts();

  if (count($accounts->getItems()) > 0) {
    $items = $accounts->getItems();
    $firstAccountId = $items[0]->getId();

    // Get the list of properties for the authorized user.
    $properties = $analytics->management_webproperties
        ->listManagementWebproperties($firstAccountId);

    if (count($properties->getItems()) > 0) {
      $items = $properties->getItems();
      $firstPropertyId = $items[0]->getId();

      // Get the list of views (profiles) for the authorized user.
      $profiles = $analytics->management_profiles
          ->listManagementProfiles($firstAccountId, $firstPropertyId);

      if (count($profiles->getItems()) > 0) {
        $items = $profiles->getItems();

        // Return the first view (profile) ID.
        return $items[0]->getId();

      } else {
        throw new Exception('No views (profiles) found for this user.');
      }
    } else {
      throw new Exception('No properties found for this user.');
    }
  } else {
    throw new Exception('No accounts found for this user.');
  }
}

function getStatistics(&$analytics, $profileId) {
  /*
   * sessions
   * avgSessionDuration
   * Hits
   * uniquePageviews
   * avgTimeOnPage
   */
  return $analytics->data_ga->get(
      'ga:' . $profileId,
      '2016-01-01',
      'yesterday',
      'ga:sessions%2Cga:avgSessionDuration%2Cga:hits%2Cga:uniquePageviews%2Cga:avgTimeOnPage');
}

  /*
   * Users filtred by country
   * Sort by users number (DESC)
   */
function getCountriesStatistics(&$analytics, $profileId){
   return $analytics->data_ga->get(
       'ga:' . $profileId,
       '2016-01-01',
       'yesterday',
       'ga:users',
       array(
           'dimensions'  => 'ga:country',
           'sort'        => '-ga:users'
       )
   );
}

   /*
    * Returned users
    */
function getReturnedUsers(&$analytics, $profileId){
  return $analytics->data_ga->get(
      'ga:' . $profileId,
      '2016-01-01',
      'yesterday',
      'ga:users',
      array(
          'segment'  => 'gaid%3A%3A-3'
      )
  );
}
function printResults(&$results,$rows = false) {
  // Parses the response from the Core Reporting API and prints
  // the profile name and total sessions.
  if (count($results->getRows()) > 0) {
    // Get the entry for the first entry in the first row.
    if($rows)
      return $results->getRows();
    else {
      $results = $results->getTotalsForAllResults();
      return $results;
    }
  } else {
    return null;
  }
}

$analytics = getService();

$profile = getFirstProfileId($analytics);
//$results = getSessions($analytics, $profile);
// Script start
$rustart = getrusage();

print json_encode(array('generalStatistics' => printResults(getStatistics($analytics, $profile)),'countriesStatistics' => printResults(getCountriesStatistics($analytics, $profile),true),'returnedUsers' => printResults(getReturnedUsers($analytics, $profile))));

function rutime($ru, $rus, $index) {
  return ($ru["ru_$index.tv_sec"]*1000 + intval($ru["ru_$index.tv_usec"]/1000))
  -  ($rus["ru_$index.tv_sec"]*1000 + intval($rus["ru_$index.tv_usec"]/1000));
}

$ru = getrusage();
echo "\nThis process used " . rutime($ru, $rustart, "utime") .
    " ms for its computations";
echo "\nIt spent " . rutime($ru, $rustart, "stime") .
    " ms in system calls\n";

?>
