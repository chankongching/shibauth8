<?php

/**
 * @file
 * Contains ShibAuth8Subscriber.
 */

namespace Drupal\shibauth8\EventSubscriber;

use Drupal\Core\Url;
use Drupal\shibauth8\Login\LoginHandler;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
   * @var array
   */
  private $excluded_routes;

  /**
   * ShibAuth8Subscriber constructor.
   *
   * @param \Drupal\shibauth8\Login\LoginHandler $lh
   */
  public function __construct(LoginHandler $lh){
    $this->lh = $lh;
    $this->excluded_routes = array('shibauth8.custom_email_form');
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function checkForShibbolethSession(GetResponseEvent $event) {

    // Check if there is currently an active shibboleth session.
    // Don't initiate session on the excluded urls.
    $cur_route = \Drupal::routeMatch()->getRouteName();

    if (!in_array($cur_route, $this->excluded_routes)) {
      if (!empty($this->lh->getShibSession()->getSessionId())) {
        // Check if there is an active drupal login.
        if (\Drupal::currentUser()->isAnonymous()) {
          // Call the shib login function in the login handler class.
          $this->lh->shibLogin();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkForShibbolethSession', 28);
    return $events;
  }

}
