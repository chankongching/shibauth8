<?php

/**
 * @file
 * Contains ShibAuth8Subscriber.
 */

namespace Drupal\shibauth8\EventSubscriber;

use Drupal\shibauth8\Login\LoginHandler;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ShibAuth8Subscriber
 *
 * @package Drupal\shibauth8\EventSubscriber
 */
class ShibAuth8Subscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\shibauth8\Login\LoginHandler
   */
  private $lh;

  /**
   * ShibAuth8Subscriber constructor.
   *
   * @param \Drupal\shibauth8\Login\LoginHandler $lh
   */
  public function __construct(LoginHandler $lh){
    $this->lh = $lh;

//$tmp = $lh->getShibSession();
//\Drupal::logger('shibauth8')->notice('<pre>' . print_r($tmp, TRUE) . '</pre>');

    // \Drupal::logger('shiauth8')->notice('<pre>' . print_r($this->lh, TRUE) . '</pre>');
  }

  /**
   * Login-- Processes Drupal login, then redirects.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function checkShibbolethLogin(GetResponseEvent $event) {
\Drupal::logger('shibauth8')->notice("checkShibbolethLogin");
    if (!empty($this->lh->getShibSession()->getSessionId())) {
      // Check if there is an active drupal login.
      if (\Drupal::currentUser()->isAnonymous()) {
        // Call the shib login function in the login handler class.
        if ($response = $this->lh->shibLogin()) {
          // We need to remove the destination or it will redirect to that
          // rather than where we actually want to go.
          \Drupal::request()->query->remove('destination');
          return $response;
        }
      }
    }

    // Will redirect to ?destination by default.
    $url = \Drupal\Core\Url::fromRoute('<front>')->toString();
    return new RedirectResponse($url);
  }

  /**
   * Show debug messages, if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function checkForShibbolethDebug(GetResponseEvent $event) {

    $config = \Drupal::config('shibauth8.shibbolethsettings');

    if (!$config->get('enable_debug_mode')) {
      // Debugging is off-- bail.
      return;
    }

    $patterns = '';
    if ($path_prefix = $config->get('debug_prefix_path')) {
      $patterns = $path_prefix . "\n" . $path_prefix . '*';
    }

    $current_path = \Drupal::service('path.current')->getPath();
    if ($patterns && !\Drupal::service('path.matcher')->matchPath($current_path, $patterns)) {
      // Path doesn't match-- bail.
      return;
    }

    $tags = ['pre', 'b', 'br'];

    $debug_message = Xss::filter('<b>' . t('Shibboleth debug information') . '</b>', $tags);
    $rendered_message = \Drupal\Core\Render\Markup::create($debug_message);
    drupal_set_message($rendered_message);

    $current_user = \Drupal::currentUser();
    if ($current_user->id()) {
      $user_info = array(
        'uid' => $current_user->id(),
        'name' => $current_user->getAccountName(),
        'mail' => $current_user->getEmail(),
        'roles' => $current_user->getRoles(),
      );
      $debug_message = Xss::filter('<b>Drupal::currentUser():</b><br/><pre>' . print_r($user_info, TRUE) . '</pre>', $tags);
      $rendered_message = \Drupal\Core\Render\Markup::create($debug_message);
      drupal_set_message($rendered_message);
    }

    // Show $_SESSION variables.
    // Work around that drupal_set_message() keeps previous messages in $_SESSION.
    if (!empty($_SESSION)) {
      $session_copy = $_SESSION;
    }
    else {
      $session_copy = [];
    }


    if (isset($session_copy['messages'])) {
      unset($session_copy['messages']);
    }
    $debug_message = Xss::filter('<b>$_SESSION:</b><br/><pre>' . print_r($session_copy, TRUE) . '</pre>', $tags);
    unset($session_copy);
    $rendered_message = \Drupal\Core\Render\Markup::create($debug_message);
    drupal_set_message($rendered_message);

    // Show $_SERVER variables.
    $debug_message = Xss::filter('<b>$_SERVER:</b><br/><pre>' . print_r($_SERVER, TRUE) . '</pre>', $tags);
    $rendered_message = \Drupal\Core\Render\Markup::create($debug_message);
    drupal_set_message($rendered_message);

    // Show config settings.
    $settings = $config->getRawData();
    $adv_config = \Drupal::config('shibauth8.advancedsettings');
    $settings += $adv_config->getRawData();
    ksort($settings);
    $debug_message = Xss::filter('<b>' . t('Module configuration') . ':</b><br/><pre>' . print_r($settings, TRUE) . '</pre>', $tags);
    $rendered_message = \Drupal\Core\Render\Markup::create($debug_message);
    drupal_set_message($rendered_message);

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
$events[KernelEvents::REQUEST][] = array('checkShibbolethLogin', 27);
    $events[KernelEvents::REQUEST][] = array('checkForShibbolethDebug', 28);
    return $events;
  }

}
