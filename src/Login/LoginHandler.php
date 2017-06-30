<?php

namespace Drupal\shibauth8\Login;

use Drupal\Component\Utility\Random;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\PrivateTempStoreFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class LoginHandler.
 *
 * @package Drupal\shibauth8
 */
class LoginHandler implements LoginHandlerInterface {

  /**
   * @var
   */
  protected $user;
  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $user_store;
  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;
  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $adv_config;
  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;
  /**
   * @var \Drupal\shibauth8\Login\ShibSessionVars
   */
  protected $shib_session;
  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $shib_logger;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $temp_store_factory;
  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $custom_email_store;

  /**
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $session_manager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $current_user;

  /**
   * LoginHandler constructor.
   * @param \Drupal\Core\Database\Connection $db
   * @param \Drupal\Core\Config\ImmutableConfig $config
   * @param \Drupal\Core\Config\ImmutableConfig $advanced_config
   */
  public function __construct(Connection $db, ImmutableConfig $config, ImmutableConfig $adv_config, EntityTypeManagerInterface $etm, ShibSessionVars $shib_session, LoggerInterface $shib_logger, PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {
    $this->db = $db;
    $this->config = $config;
    $this->adv_config = $adv_config;
    $this->user_store = $etm->getStorage('user');
    $this->shib_session = $shib_session;
    $this->shib_logger = $shib_logger;
    $this->temp_store_factory = $temp_store_factory;
    $this->session_manager = $session_manager;
    $this->current_user = $current_user;
    $this->custom_email_store = $this->temp_store_factory->get('shibauth8');

    //Start Session if it does not exist yet
    if ($this->current_user->isAnonymous() && !isset($_SESSION['session_started'])) {
      $_SESSION['session_started'] = TRUE;
      $this->session_manager->start();
    }
  }

  /**
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function shibLogin() {


    try {
      //Register new user if user does not exist
      if (!$this->checkUserExists()) {

        $user_registered = FALSE;

        //Start Session if it does not exist yet


        //Check if custom email has been set
        $custom_email = $this->custom_email_store->get('custom_email');
        if (empty($custom_email)) {
          $this->custom_email_store->set('return_url', \Drupal::request()
            ->getRequestUri());
          //redirect to email form if custom email has not been set
          $response = new RedirectResponse(Url::fromRoute('shibauth8.custom_email_form')
            ->toString());
          $response->send();
        }
        else {
          $user_registered = $this->registerNewUser();
        }
      }
      else {
        $user_registered = TRUE;
      }

      if ($user_registered) {
        $this->authenticateUser();
      }
    } catch (\Exception $e) {
      //log the error to drupal log messages
      $this->shib_logger->error($e);
      //kill the drupal session
      user_logout();
      $return_url = '';
      if ($this->adv_config->get('url_redirect_logout')) {
        $return_url = '?return=' . $this->adv_config->get('url_redirect_logout');
      }
      //redirect to shib logout url
      return new RedirectResponse($this->config->get('shibboleth_logout_handler_url') . $return_url);
    }
  }

  /**
   * Adds user to the shib_auth table in the database;
   */
  private function registerNewUser($success = FALSE) {

    $user_data = [
      'name' => $this->shib_session->getTargetedId(),
      'mail' => $this->custom_email_store->get('custom_email'),
      'pass' => $this->genPassword(),
      'status' => 1,
    ];

    //Create Drupal user
    $this->user = $this->user_store->create($user_data);
    $results = $this->user->save();

    //Throw exception if drupal user creation fails
    if (!$results) {
      throw new \Exception('Error creating new drupal user from Shibboleth Session');
      //return FALSE;
    }

    //Insert shib data into shib_authmap table
    $shib_data = [
      'uid' => $this->user->id(),
      'targeted_id' => $this->shib_session->getTargetedId(),
      'idp' => $this->shib_session->getIdp(),
      'created' => REQUEST_TIME,
    ];
    $success = $this->db->insert('shib_authmap')->fields($shib_data)->execute();

    //thow exception if shib_authmap insert fails
    if (!$success) {
      throw new \Exception('Error creating new drupal user from Shibboleth Session. Database insert on shib_authmap failed');
    }
    return TRUE;
  }

  /**
   * @return bool
   * @throws \Exception
   */
  private function authenticateUser() {
    if (empty($this->user)) {
      throw new \Exception('No uid found for user when trying to initialize drupal session');
      //return FALSE;
    }
    user_login_finalize($this->user);
    return TRUE;
  }

  /**
   * check shib_authmap table to see if the user exists. Return true if user found
   *
   * @return bool
   */
  private function checkUserExists() {
    $user_query = $this->db->select('shib_authmap');
    $user_query->fields('shib_authmap', ['id', 'uid', 'targeted_id']);
    $user_query->condition('targeted_id', $this->shib_session->getTargetedId());
    $results = $user_query->execute()->fetchAll();

    if (empty($results)) {
      //no user found
      return FALSE;
    }

    if (count($results) > 1) {
      throw new \Exception('Multiple entries for a user exist in the shib_authmap table');
      //return FALSE;
    }

    $this->user = User::load($results[0]->uid);

    if (empty($this->user)) {
      throw new \Exception('User information exists in shib_authmap table, but drupal user does not exist');
      //return FALSE;
    }
    return TRUE;
  }

  /**
   * generate a random password for the drupal user account
   *
   * @return string
   */
  private function genPassword() {
    $rand = new Random();
    return $rand->string(30);
  }

  /**
   * @return \Drupal\shibauth8\Login\ShibSessionVars
   */
  public function getShibSession() {
    return $this->shib_session;
  }

}
