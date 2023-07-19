<?php
namespace WFSAPP;

require_once('wfs-app-wpengine-init.php');

class WPENGINE {
  private $user;
  private $pass;

  public function __construct(){
    $this->auth();
  }

  /**
   * Build the auth string
   **/
  private function auth(){
    $this->user = $_ENV['WPENGINE_USER_ID'];
    $this->pass = $_ENV['WPENGINE_PASSWORD'];

    return base64_encode($this->user.':'.$this->pass);
  }

  /**
   * Return response from WP Engine API looping through all pages handling get or post methods, query strings and data.
   **/
  public function get($endpoint = 'sites', $method = 'GET', $args = false){
    $output = false;
    $ch = curl_init();

    $headers[] = "Authorization: Basic " . $this->auth();

    $url = "https://api.wpengineapi.com/v1/$endpoint";

    if($method == 'GET' && $args !== false){
      $query = '?';

      if(is_array($args)){
        $query_args = [];

        foreach($args as $key => $value){
          $query_args[] = urlencode("$key=$value");
        }

        $query .= implode('&', $query_args);
      } else {
        $query .= urlencode($args);
      }

      $url .= "$query";
    }

    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

    if($method == 'POST') {
      $body = json_encode($args, JSON_FORCE_OBJECT);
      $headers[] = 'accept: application/json';
      $headers[] = 'Content-Type: application/json';

      curl_setopt( $ch, CURLOPT_POST, 1 );
      curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    }

    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    $result = curl_exec( $ch );
    if ( curl_errno( $ch ) ) {
      $output = ['error' => curl_error( $ch )];
    } else {
      if(isset($result['next']) && ($result['next'] != 'null' || $result['next'] !== null)){
        $output = [
          $result,
          $this->get($result['next']),
        ];
      } else {
        $output = [$result];
      }
    }

    curl_close( $ch );

    return $output;
  }

  /**
   * Return all sites
   **/
  public function get_all_sites(){
    $output = false;

    $result = $this->get();

    if(isset($result['error'])){
      $output = $result;
    } else {
      foreach($result['results'] as $site){
        $account = $site['account']['id'];

        $output[$site['name']] = [
          'id' => $site['id'],
          'name' => $site['name'],
          'account' => $site['account']['id'],
          'group' => $site['group_name'],
        ];

        $installs = false;

        foreach($site['installs'] as $install){
          $name = $install['name'];
          $view = "https://my.wpengine.com/installs/$name";

          $installs[$name] = [
            'id' => $install['id'],
            'name' => $name,
            'environment' => $install['environment'],
            'php' => $install['php'],
            'view' => $view,
            'admin' => "$view/launch_wp_admin?account_id=$account",
            'plugins' => "$view/plugins_and_themes",
          ];
        }

        if($installs){
          $output[$site['name']]['installs'] = $installs;
        }
      }
    }

    return $output;
  }

  /**
   * Creates a backup of $install with $description notifying $emails
   **/
  public function backup($install, $description = 'quick backup', $emails = 'support@whitefoxstudios.net'){
    return $this->get("installs/$install/backups", 'POST', [
      'description' => $description,
      'notification_emails' => $emails,
    ]);
  }

  /**
   * Purge the object and page cache of $install
   **/
  public function purge($install){
    $output['object'] = $this->get("installs/$install/purge_cache", 'POST', [
      'type' => 'object',
    ]);

    $output['page'] = $this->get("installs/$install/purge_cache", 'POST', [
      'type' => 'page',
    ]);

    return $output;
  }

  public function return_json($data = false){
    if(!$data){
      $data = $this->get_all_sites();
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

    return json_encode($data, $flags);
  }
}

if(isset($_POST) && isset($_POST['sites'])){
  header("Content-Type: application/json");

  $wfs_app_wpengine = new \WFSAPP\WPENGINE();

  print($wfs_app_wpengine->return_json());
} else { ?>
<!doctype html>
<html>
<head>
<title>WP Engine Sites</title>
<style>

</style>
</head>
<body>
<div class="sites"></div>
<script>
  //fetch…
</script>
</body>
</html><?php }
